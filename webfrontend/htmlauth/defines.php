<?php

define ("CONFIGFILE", "$lbpconfigdir/config.json");
define ("TMPPREFIX", "/run/shm/${lbpplugindir}_");
define ("LOGINFILE", TMPPREFIX . "sessiondata.json");
define ("INSTALLDATA", TMPPREFIX . "installdata.json");
define ("STATSFILE", "/run/shm/Vitoconnect_stats.json");

// The Navigation Bar
$navbar[1]['Name'] = "Settings & Service";
$navbar[1]['URL'] = 'index.php';
 
$navbar[2]['Name'] = "Status & Live Data";
$navbar[2]['URL'] = 'showdata.php';

$navbar[3]['Name'] = "Log";
$navbar[3]['URL'] = 'log.php';

/**
 * Robust MQTT publisher for LoxBerry webfrontend
 */
function vitoconnect_mqtt_publish($target_topic, $value) {
    global $lbpdatadir;
    require_once "loxberry_io.php";
    require_once "loxberry_system.php";

    $brokerhost = "127.0.0.1";
    $brokerport = 1883;
    $brokeruser = "";
    $brokerpass = "";

    if (function_exists("mqtt_connectiondetails")) {
        $creds = mqtt_connectiondetails();
        if (is_array($creds)) {
            if (!empty($creds["brokerhost"])) $brokerhost = $creds["brokerhost"];
            if (!empty($creds["brokerport"])) $brokerport = (int)$creds["brokerport"];
            if (!empty($creds["brokeruser"])) $brokeruser = $creds["brokeruser"];
            if (!empty($creds["brokerpass"])) $brokerpass = $creds["brokerpass"];
        }
    }

    $data_dir = defined("LBPDATADIR") ? LBPDATADIR : (isset($lbpdatadir) ? $lbpdatadir : LBHOMEDIR . "/data/plugins/vitoconnect");
    $venv_python = $data_dir . "/venv/bin/python3";

    // Method 1: Python venv with paho-mqtt
    if (file_exists($venv_python)) {
        $py_script = 'import sys, paho.mqtt.publish as publish
auth = {"username": sys.argv[3], "password": sys.argv[4]} if sys.argv[3] else None
publish.single(sys.argv[5], sys.argv[6], hostname=sys.argv[1], port=int(sys.argv[2]), auth=auth)
print("OK")';
        $cmd = escapeshellcmd($venv_python) . " -c " . escapeshellarg($py_script) . " " .
               escapeshellarg($brokerhost) . " " .
               escapeshellarg((string)$brokerport) . " " .
               escapeshellarg($brokeruser) . " " .
               escapeshellarg($brokerpass) . " " .
               escapeshellarg($target_topic) . " " .
               escapeshellarg((string)$value) . " 2>&1";
        $out = trim(shell_exec($cmd) ?: "");
        if (strpos($out, "OK") !== false) {
            return ["success" => true, "output" => $out];
        }
    }

    // Method 2: mosquitto_pub CLI with credentials
    $cmd = "mosquitto_pub -h " . escapeshellarg($brokerhost) . " -p " . escapeshellarg((string)$brokerport);
    if (!empty($brokeruser)) {
        $cmd .= " -u " . escapeshellarg($brokeruser) . " -P " . escapeshellarg($brokerpass);
    }
    $cmd .= " -t " . escapeshellarg($target_topic) . " -m " . escapeshellarg((string)$value) . " 2>&1";
    $out = trim(shell_exec($cmd) ?: "");
    if (empty($out) || stripos($out, "error") === false) {
        return ["success" => true, "output" => $out];
    }

    // Method 3: phpMQTT if available
    $phpmqtt_path = LBHOMEDIR . "/libs/phpmqtt/phpMQTT.php";
    if (file_exists($phpmqtt_path)) {
        try {
            require_once $phpmqtt_path;
            $client_id = "Vitoconnect_PHP_" . uniqid();
            $mqtt = new Bluerhino\phpMQTT($brokerhost, $brokerport, $client_id);
            if ($mqtt->connect(true, NULL, $brokeruser, $brokerpass)) {
                $mqtt->publish($target_topic, (string)$value, 0, false);
                $mqtt->close();
                return ["success" => true, "output" => "via phpMQTT"];
            }
        } catch (Exception $e) {
            // continue
        }
    }

    return ["success" => false, "error" => "MQTT publish failed: " . $out];
}
