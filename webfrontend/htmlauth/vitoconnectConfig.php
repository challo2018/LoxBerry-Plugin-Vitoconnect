<?php
class Configuration {

    public $vieUser;

    public $viePassword;

    public $vieApiKey;

    public $mqttTopic;

    public $mqttTopicIncoming;

    public $mqttBrokerAddress;

    public $mqttBrokerUser;

    public $mqttBrokerPassword;

    public $loxoneMsNr;

    public $isLoxoneCacheDisabled = false;

    public $apiVersion = 1;

    public function __construct() {}

    public static function create() {
        $result = new Configuration();
        // Init
        if( ! file_exists(CONFIGFILE) ) {
            LOGERR("You need to create a json config file. Exiting.");
            exit(1);
        } else {
            LOGDEB("Using configfile " . CONFIGFILE );
            $configdata = file_get_contents(CONFIGFILE);
            $configJson = json_decode($configdata);
            if( empty($configJson) ) {
                LOGERR("Config file exists, but seems to be empty or invalid. Exiting.");
                if( !empty(json_last_error()) ) {
                    LOGERR("JSON Error: " . json_last_error() . " " . json_last_error_msg());
                }
                exit(1);
            }
            $result->vieUser = $configJson->user;
            $result->viePassword = $configJson->pass;
            $result->vieApiKey = $configJson->apikey;
        }
        if( empty($result->vieUser) || empty($result->viePassword) || empty($result->vieApiKey)) {
            LOGERR("User and/or pass and/or key not set. Exiting.");
            exit(1);
        }

        $msnr = isset($configJson->Loxone->msnr) ? $configJson->Loxone->msnr : 1 ;
        if (isset($configJson->Loxone->enabled) && Vitoconnect_is_enabled($configJson->Loxone->enabled)) {
            $result->loxoneMsNr = $msnr;
            if( isset($configJson->Loxone->cachedisabled) && Vitoconnect_is_enabled($configJson->Loxone->cachedisabled) ) {
                $result->isLoxoneCacheDisabled = true;
            }
        }

        // MQTT support
        if( isset($configJson->MQTT->enabled) && Vitoconnect_is_enabled($configJson->MQTT->enabled) ) {
            $mqttenabled = true;
            $mqtttopic = !empty($configJson->MQTT->topic) ? $configJson->MQTT->topic : "vitoconnect";
        } else {
            $mqttenabled = false;
        }

        if ( $mqttenabled  && !empty($configJson->MQTT->host) ) {
            $brokeraddress = $configJson->MQTT->host;
            $brokeruser = !empty($configJson->MQTT->user) ? $configJson->MQTT->user : "";
            $brokerpass = !empty($configJson->MQTT->pass) ? $configJson->MQTT->pass : "";
        }

        if ($mqttenabled && empty($brokeraddress) ) {
            // Check if MQTT plugin in installed and get credentials
            $mqttcred = mqtt_connectiondetails();

            if ( $mqttcred != NULL){
                $brokeraddress = $mqttcred['brokeraddress'];
                $brokeruser = $mqttcred['brokeruser'];
                $brokerpass = $mqttcred['brokerpass'];

                LOGDEB("Using broker settings from MQTT Gateway plugin:");
            }

        }

        // Final MQTT check
        if ( $mqttenabled ) {
            if ( empty($brokeraddress) ) {
                LOGERR("MQTT is enabled, but no broker is set. Disabling MQTT.");
                $mqttenabled = false;
            } else {
                LOGDEB("Broker host : $brokeraddress");
                LOGDEB("Broker user : $brokeruser");
                LOGDEB("Broker pass : " . substr($brokerpass, 0, 1) . str_repeat("*", strlen($brokerpass)-1));
            }
        }

        if ($mqttenabled) {
            $result->mqttBrokerAddress = $brokeraddress;
            $result->mqttBrokerUser = $brokeruser;
            $result->mqttBrokerPassword = $brokerpass;
            $result->mqttTopic = $mqtttopic;
            $result->mqttTopicIncoming = $mqtttopic . "/desired";
        }
        return $result;
    }
}

class Command {
    public $action;
    public $parameter;

    public $value;

    public function __construct($commandLineArguments, $getParameters)
    {
        $this->init($commandLineArguments, $getParameters);
    }

    private function init($commandLineArguments, $getParameters) {
        // Convert commandline parameters to query parameter
        if (!empty($commandLineArguments)){
            foreach ($commandLineArguments as $arg) {
                $e=explode("=",$arg);
                if(count($e)==2)
                    $getParameters[$e[0]]=$e[1];
                else
                    $getParameters[$e[0]]=0;
            }
        }

// Parse query paraeters
        LOGDEB("GET params:  " . print_r($getParameters,true));
// Default action
        $this->action = "summary";
        $this->parameter = null;
        $this->value = null;

        if(!empty($getParameters["option"])) {
            $this->parameter = $getParameters["option"];
        }
        if(array_key_exists("value", $getParameters)) { //could be that value is set to 0 intentionally!
            $this->value  = $getParameters["value"];
        }

// Actions
        if(isset($getParameters["a"])) {
            $getParameters["action"] = $getParameters["a"];
        }
        if(isset($getParameters["action"])) {
            switch($getParameters["action"]) {
                case "summary":
                case "sum":
                    $this->action = "summary";
                    break;
                case "setvalue":
                    $this->action = "setvalue";
                    break;
                case "relogin":
                    $this->action = "relogin";
                    break;
                case "mqttpoll":
                    $this->action = "mqttpoll";
                    break;
                default:
                    LOGERR("Action '" . $getParameters["action"] . "' not supported. Exiting.");
                    exit(1);
            }
        }

        LOGDEB("Calling parameters:");
        LOGDEB("  action : $this->action");
        LOGDEB("  option : $this->parameter");
        LOGDEB("  value  : $this->value");
        // Validy check
        if( $this->action == "setvalue"  && (!isset($this->parameter) || !isset($this->value)) ) {
            LOGERR("Action $this->value requires parameter option/value. Exiting.");
            exit(1);
        }
    }
}

####################################################
# is_enabled - tries to detect if a string says 'True'
####################################################
function Vitoconnect_is_enabled($text):bool
{
    $text = trim($text);
    $text = strtolower($text);

    $words = array("true", "yes", "on", "enabled", "enable", "1", "check", "checked", "select", "selected");
    if (in_array($text, $words)) {
        return true;
    }
    return false;
}