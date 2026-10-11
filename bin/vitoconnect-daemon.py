#!/usr/bin/env python3
"""
Vitoconnect Daemon for LoxBerry
Powered by PyViCare & paho-mqtt.
Provides bidirectional MQTT communication, automatic feature discovery,
change detection, and API quota tracking for Viessmann heating systems.
"""

import os
import sys
import time
import json
import signal
import logging
from pathlib import Path
from datetime import datetime, timedelta

# Import MQTT client
try:
    import paho.mqtt.client as mqtt
except ImportError:
    mqtt = None

# Import PyViCare
try:
    from PyViCare.PyViCare import PyViCare
    from PyViCare.PyViCareUtils import (
        PyViCareNotSupportedFeatureError,
        PyViCareRateLimitError,
        PyViCareInvalidDataError,
    )
except ImportError:
    PyViCare = None

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("vitoconnect-daemon")

# LoxBerry environment
LBHOMEDIR = os.environ.get("LBHOMEDIR", "/opt/loxberry")
PLUGIN_NAME = "Vitoconnect"
LBPCONFIG = os.environ.get("LBPCONFIG", f"{LBHOMEDIR}/config/plugins/{PLUGIN_NAME}")
LBPDATA = os.environ.get("LBPDATA", f"{LBHOMEDIR}/data/plugins/{PLUGIN_NAME}")
LBPLOG = os.environ.get("LBPLOG", f"{LBHOMEDIR}/log/plugins/{PLUGIN_NAME}")

CONFIG_FILE = Path(LBPCONFIG) / "config.json"
TOKEN_FILE = Path(LBPDATA) / "token.save"
PID_FILE = Path("/run/shm") / f"{PLUGIN_NAME}.pid"
STATS_FILE = Path("/run/shm") / f"{PLUGIN_NAME}_stats.json"

# Fallback PID
if not Path("/run/shm").exists():
    PID_FILE = Path("/tmp") / f"{PLUGIN_NAME}.pid"
    STATS_FILE = Path("/tmp") / f"{PLUGIN_NAME}_stats.json"


class QuotaTracker:
    """Tracks Viessmann API calls within a rolling 24-hour window."""

    def __init__(self, limit=1450):
        self.limit = limit
        self.call_timestamps = []

    def record_call(self):
        now = time.time()
        self.call_timestamps.append(now)
        self.prune()

    def prune(self):
        cutoff = time.time() - 86400  # 24 hours
        self.call_timestamps = [t for t in self.call_timestamps if t > cutoff]

    def count_24h(self):
        self.prune()
        return len(self.call_timestamps)

    def remaining_24h(self):
        return max(0, self.limit - self.count_24h())

    def is_quota_critical(self):
        return self.remaining_24h() < 100


