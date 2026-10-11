#!/usr/bin/php
<?php

/**
 * Vitoconnect Plugin v2.0 - Backward Compatibility Wrapper
 * Vitoconnect v2 operates as an asynchronous background daemon using PyViCare and MQTT.
 * This wrapper forwards legacy HTTP calls (e.g. from existing Loxone configs) to MQTT.
 */

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "defines.php";

$log = LBLog::newLog(["name" => "Vitoconnect-Legacy-Bridge", "stderr" => 1]);
LOGSTART("Vitoconnect Legacy HTTP Bridge called");

// Parse command line or GET parameters
if (!empty($argv)) {
    foreach ($argv as $arg) {
        $e = explode("=", $arg, 2);
        if (count($e) == 2) {
            $_GET[$e[0]] = $e[1];
        }
    }
}

$action = isset($_GET["action"]) ? $_GET["action"] : (isset($_GET["a"]) ? $_GET["a"] : "summary");
$option = isset($_GET["option"]) ? $_GET["option"] : null;
$value  = isset($_GET["value"])  ? $_GET["value"]  : null;

// Read config
$topic = "vitoconnect";
if (file_exists(CONFIGFILE)) {
    $cfg = json_decode(file_get_contents(CONFIGFILE), true);
    if (!empty($cfg["MQTT"]["topic"])) {
        $topic = trim($cfg["MQTT"]["topic"], "/");
    }
}

if ($action == "summary" || $action == "sum") {
    LOGINF("Legacy trigger: requesting immediate poll via MQTT ($topic/poll)");
    if (function_exists("mqtt_publish")) {
        mqtt_publish("$topic/poll", "1");
    }
    echo json_encode(["status" => "ok", "message" => "Triggered poll via daemon"]);
    LOGEND("Finished");
    exit(0);
}

if ($action == "setvalue" && $option !== null && $value !== null) {
    LOGINF("Legacy setvalue: $option = $value");
    
    // Map legacy options to new set topics
    $subtopic = str_replace(".", "/", $option);
    $targetTopic = "$topic/set/$subtopic";
    
    if (function_exists("mqtt_publish")) {
        mqtt_publish($targetTopic, (string)$value);
        LOGINF("Published to MQTT: $targetTopic = $value");
    }
    
    echo json_encode(["status" => "ok", "topic" => $targetTopic, "value" => $value]);
    LOGEND("Finished");
    exit(0);
}

LOGWARN("Unknown action: $action");
echo json_encode(["status" => "error", "message" => "Unknown action: $action"]);
LOGEND("Finished with error");
exit(1);
