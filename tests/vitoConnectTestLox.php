<?php



use PHPUnit\Framework\TestCase;



require_once "vitoConnectLox.php";
require_once "vitoConnectConfig.php";

setGlobals();
function generateConfig() {
    $sysConfigRaw = file_get_contents("lbhomedir/config/system/general.json");
    $sysConfig = json_decode($sysConfigRaw);
    $result = new Configuration();
    $result->mqttTopic  ="test/vitoconnect";
    $result->mqttBrokerAddress= $sysConfig->Mqtt->Brokerhost;
    $result->mqttBrokerUser= $sysConfig->Mqtt->Brokeruser;
    $result->mqttBrokerPassword= $sysConfig->Mqtt->Brokerpass;
    return $result;
}
$message = null;
class vitoConnectTestLox extends TestCase
{
    public function testMqttPublish() {
        global $message;
        $config = generateConfig();
        $broker = explode(':', $config->mqttBrokerAddress, 2);
        $mqtt= new Bluerhinos\phpMQTT($broker[0], 1883,"vitoConnectTextLox", null, "fancyLogging");
        $mqtt->connect(true, NULL, $config->mqttBrokerUser, $config->mqttBrokerPassword);




        $data = new stdClass();
        $data->testValue = "value". rand(1,10000);
        $result = publishInstallationDetailToLox($data,$config);
        sleep(5);
        $this->assertTrue($result);
        $publishedData = $mqtt->subscribeAndWaitForMessage("test/vitoconnect/testValue",0);
        $mqtt->close();
        $this->assertEquals($data->testValue, $publishedData);
    }

    public function handleCallsTest($intopic, $msg){
        global $message;
        LOGERR("**************** MQTT inbound $intopic $msg");
        $message = $msg;
    }
};

function fancyLogging($level, $message) {
    echo "LOGLEVEL: ". $level . " MESSAGE:<" . $message . ">\n";
}
function setGlobals() {
    global $testOverrideLogLevel;
    $testOverrideLogLevel = 7;
}