class VitoconnectDaemon:
    def __init__(self, config_path: Path):
        self.config_path = config_path
        self.config = {}
        self.vicare = None
        self.devices = []
        self.mqtt_client = None
        self.running = False
        self.quota = QuotaTracker()
        self.last_published = {}
        self.last_heartbeat = 0
        self.force_poll = False
        self.base_topic = "vitoconnect"
        self.poll_interval = 60
        self.heartbeat_interval = 1800  # 30 min

    def load_config(self):
        if not self.config_path.exists():
            logger.error(f"Config file not found: {self.config_path}")
            return False
        try:
            with open(self.config_path, "r", encoding="utf-8") as f:
                self.config = json.load(f)
            logger.info("Configuration loaded successfully")
            return True
        except Exception as e:
            logger.error(f"Error reading config: {e}")
            return False

    def get_mqtt_settings(self):
        """Determine MQTT broker settings (LoxBerry MQTT Gateway or custom)."""
        mqtt_cfg = self.config.get("MQTT", {})
        enabled = mqtt_cfg.get("enabled", True)
        if isinstance(enabled, str):
            enabled = enabled.lower() in ["1", "true", "on", "yes"]

        if not enabled:
            return None

        self.base_topic = mqtt_cfg.get("topic", "vitoconnect").strip("/")

        host = mqtt_cfg.get("host", "").strip()
        user = mqtt_cfg.get("user", "")
        password = mqtt_cfg.get("pass", "")
        port = 1883

        # Auto-detect LoxBerry MQTT Gateway if host is empty
        if not host:
            shm_mqtt = Path("/dev/shm/mqttgateway.json")
            if not shm_mqtt.exists():
                shm_mqtt = Path("/run/shm/mqttgateway.json")

            if shm_mqtt.exists():
                try:
                    with open(shm_mqtt, "r") as f:
                        gw = json.load(f)
                    host = gw.get("brokeraddress", "127.0.0.1")
                    port = int(gw.get("brokerport", 1883))
                    user = gw.get("brokeruser", "")
                    password = gw.get("brokerpass", "")
                    logger.info(f"Using LoxBerry MQTT Gateway settings from {shm_mqtt}")
                except Exception as e:
                    logger.warning(f"Could not read MQTT Gateway config: {e}")

        # Fallback to localhost if still empty
        if not host:
            host = "127.0.0.1"

        if ":" in host:
            parts = host.split(":", 1)
            host = parts[0]
            try:
                port = int(parts[1])
            except ValueError:
                port = 1883

        return {
            "host": host,
            "port": port,
            "user": user,
            "password": password,
        }

    def init_vicare(self):
        """Initialize PyViCare with user credentials and OAuth token."""
        if PyViCare is None:
            logger.error("PyViCare library is not installed in current environment!")
            return False

        user = self.config.get("user")
        password = self.config.get("pass")
        client_id = self.config.get("apikey")

        if not user or not password or not client_id:
            logger.error("Viessmann credentials (user, pass, apikey) are missing in config.")
            return False

        TOKEN_FILE.parent.mkdir(parents=True, exist_ok=True)
        try:
            logger.info("Initializing PyViCare session...")
            self.vicare = PyViCare()
            self.vicare.initWithCredentials(
                username=user,
                password=password,
                token_file=str(TOKEN_FILE),
                client_id=client_id,
            )
            self.quota.record_call()
            self.devices = self.vicare.getDevices()
            logger.info(f"Connected to Viessmann API. Found {len(self.devices)} device(s).")
            return True
        except PyViCareRateLimitError:
            logger.error("Viessmann API rate limit reached! Pausing requests.")
            return False
        except Exception as e:
            logger.error(f"Failed to connect to Viessmann API: {e}", exc_info=True)
            return False

    def init_mqtt(self):
        """Initialize MQTT connection and command listeners."""
        if mqtt is None:
            logger.error("paho-mqtt library is not installed!")
            return False

        settings = self.get_mqtt_settings()
        if not settings:
            logger.info("MQTT is disabled in configuration.")
            return True

        client_id = f"loxberry_vitoconnect_{os.getpid()}"
        self.mqtt_client = mqtt.Client(client_id=client_id)

        if settings["user"]:
            self.mqtt_client.username_pw_set(settings["user"], settings["password"])

        self.mqtt_client.on_connect = self._on_mqtt_connect
        self.mqtt_client.on_message = self._on_mqtt_message

        try:
            logger.info(f"Connecting to MQTT Broker {settings['host']}:{settings['port']}...")
            self.mqtt_client.connect(settings["host"], settings["port"], keepalive=60)
            self.mqtt_client.loop_start()
            return True
        except Exception as e:
            logger.error(f"Could not connect to MQTT Broker: {e}")
            return False

    def _on_mqtt_connect(self, client, userdata, flags, rc):
        if rc == 0:
            logger.info("Connected to MQTT Broker.")
            set_topic = f"{self.base_topic}/set/#"
            client.subscribe(set_topic)
            client.subscribe(f"{self.base_topic}/poll")
            logger.info(f"Subscribed to command topics: {set_topic}")
            self.publish(f"{self.base_topic}/status", "online", retain=True, force=True)
        else:
            logger.error(f"MQTT connect failed with return code {rc}")

    def _on_mqtt_message(self, client, userdata, msg):
        topic = msg.topic
        payload = msg.payload.decode("utf-8").strip()
        logger.info(f"Received MQTT command on {topic}: {payload}")

        if topic == f"{self.base_topic}/poll":
            self.force_poll = True
            return

        prefix = f"{self.base_topic}/set/"
        if topic.startswith(prefix):
            subtopic = topic[len(prefix):]
            self.handle_command(subtopic, payload)

    def handle_command(self, subtopic: str, value: str):
        """Execute command via PyViCare."""
        if not self.devices:
            logger.warning("No active device to handle command.")
            return

        device = self.devices[0]
        try:
            parts = subtopic.split("/")
            
            # e.g. circuit_0/normal_temperature or circuit/0/normal_temperature
            if "circuit" in parts[0] or (len(parts) > 1 and parts[0] == "circuit"):
                circuit_idx = 0
                if "_" in parts[0]:
                    try:
                        circuit_idx = int(parts[0].split("_")[1])
                    except ValueError:
                        pass
                elif len(parts) > 1 and parts[1].isdigit():
                    circuit_idx = int(parts[1])
                    parts = [parts[0]] + parts[2:]

                circuits = getattr(device, "circuits", [])
                if circuit_idx < len(circuits):
                    circuit = circuits[circuit_idx]
                    cmd = parts[-1]
                    if cmd in ["target_temperature", "normal_temperature", "normal"]:
                        temp = float(value)
                        logger.info(f"Setting Circuit {circuit_idx} normal temperature to {temp}°C")
                        circuit.setNormalTargetTemperature(temp)
                    elif cmd in ["comfort_temperature", "comfort"]:
                        temp = float(value)
                        logger.info(f"Setting Circuit {circuit_idx} comfort temperature to {temp}°C")
                        circuit.setComfortTargetTemperature(temp)
                    elif cmd in ["reduced_temperature", "reduced"]:
                        temp = float(value)
                        logger.info(f"Setting Circuit {circuit_idx} reduced temperature to {temp}°C")
                        circuit.setReducedTargetTemperature(temp)
                    elif cmd in ["mode", "operating_mode"]:
                        logger.info(f"Setting Circuit {circuit_idx} mode to {value}")
                        circuit.setMode(value)
                    self.quota.record_call()
                    self.force_poll = True
                    return

            # DHW Commands
            if "dhw" in subtopic:
                if "target_temperature" in subtopic or "temperature" in subtopic:
                    temp = float(value)
                    logger.info(f"Setting DHW target temperature to {temp}°C")
                    device.setDomesticHotWaterTargetTemperature(temp)
                elif "one_time_charge" in subtopic:
                    if value.lower() in ["1", "start", "true", "on"]:
                        logger.info("Activating DHW one-time charge")
                        device.activateDomesticHotWaterOneTimeCharge()
                    else:
                        logger.info("Deactivating DHW one-time charge")
                        device.deactivateDomesticHotWaterOneTimeCharge()
                self.quota.record_call()
                self.force_poll = True
                return

            logger.warning(f"Unrecognized command subtopic: {subtopic}")
        except Exception as e:
            logger.error(f"Error executing command '{subtopic}' with value '{value}': {e}")

    def publish(self, topic: str, payload, retain=False, force=False):
        """Publish value to MQTT with Change-Detection."""
        if not self.mqtt_client:
            return

        str_val = str(payload)
        last_val = self.last_published.get(topic)

        if not force and last_val == str_val:
            return  # No change, skip

        self.last_published[topic] = str_val
        try:
            self.mqtt_client.publish(topic, str_val, qos=0, retain=retain)
        except Exception as e:
            logger.error(f"Error publishing to {topic}: {e}")

    def safe_get(self, func, default=None):
        try:
            val = func()
            return val if val is not None else default
        except (PyViCareNotSupportedFeatureError, AttributeError):
            return default
        except Exception as e:
            logger.debug(f"Feature lookup exception: {e}")
            return default

    def poll_and_publish(self):
        """Poll device status from Viessmann API and publish to MQTT."""
        if not self.vicare or not self.devices:
            return

        now = time.time()
        is_heartbeat = (now - self.last_heartbeat > self.heartbeat_interval)
        if is_heartbeat:
            self.last_heartbeat = now
            logger.info("Heartbeat triggered: republishing all values.")

        try:
            logger.debug("Polling data from Viessmann API...")
            self.quota.record_call()

            for dev_idx, device in enumerate(self.devices):
                dev_prefix = f"{self.base_topic}"
                if len(self.devices) > 1:
                    dev_prefix += f"/device_{dev_idx}"

                # Device General Info
                model = self.safe_get(device.getModelId, "Unknown")
                is_online = self.safe_get(device.isOnline, True)
                serial = self.safe_get(device.getSerial, "")

                self.publish(f"{dev_prefix}/model", model, force=is_heartbeat)
                self.publish(f"{dev_prefix}/online", "1" if is_online else "0", force=is_heartbeat)
                if serial:
                    self.publish(f"{dev_prefix}/serial", serial, force=is_heartbeat)

                # Core Temperatures
                out_temp = self.safe_get(device.getOutsideTemperature)
                if out_temp is not None:
                    self.publish(f"{dev_prefix}/temperature/outside", round(out_temp, 2), force=is_heartbeat)

                boiler_temp = self.safe_get(device.getBoilerTemperature)
                if boiler_temp is not None:
                    self.publish(f"{dev_prefix}/temperature/boiler", round(boiler_temp, 2), force=is_heartbeat)

                supply_temp = self.safe_get(device.getSupplyTemperature)
                if supply_temp is not None:
                    self.publish(f"{dev_prefix}/temperature/supply", round(supply_temp, 2), force=is_heartbeat)

                # Burner / Compressor
                burner_active = self.safe_get(device.getBurnerActive)
                if burner_active is not None:
                    self.publish(f"{dev_prefix}/burner/active", "1" if burner_active else "0", force=is_heartbeat)

                burner_hours = self.safe_get(device.getBurnerHours)
                if burner_hours is not None:
                    self.publish(f"{dev_prefix}/burner/hours", burner_hours, force=is_heartbeat)

                burner_starts = self.safe_get(device.getBurnerStarts)
                if burner_starts is not None:
                    self.publish(f"{dev_prefix}/burner/starts", burner_starts, force=is_heartbeat)

                burner_mod = self.safe_get(device.getBurnerModulation)
                if burner_mod is not None:
                    self.publish(f"{dev_prefix}/burner/modulation", burner_mod, force=is_heartbeat)

                # Heat pump specific
                comp_active = self.safe_get(device.getCompressorActive)
                if comp_active is not None:
                    self.publish(f"{dev_prefix}/compressor/active", "1" if comp_active else "0", force=is_heartbeat)

                comp_hours = self.safe_get(device.getCompressorHours)
                if comp_hours is not None:
                    self.publish(f"{dev_prefix}/compressor/hours", comp_hours, force=is_heartbeat)

                # Domestic Hot Water (DHW)
                dhw_temp = self.safe_get(device.getDomesticHotWaterStorageTemperature)
                if dhw_temp is not None:
                    self.publish(f"{dev_prefix}/dhw/temperature", round(dhw_temp, 2), force=is_heartbeat)

                dhw_target = self.safe_get(device.getDomesticHotWaterTargetTemperature)
                if dhw_target is not None:
                    self.publish(f"{dev_prefix}/dhw/target_temperature", round(dhw_target, 2), force=is_heartbeat)

                dhw_pump = self.safe_get(device.getDomesticHotWaterCirculationPump)
                if dhw_pump is not None:
                    self.publish(f"{dev_prefix}/dhw/circulation_pump", "1" if dhw_pump else "0", force=is_heartbeat)

                dhw_charge = self.safe_get(device.getDomesticHotWaterOneTimeCharge)
                if dhw_charge is not None:
                    self.publish(f"{dev_prefix}/dhw/one_time_charge", "1" if dhw_charge else "0", force=is_heartbeat)

                # Errors
                active_errors = self.safe_get(device.getActiveErrors, [])
                error_count = len(active_errors) if active_errors else 0
                self.publish(f"{dev_prefix}/errors/count", error_count, force=is_heartbeat)
                if error_count > 0:
                    self.publish(f"{dev_prefix}/errors/active", json.dumps(active_errors), force=is_heartbeat)

                # Heating Circuits
                circuits = getattr(device, "circuits", [])
                for c_idx, circuit in enumerate(circuits):
                    c_prefix = f"{dev_prefix}/circuit_{c_idx}"

                    mode = self.safe_get(circuit.getActiveMode)
                    if mode is not None:
                        self.publish(f"{c_prefix}/mode", mode, force=is_heartbeat)

                    program = self.safe_get(circuit.getActiveProgram)
                    if program is not None:
                        self.publish(f"{c_prefix}/program", program, force=is_heartbeat)

                    normal_t = self.safe_get(circuit.getNormalTargetTemperature)
                    if normal_t is not None:
                        self.publish(f"{c_prefix}/normal_temperature", normal_t, force=is_heartbeat)

                    comfort_t = self.safe_get(circuit.getComfortTargetTemperature)
                    if comfort_t is not None:
                        self.publish(f"{c_prefix}/comfort_temperature", comfort_t, force=is_heartbeat)

                    reduced_t = self.safe_get(circuit.getReducedTargetTemperature)
                    if reduced_t is not None:
                        self.publish(f"{c_prefix}/reduced_temperature", reduced_t, force=is_heartbeat)

                    slope = self.safe_get(circuit.getHeatingCurveSlope)
                    if slope is not None:
                        self.publish(f"{c_prefix}/heating_curve_slope", slope, force=is_heartbeat)

                    shift = self.safe_get(circuit.getHeatingCurveShift)
                    if shift is not None:
                        self.publish(f"{c_prefix}/heating_curve_shift", shift, force=is_heartbeat)

                    c_supply = self.safe_get(circuit.getSupplyTemperature)
                    if c_supply is not None:
                        self.publish(f"{c_prefix}/supply_temperature", round(c_supply, 2), force=is_heartbeat)

            # Quota info
            calls_24h = self.quota.count_24h()
            rem_24h = self.quota.remaining_24h()
            self.publish(f"{self.base_topic}/quota/calls_24h", calls_24h, force=is_heartbeat)
            self.publish(f"{self.base_topic}/quota/remaining", rem_24h, force=is_heartbeat)
            self.publish(f"{self.base_topic}/last_poll", datetime.now().isoformat(), force=is_heartbeat)

            # Write stats to RAM file for Web-UI
            self.update_stats_file()

        except PyViCareRateLimitError:
            logger.warning("Viessmann rate limit hit! Increasing backoff.")
            time.sleep(120)
        except Exception as e:
            logger.error(f"Error polling Viessmann API: {e}", exc_info=True)

    def update_stats_file(self):
        try:
            stats = {
                "timestamp": datetime.now().isoformat(),
                "calls_24h": self.quota.count_24h(),
                "remaining_24h": self.quota.remaining_24h(),
                "devices_count": len(self.devices),
                "is_running": True,
            }
            with open(STATS_FILE, "w") as f:
                json.dump(stats, f)
        except Exception:
            pass

    def run(self):
        """Main daemon loop."""
        self.running = True
        logger.info("Vitoconnect daemon started.")

        # Write PID file
        try:
            PID_FILE.write_text(str(os.getpid()))
        except Exception as e:
            logger.warning(f"Could not write PID file: {e}")

        # Set up signal handling
        signal.signal(signal.SIGTERM, self.stop)
        signal.signal(signal.SIGINT, self.stop)

        if not self.load_config():
            logger.error("Configuration failed to load. Exiting.")
            return 1

        interval_raw = self.config.get("Cron", {}).get("interval", "60")
        try:
            if isinstance(interval_raw, str) and "min" in interval_raw:
                # e.g. "01min", "05min"
                mins = int(interval_raw.replace("min", ""))
                self.poll_interval = max(60, mins * 60)
            else:
                self.poll_interval = max(60, int(interval_raw))
        except Exception:
            self.poll_interval = 60

        logger.info(f"Polling interval set to {self.poll_interval}s")

        self.init_mqtt()
        if not self.init_vicare():
            logger.error("Initial Viessmann authentication failed. Will retry in main loop.")

        last_poll_time = 0

        while self.running:
            now = time.time()

            # Dynamic interval if quota is running low
            actual_interval = self.poll_interval
            if self.quota.is_quota_critical():
                actual_interval = max(300, self.poll_interval * 2)
                logger.warning(f"Quota is low! Throttling poll interval to {actual_interval}s")

            if self.force_poll or (now - last_poll_time >= actual_interval):
                self.force_poll = False
                last_poll_time = now
                if not self.vicare or not self.devices:
                    self.init_vicare()
                self.poll_and_publish()

            time.sleep(1)

        logger.info("Vitoconnect daemon terminating.")
        if self.mqtt_client:
            self.publish(f"{self.base_topic}/status", "offline", retain=True, force=True)
            self.mqtt_client.loop_stop()
            self.mqtt_client.disconnect()

        if PID_FILE.exists():
            try:
                PID_FILE.unlink()
            except Exception:
                pass
        return 0

    def stop(self, *args):
        logger.info("Received termination signal.")
        self.running = False


def main():
    daemon = VitoconnectDaemon(CONFIG_FILE)
    return daemon.run()


if __name__ == "__main__":
    sys.exit(main())
