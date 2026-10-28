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
 * Robust MQTT publisher for LoxBerry webfrontend & legacy bridge
 */
function vitoconnect_mqtt_publish($target_topic, $value) {
    global $lbpdatadir, $lbpconfigdir;
    require_once "loxberry_io.php";
    require_once "loxberry_system.php";

    $brokerhost = "127.0.0.1";
    $brokerport = 1883;
    $brokeruser = "";
    $brokerpass = "";

    // 1. Try reading MQTT settings from Vitoconnect plugin config.json
    $cfg_file = defined("CONFIGFILE") ? CONFIGFILE : (isset($lbpconfigdir) ? "$lbpconfigdir/config.json" : "/opt/loxberry/config/plugins/Vitoconnect/config.json");
    if (!file_exists($cfg_file)) {
        $cfg_file = "/opt/loxberry/config/plugins/vitoconnect/config.json";
    }
    if (file_exists($cfg_file)) {
        $pcfg = json_decode(file_get_contents($cfg_file), true);
        if (is_array($pcfg) && !empty($pcfg["MQTT"])) {
            if (!empty($pcfg["MQTT"]["host"])) $brokerhost = trim($pcfg["MQTT"]["host"]);
            if (!empty($pcfg["MQTT"]["port"])) $brokerport = (int)$pcfg["MQTT"]["port"];
            if (!empty($pcfg["MQTT"]["user"])) $brokeruser = trim($pcfg["MQTT"]["user"]);
            if (!empty($pcfg["MQTT"]["pass"])) $brokerpass = (string)$pcfg["MQTT"]["pass"];
        }
    }

    // 2. Try official LoxBerry SDK function if available
    if (empty($brokeruser) && function_exists("mqtt_connectiondetails")) {
        $creds = mqtt_connectiondetails();
        if (is_array($creds)) {
            if (!empty($creds["brokerhost"]) && $brokerhost == "127.0.0.1") $brokerhost = trim($creds["brokerhost"]);
            if (!empty($creds["brokerport"])) $brokerport = (int)$creds["brokerport"];
            if (!empty($creds["brokeruser"])) $brokeruser = trim($creds["brokeruser"]);
            if (!empty($creds["brokerpass"])) $brokerpass = (string)$creds["brokerpass"];
        }
    }

    // 3. Fallback: inspect LoxBerry system MQTT configuration files
    if (empty($brokeruser)) {
        $sys_candidates = [
            "/opt/loxberry/config/system/general.json",
            (defined("LBHOMEDIR") ? LBHOMEDIR : "/opt/loxberry") . "/config/system/general.json",
            "/opt/loxberry/config/system/mqttgateway.json",
            "/run/shm/mqttgateway.json",
            "/dev/shm/mqttgateway.json",
            "/opt/loxberry/config/plugins/mqttgateway/mqtt.json",
        ];
        foreach ($sys_candidates as $cand) {
            if (file_exists($cand)) {
                $j = json_decode(file_get_contents($cand), true);
                if (is_array($j)) {
                    $m = isset($j["Mqtt"]) ? $j["Mqtt"] : (isset($j["mqtt"]) ? $j["mqtt"] : (isset($j["Main"]) ? $j["Main"] : null));
                    if (is_array($m)) {
                        if (empty($brokeruser) && !empty($m["Brokeruser"])) $brokeruser = trim($m["Brokeruser"]);
                        elseif (empty($brokeruser) && !empty($m["brokeruser"])) $brokeruser = trim($m["brokeruser"]);
                        elseif (empty($brokeruser) && !empty($m["user"])) $brokeruser = trim($m["user"]);

                        if (empty($brokerpass) && !empty($m["Brokerpass"])) $brokerpass = (string)$m["Brokerpass"];
                        elseif (empty($brokerpass) && !empty($m["brokerpass"])) $brokerpass = (string)$m["brokerpass"];
                        elseif (empty($brokerpass) && !empty($m["pass"])) $brokerpass = (string)$m["pass"];

                        if ($brokerhost == "127.0.0.1" && !empty($m["Brokerhost"])) $brokerhost = trim($m["Brokerhost"]);
                        elseif ($brokerhost == "127.0.0.1" && !empty($m["brokerhost"])) $brokerhost = trim($m["brokerhost"]);

                        if (!empty($brokeruser)) break;
                    }
                }
            }
        }
    }

    // Resolve venv python path (checking both capital 'Vitoconnect' and lowercase)
    $venv_candidates = [
        defined("LBPDATADIR") ? LBPDATADIR . "/venv/bin/python3" : null,
        isset($lbpdatadir) ? $lbpdatadir . "/venv/bin/python3" : null,
        (defined("LBHOMEDIR") ? LBHOMEDIR : "/opt/loxberry") . "/data/plugins/Vitoconnect/venv/bin/python3",
        (defined("LBHOMEDIR") ? LBHOMEDIR : "/opt/loxberry") . "/data/plugins/vitoconnect/venv/bin/python3",
        "/opt/loxberry/data/plugins/Vitoconnect/venv/bin/python3",
        "/opt/loxberry/data/plugins/vitoconnect/venv/bin/python3"
    ];
    $venv_python = null;
    foreach ($venv_candidates as $cand) {
        if (!empty($cand) && file_exists($cand)) {
            $venv_python = $cand;
            break;
        }
    }

    // Method 1: Python venv with paho-mqtt & base64 encoded payload (safe against quotes/newlines/JSON)
    if ($venv_python) {
        $b64_payload = base64_encode((string)$value);
        $py_script = 'import sys, base64, paho.mqtt.publish as publish
auth = {"username": sys.argv[3], "password": sys.argv[4]} if sys.argv[3] else None
payload = base64.b64decode(sys.argv[6].encode("ascii"))
publish.single(sys.argv[5], payload, hostname=sys.argv[1], port=int(sys.argv[2]), auth=auth)
print("OK")';
        $cmd = escapeshellcmd($venv_python) . " -c " . escapeshellarg($py_script) . " " .
               escapeshellarg($brokerhost) . " " .
               escapeshellarg((string)$brokerport) . " " .
               escapeshellarg($brokeruser) . " " .
               escapeshellarg($brokerpass) . " " .
               escapeshellarg($target_topic) . " " .
               escapeshellarg($b64_payload) . " 2>&1";
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
    $phpmqtt_path = (defined("LBHOMEDIR") ? LBHOMEDIR : "/opt/loxberry") . "/libs/phpmqtt/phpMQTT.php";
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
