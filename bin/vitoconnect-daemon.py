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

def resolve_plugin_path(env_var, subdir):
    val = os.environ.get(env_var)
    if val:
        p = Path(val)
        if p.name == PLUGIN_NAME:
            return p
        return p / PLUGIN_NAME
    return Path(LBHOMEDIR) / subdir / "plugins" / PLUGIN_NAME

LBPCONFIG = resolve_plugin_path("LBPCONFIG", "config")
LBPDATA = resolve_plugin_path("LBPDATA", "data")
LBPLOG = resolve_plugin_path("LBPLOG", "log")

def get_config_file():
    candidates = [
        LBPCONFIG / "config.json",
        Path(LBHOMEDIR) / "config" / "plugins" / PLUGIN_NAME / "config.json",
        Path("/opt/loxberry/config/plugins/Vitoconnect/config.json"),
        Path(__file__).resolve().parent.parent / "config" / "config.json",
    ]
    for c in candidates:
        if c.exists():
            return c
    return candidates[0]

CONFIG_FILE = get_config_file()
TOKEN_FILE = LBPDATA / "token.save"
PID_FILE = Path("/run/shm") / f"{PLUGIN_NAME}.pid"
STATS_FILE = Path("/run/shm") / f"{PLUGIN_NAME}_stats.json"

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

        host = (mqtt_cfg.get("host") or "").strip()
        user = (mqtt_cfg.get("user") or "").strip()
        password = mqtt_cfg.get("pass") or ""
        port = 1883

        # 1. Try official LoxBerry Python SDK if available (LoxBerry 4+)
        if not host or not user:
            try:
                # Add LoxBerry libs to sys.path if not present
                lb_pylib = Path(LBHOMEDIR) / "libs" / "pythonlib"
                if lb_pylib.exists() and str(lb_pylib) not in sys.path:
                    sys.path.insert(0, str(lb_pylib))
                from loxberry import io as lb_io
                cred = lb_io.mqtt_connectiondetails()
                if cred:
                    if not host and cred.get("brokerhost"):
                        host = str(cred.get("brokerhost")).strip()
                    if cred.get("brokerport"):
                        try:
                            port = int(cred.get("brokerport"))
                        except ValueError:
                            pass
                    if not user and cred.get("brokeruser"):
                        user = str(cred.get("brokeruser")).strip()
                    if not password and cred.get("brokerpass"):
                        password = str(cred.get("brokerpass"))
                    logger.info(f"Loaded MQTT settings via LoxBerry SDK (loxberry.io) for user '{user}'")
            except Exception as e:
                logger.debug(f"loxberry.io SDK not available or failed: {e}")

        # 2. Auto-detect LoxBerry MQTT settings from configuration files if host or credentials are empty
        if not host or not user:
            candidates = [
                # LoxBerry 3 / 4 Core: /opt/loxberry/config/system/general.json
                Path(LBHOMEDIR) / "config" / "system" / "general.json",
                Path("/opt/loxberry/config/system/general.json"),
                # LoxBerry system MQTT Gateway config
                Path(LBHOMEDIR) / "config" / "system" / "mqttgateway.json",
                Path("/opt/loxberry/config/system/mqttgateway.json"),
                # Shared memory cache
                Path("/dev/shm/mqttgateway.json"),
                Path("/run/shm/mqttgateway.json"),
                # Legacy plugin location
                Path(LBHOMEDIR) / "config" / "plugins" / "mqttgateway" / "mqtt.json",
            ]
            for cand in candidates:
                if cand.exists():
                    try:
                        with open(cand, "r", encoding="utf-8") as f:
                            data = json.load(f)

                        # 1. Check LoxBerry system general.json structure: { "Mqtt": { "Brokerhost", "Brokeruser", ... } }
                        mqtt_sec = data.get("Mqtt") or data.get("mqtt")
                        if isinstance(mqtt_sec, dict):
                            det_host = mqtt_sec.get("Brokerhost") or mqtt_sec.get("brokerhost") or mqtt_sec.get("Brokeraddress")
                            det_port = mqtt_sec.get("Brokerport") or mqtt_sec.get("brokerport")
                            det_user = mqtt_sec.get("Brokeruser") or mqtt_sec.get("brokeruser")
                            det_pass = mqtt_sec.get("Brokerpass") or mqtt_sec.get("brokerpass")
                            if det_host and not host:
                                host = str(det_host).strip()
                            if det_port:
                                try:
                                    port = int(det_port)
                                except ValueError:
                                    pass
                            if det_user and not user:
                                user = str(det_user).strip()
                            if det_pass and not password:
                                password = str(det_pass)
                            if user:
                                logger.info(f"Detected LoxBerry MQTT credentials from {cand} for user '{user}'")
                                break

                        # 2. Check mqttgateway.json structure: { "Main": { ... } } or root keys
                        main_sec = data.get("Main", data)
                        det_host = main_sec.get("brokeraddress") or main_sec.get("brokerhost")
                        det_port = main_sec.get("brokerport")
                        det_user = main_sec.get("brokeruser") or main_sec.get("user")
                        det_pass = main_sec.get("brokerpass") or main_sec.get("pass")
                        if det_host and not host:
                            host = str(det_host).strip()
                        if det_port:
                            try:
                                port = int(det_port)
                            except ValueError:
                                pass
                        if det_user and not user:
                            user = str(det_user).strip()
                        if det_pass and not password:
                            password = str(det_pass)
                        if user:
                            logger.info(f"Detected LoxBerry MQTT credentials from {cand} for user '{user}'")
                            break
                    except Exception as e:
                        logger.warning(f"Could not read MQTT config from {cand}: {e}")

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

        user = (self.config.get("user") or "").strip()
        password = (self.config.get("pass") or "").strip()
        client_id = (self.config.get("apikey") or "").strip()

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
            # Retrieve devices from PyViCare (devices attribute contains PyViCareDeviceConfig objects)
            raw_devices = getattr(self.vicare, "devices", None)
            if raw_devices is None and hasattr(self.vicare, "getDevices"):
                raw_devices = self.vicare.getDevices()
            if not raw_devices:
                raw_devices = []

            # Convert each PyViCareDeviceConfig to a typed device (e.g. HeatPump, GazBoiler) via asAutoDetectDevice()
            self.devices = []
            for d in raw_devices:
                dev_model = getattr(d, "device_model", getattr(d, "getModel", lambda: "Unknown")())
                dev_type = getattr(d, "device_type", getattr(d, "getDeviceType", lambda: "")())
                if hasattr(d, "asAutoDetectDevice"):
                    try:
                        typed_dev = d.asAutoDetectDevice()
                        self.devices.append(typed_dev)
                        logger.info(f"Initialized device: {dev_model} ({dev_type}) -> {type(typed_dev).__name__}")
                    except Exception as ex:
                        logger.warning(f"Could not auto-detect typed device for {dev_model}: {ex}. Using raw device.")
                        self.devices.append(d)
                else:
                    self.devices.append(d)

            logger.info(f"Connected to Viessmann API. Found {len(self.devices)} device(s).")
            return True
        except PyViCareRateLimitError:
            logger.error("Viessmann API rate limit reached! Pausing requests.")
            return False
        except Exception as e:
            err_type = type(e).__name__
            if "InvalidCredentials" in err_type:
                logger.error(
                    f"Viessmann API Authentication failed ({err_type})! "
                    "Wichtig im Viessmann Developer Portal (developer.viessmann.com):\n"
                    "1. 'Redirect URIs' muss exakt lauten: vicare://oauth-callback/everest\n"
                    "2. 'Google reCAPTCHA' muss auf 'Disabled' stehen!\n"
                    "3. Client-ID, E-Mail und Passwort auf Tippfehler prüfen."
                )
                # Remove stale token file if it exists so next attempt starts clean
                if TOKEN_FILE.exists():
                    try:
                        TOKEN_FILE.unlink()
                        logger.info(f"Removed stale token file: {TOKEN_FILE}")
                    except Exception:
                        pass
            else:
                logger.error(f"Failed to connect to Viessmann API ({err_type}): {e}", exc_info=True)
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

        # Support paho-mqtt v2 while maintaining compatibility with v1
        try:
            if hasattr(mqtt, "CallbackAPIVersion"):
                self.mqtt_client = mqtt.Client(
                    callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
                    client_id=client_id,
                )
            else:
                self.mqtt_client = mqtt.Client(client_id=client_id)
        except Exception as e:
            logger.warning(f"MQTT client creation fallback: {e}")
            self.mqtt_client = mqtt.Client(client_id=client_id)

        user = settings.get("user")
        pwd = settings.get("password")
        if user:
            self.mqtt_client.username_pw_set(user, pwd)
            logger.info(f"MQTT using authentication for user '{user}'")
        else:
            logger.info("MQTT connecting without authentication (anonymous)")

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

    def _on_mqtt_connect(self, client, userdata, flags, rc, properties=None):
        rc_code = getattr(rc, "value", rc)
        if rc_code == 0:
            logger.info("Connected to MQTT Broker.")
            set_topic = f"{self.base_topic}/set/#"
            client.subscribe(set_topic)
            client.subscribe(f"{self.base_topic}/poll")
            logger.info(f"Subscribed to command topics: {set_topic}")
            self.publish(f"{self.base_topic}/status", "online", retain=True, force=True)
        else:
            err_hints = {
                1: "Connection refused: incorrect protocol version",
                2: "Connection refused: invalid client identifier",
                3: "Connection refused: server unavailable",
                4: "Connection refused: bad username or password",
                5: "Connection refused: not authorized (Bitte MQTT Gateway Benutzer/Passwort prüfen)",
                128: "Unspecified error",
                134: "Bad user name or password",
                135: "Not authorized (MQTT Broker verweigert Zugriff ohne gültige Zugangsdaten)",
            }
            hint = err_hints.get(rc_code, f"Return Code {rc_code}")
            logger.error(f"MQTT connect failed: {hint} (rc={rc_code})")
            if rc_code in [4, 5, 134, 135]:
                # Stop automatic reconnect loop hammering when credentials are unauthorized
                try:
                    client.disconnect()
                except Exception:
                    pass

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

        # Pick the device that has circuits or heating controls (fallback to first device)
        device = self.devices[0]
        for d in self.devices:
            if getattr(d, "circuits", None) or hasattr(d, "setDomesticHotWaterTargetTemperature"):
                device = d
                break
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

            live_datapoints = {}
            ts_now = datetime.now().isoformat()

            def record(topic, val, unit="", desc="", writable=False, set_topic=None):
                if val is not None:
                    live_datapoints[topic] = {
                        "topic": topic,
                        "value": val,
                        "unit": unit,
                        "description": desc,
                        "writable": writable,
                        "set_topic": set_topic,
                        "timestamp": ts_now,
                    }
                    self.publish(topic, val, force=is_heartbeat)

            for dev_idx, device in enumerate(self.devices):
                dev_prefix = f"{self.base_topic}"
                if len(self.devices) > 1:
                    dev_prefix += f"/device_{dev_idx}"

                # Device General Info
                model = self.safe_get(device.getModelId, "Unknown")
                is_online = self.safe_get(device.isOnline, True)
                serial = self.safe_get(device.getSerial, "")

                record(f"{dev_prefix}/model", model, desc="Gerätemodell")
                record(f"{dev_prefix}/online", "1" if is_online else "0", desc="Online Status")
                if serial:
                    record(f"{dev_prefix}/serial", serial, desc="Seriennummer")

                # Core Temperatures
                out_temp = self.safe_get(device.getOutsideTemperature)
                if out_temp is not None:
                    record(f"{dev_prefix}/temperature/outside", round(out_temp, 2), "°C", "Außentemperatur")

                boiler_temp = self.safe_get(device.getBoilerTemperature)
                if boiler_temp is not None:
                    record(f"{dev_prefix}/temperature/boiler", round(boiler_temp, 2), "°C", "Kesseltemperatur")

                supply_temp = self.safe_get(device.getSupplyTemperature)
                if supply_temp is not None:
                    record(f"{dev_prefix}/temperature/supply", round(supply_temp, 2), "°C", "Vorlauftemperatur")

                # Burner / Compressor
                burner_active = self.safe_get(device.getBurnerActive)
                if burner_active is not None:
                    record(f"{dev_prefix}/burner/active", "1" if burner_active else "0", "", "Brenner aktiv")

                burner_hours = self.safe_get(device.getBurnerHours)
                if burner_hours is not None:
                    record(f"{dev_prefix}/burner/hours", burner_hours, "h", "Brenner-Betriebsstunden")

                burner_starts = self.safe_get(device.getBurnerStarts)
                if burner_starts is not None:
                    record(f"{dev_prefix}/burner/starts", burner_starts, "", "Brenner-Starts")

                burner_mod = self.safe_get(device.getBurnerModulation)
                if burner_mod is not None:
                    record(f"{dev_prefix}/burner/modulation", burner_mod, "%", "Brennermodulation")

                # Heat pump specific
                comp_active = self.safe_get(device.getCompressorActive)
                if comp_active is not None:
                    record(f"{dev_prefix}/compressor/active", "1" if comp_active else "0", "", "Kompressor aktiv")

                comp_hours = self.safe_get(device.getCompressorHours)
                if comp_hours is not None:
                    record(f"{dev_prefix}/compressor/hours", comp_hours, "h", "Kompressor-Betriebsstunden")

                # Domestic Hot Water (DHW)
                dhw_temp = self.safe_get(device.getDomesticHotWaterStorageTemperature)
                if dhw_temp is not None:
                    record(f"{dev_prefix}/dhw/temperature", round(dhw_temp, 2), "°C", "Warmwassertemperatur (Speicher)")

                dhw_target = self.safe_get(device.getDomesticHotWaterTargetTemperature)
                if dhw_target is not None:
                    record(f"{dev_prefix}/dhw/target_temperature", round(dhw_target, 2), "°C", "Warmwasser-Sollwert", writable=True, set_topic=f"{self.base_topic}/set/dhw/target_temperature")

                dhw_pump = self.safe_get(device.getDomesticHotWaterCirculationPump)
                if dhw_pump is not None:
                    record(f"{dev_prefix}/dhw/circulation_pump", "1" if dhw_pump else "0", "", "Warmwasser-Zirkulationspumpe")

                dhw_charge = self.safe_get(device.getDomesticHotWaterOneTimeCharge)
                if dhw_charge is not None:
                    record(f"{dev_prefix}/dhw/one_time_charge", "1" if dhw_charge else "0", "", "Einmalige Warmwasserbereitung", writable=True, set_topic=f"{self.base_topic}/set/dhw/one_time_charge")

                # Errors
                active_errors = self.safe_get(device.getActiveErrors, [])
                error_count = len(active_errors) if active_errors else 0
                record(f"{dev_prefix}/errors/count", error_count, "", "Anzahl aktiver Fehler")
                if error_count > 0:
                    record(f"{dev_prefix}/errors/active", json.dumps(active_errors), "", "Aktive Fehlermeldungen")

                # Heating Circuits
                circuits = getattr(device, "circuits", [])
                for c_idx, circuit in enumerate(circuits):
                    c_prefix = f"{dev_prefix}/circuit_{c_idx}"

                    mode = self.safe_get(circuit.getActiveMode)
                    if mode is not None:
                        record(f"{c_prefix}/mode", mode, "", f"Heizkreis {c_idx}: Betriebsmodus", writable=True, set_topic=f"{self.base_topic}/set/circuit_{c_idx}/mode")

                    program = self.safe_get(circuit.getActiveProgram)
                    if program is not None:
                        record(f"{c_prefix}/program", program, "", f"Heizkreis {c_idx}: Aktives Programm")

                    normal_t = self.safe_get(circuit.getNormalTargetTemperature)
                    if normal_t is not None:
                        record(f"{c_prefix}/normal_temperature", normal_t, "°C", f"Heizkreis {c_idx}: Normal-Sollwert", writable=True, set_topic=f"{self.base_topic}/set/circuit_{c_idx}/normal_temperature")

                    comfort_t = self.safe_get(circuit.getComfortTargetTemperature)
                    if comfort_t is not None:
                        record(f"{c_prefix}/comfort_temperature", comfort_t, "°C", f"Heizkreis {c_idx}: Komfort-Sollwert", writable=True, set_topic=f"{self.base_topic}/set/circuit_{c_idx}/comfort_temperature")

                    reduced_t = self.safe_get(circuit.getReducedTargetTemperature)
                    if reduced_t is not None:
                        record(f"{c_prefix}/reduced_temperature", reduced_t, "°C", f"Heizkreis {c_idx}: Absenk-Sollwert", writable=True, set_topic=f"{self.base_topic}/set/circuit_{c_idx}/reduced_temperature")

                    slope = self.safe_get(circuit.getHeatingCurveSlope)
                    if slope is not None:
                        record(f"{c_prefix}/heating_curve_slope", slope, "", f"Heizkreis {c_idx}: Heizkennlinie Neigung")

                    shift = self.safe_get(circuit.getHeatingCurveShift)
                    if shift is not None:
                        record(f"{c_prefix}/heating_curve_shift", shift, "K", f"Heizkreis {c_idx}: Heizkennlinie Niveau")

                    c_supply = self.safe_get(circuit.getSupplyTemperature)
                    if c_supply is not None:
                        record(f"{c_prefix}/supply_temperature", round(c_supply, 2), "°C", f"Heizkreis {c_idx}: Vorlauftemperatur")

            # Quota info
            calls_24h = self.quota.count_24h()
            rem_24h = self.quota.remaining_24h()
            record(f"{self.base_topic}/quota/calls_24h", calls_24h, "", "API-Aufrufe (24h)")
            record(f"{self.base_topic}/quota/remaining", rem_24h, "", "Verbleibende API-Aufrufe")
            record(f"{self.base_topic}/last_poll", ts_now, "", "Letzter Abfrage-Zeitstempel")

            # Save live values file to RAM for Web-UI
            try:
                values_file = Path("/run/shm") / f"{PLUGIN_NAME}_values.json"
                if not values_file.parent.exists():
                    values_file = Path("/tmp") / f"{PLUGIN_NAME}_values.json"
                with open(values_file, "w", encoding="utf-8") as vf:
                    json.dump(live_datapoints, vf, indent=2)
            except Exception as e:
                logger.debug(f"Could not write live values file: {e}")

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
            logger.warning(f"Configuration not found or empty at {self.config_path}. Waiting for user configuration...")
        else:
            interval_raw = self.config.get("Cron", {}).get("interval", "60")
            try:
                if isinstance(interval_raw, str) and "min" in interval_raw:
                    mins = int(interval_raw.replace("min", ""))
                    self.poll_interval = max(60, mins * 60)
                else:
                    self.poll_interval = max(60, int(interval_raw))
            except Exception:
                self.poll_interval = 60

            logger.info(f"Polling interval set to {self.poll_interval}s")
            self.init_mqtt()
            if not self.init_vicare():
                logger.warning("Initial Viessmann authentication failed. Will retry in main loop.")

        self.update_stats_file()
        last_poll_time = 0
        last_config_check = 0
        last_auth_attempt = 0
        auth_backoff = 300  # wait 5 min after auth failure before retrying Viessmann
        last_cfg_mtime = self.config_path.stat().st_mtime if self.config_path.exists() else 0

        while self.running:
            now = time.time()

            # Check if config file was modified in Web-UI
            if now - last_config_check >= 5:
                last_config_check = now
                if self.config_path.exists():
                    try:
                        curr_mtime = self.config_path.stat().st_mtime
                        if curr_mtime != last_cfg_mtime:
                            last_cfg_mtime = curr_mtime
                            logger.info("Configuration change detected on disk. Reloading...")
                            self.load_config()
                            # Reset auth backoff on new config
                            last_auth_attempt = 0
                            if not self.mqtt_client or not self.mqtt_client.is_connected():
                                self.init_mqtt()
                            self.init_vicare()
                    except Exception as e:
                        logger.warning(f"Error checking config file modification: {e}")

            # If credentials are not yet available, wait
            has_creds = bool(self.config and self.config.get("user") and self.config.get("apikey"))
            if not has_creds:
                time.sleep(1)
                continue

            # Viessmann authentication retry with backoff if not connected
            if not self.vicare or not self.devices:
                if now - last_auth_attempt >= auth_backoff:
                    last_auth_attempt = now
                    logger.info("Retrying Viessmann API authentication...")
                    self.init_vicare()
                else:
                    time.sleep(1)
                    continue

            if not self.devices:
                time.sleep(1)
                continue

            # Dynamic interval if quota is running low
            actual_interval = self.poll_interval
            if self.quota.is_quota_critical():
                actual_interval = max(300, self.poll_interval * 2)
                logger.warning(f"Quota is low! Throttling poll interval to {actual_interval}s")

            if self.force_poll or (now - last_poll_time >= actual_interval):
                self.force_poll = False
                last_poll_time = now
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
