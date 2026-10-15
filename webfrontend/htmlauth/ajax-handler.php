<?php

require_once "loxberry_system.php";
require_once "defines.php";

$action = isset($_GET["action"]) ? $_GET["action"] : "";

$plugin_dir = defined("LBPPLUGINDIR") ? LBPPLUGINDIR : "Vitoconnect";
$bin_dir = defined("LBPBINDIR") ? LBPBINDIR : LBHOMEDIR . "/bin/plugins/" . $plugin_dir;
$data_dir = defined("LBPDATADIR") ? LBPDATADIR : LBHOMEDIR . "/data/plugins/" . $plugin_dir;
$daemon_script = LBHOMEDIR . "/system/daemons/plugins/" . $plugin_dir;
if (!file_exists($daemon_script)) {
    $daemon_script = LBHOMEDIR . "/data/plugins/" . $plugin_dir . "/../../daemon/daemon.sh";
}

$venv_python = $data_dir . "/venv/bin/python3";
$manager_script = $bin_dir . "/pyvicare-manager.py";

// Helper: run pyvicare-manager
function run_manager($arg) {
    global $venv_python, $manager_script, $data_dir;
    $python = file_exists($venv_python) ? $venv_python : "python3";
    $venv_arg = " --venv " . escapeshellarg($data_dir . "/venv");
    $cmd = escapeshellcmd($python) . " " . escapeshellarg($manager_script) . " " . $arg . $venv_arg . " 2>&1";
    $output = shell_exec($cmd);
    return $output;
}

// 1. Save Configuration
if ($action == "saveconfig") {
    $data = array();
    foreach ($_POST as $key => $value) {
        $data = generateNew($data, explode("_", $key), 0, $value);
    }

    // Explicitly handle checkboxes so they are stored as booleans (true/false)
    if (!isset($data["MQTT"])) {
        $data["MQTT"] = array();
    }
    $data["MQTT"]["enabled"] = isset($_POST["MQTT_enabled"]) && in_array(strtolower($_POST["MQTT_enabled"]), ["on", "1", "true", "yes"]);
    $data["MQTT"]["use_gateway"] = isset($_POST["MQTT_use_gateway"]) && in_array(strtolower($_POST["MQTT_use_gateway"]), ["on", "1", "true", "yes"]);

    $jsonstr = json_encode($data, JSON_PRETTY_PRINT);
    if ($jsonstr) {
        if (file_put_contents(CONFIGFILE, $jsonstr) === false) {
            sendresponse(500, "application/json", json_encode(["error" => "Could not write config file"]));
        } else {
            // Clean up obsolete cron files from v1.x
            $intervals = ["01min", "03min", "05min", "10min", "15min", "30min", "hourly"];
            foreach ($intervals as $inv) {
                $cron_file = LBHOMEDIR . "/system/cron/cron." . $inv . "/" . $plugin_dir;
                if (file_exists($cron_file)) {
                    @unlink($cron_file);
                }
            }

            // Trigger daemon restart if daemon script exists
            $daemon_run = LBHOMEDIR . "/system/daemons/plugins/" . $plugin_dir;
            if (!file_exists($daemon_run)) {
                $daemon_run = dirname(__DIR__, 2) . "/daemon/daemon.sh";
            }
            if (file_exists($daemon_run)) {
                shell_exec("/bin/bash " . escapeshellarg($daemon_run) . " restart > /dev/null 2>&1 &");
            }

            sendresponse(200, "application/json", file_get_contents(CONFIGFILE));
        }
    } else {
        sendresponse(500, "application/json", json_encode(["error" => "Submitted data is not valid JSON"]));
    }
    exit(1);
}

// 2. PyViCare Status Check
if ($action == "pyvicare_status") {
    $out = run_manager("--info");
    if ($out) {
        $json = json_decode($out, true);
        if ($json !== null) {
            sendresponse(200, "application/json", $out);
        }
    }
    sendresponse(500, "application/json", json_encode(["error" => "Could not determine PyViCare status", "raw" => $out]));
}

// 3. PyViCare Upgrade
if ($action == "pyvicare_upgrade") {
    $out = run_manager("--upgrade");
    if ($out) {
        // Also restart daemon after upgrade
        $daemon_run = LBHOMEDIR . "/system/daemons/plugins/" . $plugin_dir;
        if (!file_exists($daemon_run)) {
            $daemon_run = dirname(__DIR__, 2) . "/daemon/daemon.sh";
        }
        if (file_exists($daemon_run)) {
            shell_exec("/bin/bash " . escapeshellarg($daemon_run) . " restart > /dev/null 2>&1 &");
        }
        sendresponse(200, "application/json", $out);
    }
    sendresponse(500, "application/json", json_encode(["error" => "Upgrade failed", "raw" => $out]));
}

