<?php

function Mqtt_Command_Poll () {
    global $mqttenabled, $brokeraddress, $brokeruser, $brokerpass, $global_mqtt_topic_desired;
    if( $mqttenabled ) {
        LOGINF("Trying to poll state from current mqtt topics!");

        $broker = explode(':', $brokeraddress, 2);
        $broker[1] = !empty($broker[1]) ? $broker[1]  : 1883;

        #$client_id = gethostname()."_vitoconnect";
        $client_id = "cli"; #"ds9_control_vitoconnect";
        $mqtt = new Bluerhinos\phpMQTT($broker[0],  $broker[1], $client_id);
        $mqtt->connect(false, NULL, $brokeruser, $brokerpass);
        $mqtt->debug = true;

        LOGDEB("MQTT polling" . $global_mqtt_topic_desired);
        $topics[$global_mqtt_topic_desired . "/". '+'] = array('qos' => 2, 'function' => 'handleCalls');
        #$topics2[ "dummy/". '+'] = array('qos' => 0, 'function' => 'handleCalls');
        $mqtt->subscribe($topics);
        #$mqtt->addCallback($topics);
        $start = time();
        while($mqtt->proc()) {

            if(time() > $start+5) {
                break;
            }
            sleep(0.5);
        }
        $mqtt->close();
    } else {
        LOGINF("MQTT not enabled");
    }
}

// Processing json topic of device
function handleCalls($intopic, $msg){
    LOGDEB("MQTT inbound $intopic $msg");
}