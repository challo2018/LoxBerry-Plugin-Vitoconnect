<?php



use PHPUnit\Framework\TestCase;
require_once "../webfrontend/htmlauth/phpMQTT/phpMQTT.php";

$topic = null;
$receivedMessage = null;

function handleMessage($topic, $message) {
    global $topic, $receivedMessage;
    echo($topic . " mesage " . $message . "\n");
    $topic = $topic;
    $receivedMessage = $message;
}

function fancyLogging($level, $message) {
    echo "LOGLEVEL: ". $level . " MESSAGE:<" . $message . ">\n";
}
class phpMQTTTest extends TestCase
{
    public function testSubscribe() {
        $mqtt = new Bluerhinos\phpMQTT("192.168.20.8",  1883, "cli99",null,"fancyLogging");
        $topic = "vitoconnect/desired";
        $topics[$topic . "/". '+'] = array('qos' => 1, 'function' => 'handleMessage');
        $mqtt->addCallback($topics);
        $mqtt->connect(true, NULL, "loxberry", "Y4Yj27pxFSNfCe70");


        $start = time();
        $firstTime=true;
        while($mqtt->proc()) {
            if ($firstTime) {
                $result = $mqtt->subscribe($topics);
                $firstTime=false;
            }

            if(time() > $start+10) {
                break;
            }
            sleep(0.5);
        }
        $mqtt->close();
        $this->assertNull($result);
    }
    public function handleMessage($topic, $message) {
        $this->receivedMessage = $message;
    }
};


