<?php



use PHPUnit\Framework\TestCase;
$testparams = "foo";
setGlobals();
require_once "vitoconnect.php";


function fancyLogging($level, $message) {
    echo "LOGLEVEL: ". $level . " MESSAGE:<" . $message . ">\n";
}
class vitoConnectTest extends TestCase
{
    public function testCall() {

    }

};


function setGlobals() {
    global $testOverrideLogLevel;
    $testOverrideLogLevel = 7;
}