// 4. Daemon Control (start, stop, restart, status)
if ($action == "daemon_control") {
    $cmd = isset($_GET["cmd"]) ? $_GET["cmd"] : "status";
    if (!in_array($cmd, ["start", "stop", "restart", "status"])) {
        sendresponse(400, "application/json", json_encode(["error" => "Invalid command"]));
    }

    $daemon_run = LBHOMEDIR . "/system/daemons/plugins/" . $plugin_dir;
    if (!file_exists($daemon_run)) {
        // Fallback relative path in repo
        $daemon_run = dirname(__DIR__, 2) . "/daemon/daemon.sh";
    }

    $res = shell_exec("/bin/bash " . escapeshellarg($daemon_run) . " " . escapeshellarg($cmd) . " 2>&1");
    sendresponse(200, "application/json", json_encode(["cmd" => $cmd, "output" => trim($res)]));
}

// 5. Live Stats
if ($action == "get_stats") {
    $stats_file = file_exists(STATSFILE) ? STATSFILE : "/tmp/Vitoconnect_stats.json";
    if (file_exists($stats_file)) {
        sendresponse(200, "application/json", file_get_contents($stats_file));
    }
    sendresponse(200, "application/json", json_encode(["is_running" => false, "calls_24h" => 0, "remaining_24h" => 1450]));
}

// 6. Live Datapoints
if ($action == "get_live_values") {
    $values_file = "/run/shm/" . $plugin_dir . "_values.json";
    if (!file_exists($values_file)) {
        $values_file = "/tmp/" . $plugin_dir . "_values.json";
    }
    if (file_exists($values_file)) {
        sendresponse(200, "application/json", file_get_contents($values_file));
    }
    sendresponse(200, "application/json", json_encode([]));
}

// 7. Send Command to MQTT (for Testing from GUI)
if ($action == "send_command") {
    $target_topic = isset($_POST["topic"]) ? trim($_POST["topic"]) : (isset($_GET["topic"]) ? trim($_GET["topic"]) : "");
    $value = isset($_POST["value"]) ? trim($_POST["value"]) : (isset($_GET["value"]) ? trim($_GET["value"]) : "");

    if (empty($target_topic)) {
        sendresponse(400, "application/json", json_encode(["success" => false, "error" => "Topic is missing"]));
    }

    $res = vitoconnect_mqtt_publish($target_topic, $value);

    if (!empty($res["success"])) {
        sendresponse(200, "application/json", json_encode([
            "success" => true,
            "topic" => $target_topic,
            "value" => $value,
            "message" => "Befehl erfolgreich an MQTT gesendet ($target_topic = $value)"
        ]));
    } else {
        sendresponse(500, "application/json", json_encode([
            "success" => false,
            "error" => "Fehler beim Senden an MQTT: " . ($res["error"] ?? "Unbekannter Fehler")
        ]));
    }
}

