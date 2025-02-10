<?php



use PHPUnit\Framework\TestCase;
$testparams = "foo";
setGlobals();
$argv=["action=setvalue","option=heating.circuits.0.operating.programs.reduced","value=17"];
require_once "vitoconnect.php";


function fancyLogging($level, $message) {
    echo "LOGLEVEL: ". $level . " MESSAGE:<" . $message . ">\n";
}
class phpMQTTTest2 extends TestCase
{
    public function testCall() {

    }

};


function setGlobals() {
    global $testOverrideLogLevel;
    $testOverrideLogLevel = 7;
}