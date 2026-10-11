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
            if (file_exists($daemon_run)) {
                shell_exec(escapeshellcmd($daemon_run) . " restart > /dev/null 2>&1 &");
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
        if (file_exists($daemon_run)) {
            shell_exec(escapeshellcmd($daemon_run) . " restart > /dev/null 2>&1 &");
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

    $res = shell_exec(escapeshellcmd($daemon_run) . " " . escapeshellarg($cmd) . " 2>&1");
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
    require_once "loxberry_io.php";
    $target_topic = isset($_POST["topic"]) ? trim($_POST["topic"]) : (isset($_GET["topic"]) ? trim($_GET["topic"]) : "");
    $value = isset($_POST["value"]) ? trim($_POST["value"]) : (isset($_GET["value"]) ? trim($_GET["value"]) : "");

    if (empty($target_topic)) {
        sendresponse(400, "application/json", json_encode(["error" => "Topic is missing"]));
    }

    if (function_exists("mqtt_publish")) {
        $pub_res = mqtt_publish($target_topic, $value);
        sendresponse(200, "application/json", json_encode([
            "success" => true,
            "topic" => $target_topic,
            "value" => $value,
            "message" => "Befehl erfolgreich an MQTT übergeben"
        ]));
    } else {
        // Fallback: mosquitto_pub CLI
        $cmd = "mosquitto_pub -t " . escapeshellarg($target_topic) . " -m " . escapeshellarg($value) . " 2>&1";
        shell_exec($cmd);
        sendresponse(200, "application/json", json_encode([
            "success" => true,
            "topic" => $target_topic,
            "value" => $value,
            "message" => "Befehl über mosquitto_pub übergeben"
        ]));
    }
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
