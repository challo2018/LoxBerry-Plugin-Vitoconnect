<?php
include_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "defines.php";
require_once __DIR__."/phpMQTT/phpMQTT.php";
function publishInstallationDetailToLox( $data, $configuration ){
    $result = false;
    foreach ($data as $key => $val) {
        $sendbuffer[$key] = $val;
    }

    // Show values
    foreach ($sendbuffer as $key => $value) {
        LOGDEB("   $key: $value");
    }

    // Send via HTTP to Loxone Miniserver
    if( isset($configuration->loxoneMsNr)) {
        LOGDEB("Sending data to Loxone Miniserver No. $configuration->loxoneMsNr...");
        if($configuration->isLoxoneCacheDisabled)  {
            mshttp_send( $configuration->loxoneMsNr, $sendbuffer );
            $result = true;
        } else {
            mshttp_send_mem( $configuration->loxoneMsNr, $sendbuffer );
            $result = true;
        }
    }
    // Send to MQTT
    if( isset($configuration->mqttBrokerAddress) ) {
        $result = mqtt_publish_local( $sendbuffer, $configuration );
    }
    return $result;
}

####################################################
# MQTT handler
####################################################
function mqtt_publish_local ( $keysandvalues, $configuration ) {

    $broker = explode(':', $configuration->mqttBrokerAddress, 2);
    $broker[1] = !empty($broker[1]) ? $broker[1]  : 1883;

    $client_id = uniqid(gethostname()."_vitoconnect");
    $mqtt = new Bluerhinos\phpMQTT($broker[0],  $broker[1], $client_id);
    if( $mqtt->connect(true, NULL, $configuration->mqttBrokerUser, $configuration->mqttBrokerPassword) ) {
        foreach ($keysandvalues as $key => $value) {
            //$keysplit=explode("_", $key, 2);
            $key=str_replace(".","/",$key);
            LOGDEB("MQTT publishing " . $configuration->mqttTopic . "/".$key.": $value...");
            $mqtt->publish($configuration->mqttTopic  . "/". $key, $value, 1, 1);
        }
        $mqtt->close();
        return true;
    } else {
        LOGERR("Unable to connect to MQTT server");
        return false;
    }
}