// 8. Detailed Daemon Diagnostics & Live Log
if ($action == "get_daemon_diagnostics") {
    $log_dir = defined("LBPLOGDIR") ? LBPLOGDIR : LBHOMEDIR . "/log/plugins/" . $plugin_dir;
    $log_file = $log_dir . "/daemon.log";

    $pid_file = file_exists("/run/shm/" . $plugin_dir . ".pid") ? "/run/shm/" . $plugin_dir . ".pid" : "/tmp/" . $plugin_dir . ".pid";
    $is_running = false;
    $pid = null;

    if (file_exists($pid_file)) {
        $candidate_pid = trim(file_get_contents($pid_file));
        if (is_numeric($candidate_pid)) {
            // Check if process is alive
            $chk = shell_exec("ps -p " . escapeshellarg($candidate_pid) . " -o pid= 2>/dev/null");
            if (!empty(trim($chk))) {
                $is_running = true;
                $pid = (int)$candidate_pid;
            }
        }
    }

    $reason_code = "OK";
    $reason_text = "Dienst läuft einwandfrei.";
    $last_error = null;

    // Read last lines of daemon.log
    $log_content = "";
    if (file_exists($log_file)) {
        $lines = file($log_file);
        if ($lines !== false) {
            $slice = array_slice($lines, -100);
            $log_content = implode("", $slice);

            // Scan backwards for last error or warning
            for ($i = count($lines) - 1; $i >= max(0, count($lines) - 50); $i--) {
                $line = $lines[$i];
                if (stripos($line, "[ERROR]") !== false || stripos($line, "Traceback") !== false || stripos($line, "Error:") !== false) {
                    $last_error = trim($line);
                    break;
                }
            }
        }
    }

    if (!$is_running) {
        // Diagnose why not running
        if (!file_exists(CONFIGFILE)) {
            $reason_code = "NO_CONFIG";
            $reason_text = "Konfigurationsdatei fehlt. Bitte im Tab 'Settings' anlegen und speichern.";
        } else {
            $cfg = json_decode(file_get_contents(CONFIGFILE), true);
            if (empty($cfg["user"]) || empty($cfg["pass"]) || empty($cfg["apikey"])) {
                $reason_code = "MISSING_CREDENTIALS";
                $reason_text = "Viessmann Zugangsdaten unvollständig: Benutzername, Passwort oder Client-ID fehlen in den Einstellungen.";
            } elseif (!file_exists($data_dir . "/venv/bin/python3")) {
                $reason_code = "VENV_MISSING";
                $reason_text = "Python Virtual Environment (venv) fehlt noch. Bitte auf 'PyViCare aktualisieren' klicken oder Plugin neu installieren.";
            } elseif ($last_error) {
                $reason_code = "CRASH_OR_ERROR";
                if (stripos($last_error, "InvalidCredentials") !== false || stripos($last_error, "Authentication failed") !== false) {
                    $reason_text = "Viessmann-Anmeldung fehlgeschlagen! Bitte E-Mail, Passwort und Client-ID prüfen. Im Viessmann Developer Portal muss die Redirect URI exakt 'vicare://oauth-callback/everest' lauten und Google reCAPTCHA deaktiviert sein.";
                } elseif (stripos($last_error, "return code 5") !== false || stripos($last_error, "rc=135") !== false || stripos($last_error, "not authorized") !== false) {
                    $reason_text = "MQTT-Verbindung abgewiesen (Nicht autorisiert / Return Code 5 bzw. 135). Der Broker verlangt Benutzer/Passwort. Bitte LoxBerry MQTT Gateway Einstellungen prüfen.";
                } else {
                    $reason_text = "Dienst hat einen Fehler gemeldet: " . $last_error;
                }
            } else {
                $reason_code = "STOPPED_MANUALLY";
                $reason_text = "Dienst ist momentan gestoppt (oder wurde noch nicht gestartet).";
            }
        }
    } else {
        // Daemon is running, but might have runtime connection issues
        if ($last_error) {
            if (stripos($last_error, "InvalidCredentials") !== false || stripos($last_error, "Authentication failed") !== false) {
                $reason_code = "AUTH_ERROR";
                $reason_text = "Dienst läuft, aber Viessmann-Anmeldung fehlgeschlagen! Bitte Redirect URI ('vicare://oauth-callback/everest') und reCAPTCHA im Developer Portal prüfen.";
            } elseif (stripos($last_error, "return code 5") !== false || stripos($last_error, "rc=135") !== false || stripos($last_error, "not authorized") !== false) {
                $reason_code = "MQTT_AUTH_ERROR";
                $reason_text = "Dienst läuft, aber MQTT-Broker verweigert Verbindung (Return Code 5 / 135: nicht autorisiert). MQTT Gateway Zugangsdaten prüfen.";
            }
        }
    }

    sendresponse(200, "application/json", json_encode([
        "is_running" => $is_running,
        "pid" => $pid,
        "reason_code" => $reason_code,
        "reason_text" => $reason_text,
        "last_error" => $last_error,
        "log_content" => $log_content,
        "log_file" => $log_file,
    ]));
}

// 9. Clear Daemon Log
if ($action == "clear_log") {
    $log_dir = defined("LBPLOGDIR") ? LBPLOGDIR : LBHOMEDIR . "/log/plugins/" . $plugin_dir;
    $log_file = $log_dir . "/daemon.log";
    if (file_exists($log_file)) {
        file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] [INFO] Log cleared via Web-UI\n");
    }
    sendresponse(200, "application/json", json_encode(["success" => true]));
}

sendresponse(501, "application/json", json_encode(["error" => "No supported action given."]));
exit(1);

function generateNew($array, $keys, $currentIndex, $value) {
    if ($currentIndex == count($keys) - 1) {
        $array[$keys[$currentIndex]] = $value;
    } else {
        if (!isset($array[$keys[$currentIndex]])) {
            $array[$keys[$currentIndex]] = array();
        }
        $array[$keys[$currentIndex]] = generateNew($array[$keys[$currentIndex]], $keys, $currentIndex + 1, $value);
    }
    return $array;
}

function sendresponse($httpstatus, $contenttype, $response = null) {
    $codes = array(
        200 => "OK",
        204 => "NO CONTENT",
        304 => "NOT MODIFIED",
        400 => "BAD REQUEST",
        404 => "NOT FOUND",
        405 => "METHOD NOT ALLOWED",
        500 => "INTERNAL SERVER ERROR",
        501 => "NOT IMPLEMENTED"
    );

    header($_SERVER["SERVER_PROTOCOL"] . " " . $httpstatus . " " . $codes[$httpstatus]);
    header("Content-Type: " . $contenttype);

    if ($response) {
        echo $response;
    }
    exit(0);
}
