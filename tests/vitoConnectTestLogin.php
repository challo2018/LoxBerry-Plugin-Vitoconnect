<?php



use PHPUnit\Framework\TestCase;
setGlobals();
require_once "vitoConnectLogin.php";


function readPluginConfig() {
    $pluginConfigRaw = file_get_contents("lbhomedir/plugin/config/config.json");
    $pluginConfig = json_decode($pluginConfigRaw);

    return $pluginConfig;
}
class vitoConnectTestLogin extends TestCase
{
    public function testLoginFromServer() {
        $config = readPluginConfig();
        $this->assertNotEmpty($config->user);
        $this->assertNotEmpty($config->pass);
        $this->assertNotEmpty($config->apikey);
        $login = LoginData::doViessmannLogin($config->user, $config->pass, $config->apikey);
        $this->assertNotNull($login);
        $this->assertNotEmpty($login->token);
        $this->assertNotEmpty($login->expirationTimestamp);
        $this->assertGreaterThan(time(), $login->expirationTimestamp);
    }

    public function testLoginDataFromCacheInvalid() {
        file_put_contents("/tmp/vitoconnect/sessiondata.json","illegal");
        $login = LoginData::getFromCache();
        $this->assertNull($login);
    }

    public function testLoginDataFromCacheEmptyJson() {
        file_put_contents("/tmp/vitoconnect/sessiondata.json",'{"token":"", "expirationTimestamp":""}');
        $login = LoginData::getFromCache();
        $this->assertNull($login);
    }

    public function testLoginDataFromCacheNotThere() {
        unlink("/tmp/vitoconnect/sessiondata.json");
        $login = LoginData::getFromCache();
        $this->assertNull($login);
    }

    public function testLoginDataFromCacheOK() {
        $expirationTimestamp = time() + 100;
        file_put_contents("/tmp/vitoconnect/sessiondata.json",'{"token":"12345678910", "expirationTimestamp":' . $expirationTimestamp . '}');
        $login = LoginData::getFromCache();
        $this->assertNotNull($login);
        $this->assertEquals("12345678910", $login->token);
        $this->assertEquals($expirationTimestamp, $login->expirationTimestamp);
    }
    public function testLoginDataFromCacheTooLate() {
        $expirationTimestamp = time() ;
        file_put_contents("/tmp/vitoconnect/sessiondata.json",'{"token":"12345678910", "expirationTimestamp":' . $expirationTimestamp . '}');
        $login = LoginData::getFromCache();
        $this->assertNull($login);
    }

};


function setGlobals() {
    global $testOverrideLogLevel;
    $testOverrideLogLevel = 7;
}