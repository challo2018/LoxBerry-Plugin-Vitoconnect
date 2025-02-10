<?php



use PHPUnit\Framework\TestCase;
setGlobals();
require_once "vitoConnectInstallationData.php";
require_once "vitoConnectLogin.php";

function readPluginConfig() {
    $pluginConfigRaw = file_get_contents("lbhomedir/plugin/config/config.json");
    $pluginConfig = json_decode($pluginConfigRaw);

    return $pluginConfig;
}

function getLoginData(): LoginData {
    $config = readPluginConfig();
    $login = LoginData::doViessmannLogin($config->user, $config->pass, $config->apikey);
    return $login;
}

function getSampleJson($lastChangedDate): String {
    $lastChangeDateString = $lastChangedDate->format("c");
    $sampleJsonString =
            '{"cursor":{"next":""},
            "data":[{"id":86516,"description":"Unterschleißheim",
            "address":{"street":"Tannenstraße","houseNumber":"12","zip":"85716","city":"Unterschleißheim","region":null,"country":"de","phoneNumber":null,"faxNumber":null,"geolocation":{"latitude":48.27629,"longitude":11.58498,"timeZone":"Europe/Berlin"}},"gateways":[{"serial":"7571381605216204","version":"2.8.0.0","firmwareUpdateFailureCounter":0,"autoUpdate":false,"createdAt":"2016-10-18T19:22:10.183Z","producedAt":"2016-10-13T10:46:12.000Z","lastStatusChangedAt":"'. $lastChangeDateString . '","aggregatedStatus":"WorksProperly","targetRealm":"DC","gatewayType":"VitoconnectOptolink","installationId":86516,"registeredAt":"2018-04-27T15:37:23.810Z","description":null,"otaOngoing":false,"devices":[{"gatewaySerial":"7571381605216204","id":"0","boilerSerial":"7542014701157109","boilerSerialEditor":"DeviceCommunication","bmuSerial":"7742336701809108","bmuSerialEditor":"DeviceCommunication","createdAt":"2018-06-08T02:17:59.121Z","editedAt":"2025-02-07T13:28:54.935Z","modelId":"CU401B_S","status":"Online","deviceType":"heating","roles":["capability:consumptionReport;electric","capability:monetization;AdvancedReport","capability:productionReport;electric","capability:productionReport;thermal","type:brand;Viessmann","type:cooling;integrated","type:dhw;integrated","type:heating;integrated","type:heatpump","type:legacy","type:product;CU401B"],"isBoilerSerialEditable":false,"brand":"Viessmann","translationKey":"CU401B_S_22"},{"gatewaySerial":"7571381605216204","id":"gateway","boilerSerial":null,"boilerSerialEditor":null,"bmuSerial":null,"bmuSerialEditor":null,"createdAt":"2019-09-13T10:18:16.368Z","editedAt":"2025-02-07T13:28:54.935Z","modelId":"Heatbox1","status":"Online","deviceType":"vitoconnect","roles":["type:gateway;VitoconnectOpto1","type:legacy"],"isBoilerSerialEditable":false,"brand":null,"translationKey":"Heatbox1"}]}],"registeredAt":"2018-04-27T15:37:23.000Z","updatedAt":"2025-02-07T13:29:28.204Z","aggregatedStatus":"WorksProperly","servicedBy":null,"heatingType":null,"ownedByMaintainer":false,"endUserWlanCommissioned":true,"withoutViCareUser":false,"installationType":"Residential","buildingName":null,"buildingEmail":null,"buildingPhone":null,"accessLevel":"Owner","ownershipType":"ResidentialEndUser","brand":"Viessmann"}]}';
    return $sampleJsonString;
}
class vitoConnectTestInstallationData extends TestCase
{
    public function testReadInstallationGeneralViessmann()
    {
        $login = getLoginData();
        $installationGeneral = InstallationGeneral::getFromViessmann($login);
        $this->assertNotNull($installationGeneral);
        $this->assertTrue($installationGeneral->aggregatedStatusOk);
        $this->assertEquals("WorksProperly",$installationGeneral->aggregatedStatus);
        $this->assertEquals("Online",$installationGeneral->status);
        $this->assertGreaterThan(time()-3600*24,$installationGeneral->lastStatusChangedAt);
    }

    public function testReadInstallationGeneralCacheNotAvailable()
    {
        unlink(INSTALLDATA);
        $cached = InstallationGeneral::getFromCache();
        $this->assertNull($cached);
    }

    public function testReadInstallationGeneralCacheInvalid()
    {
        file_put_contents(INSTALLDATA,"crap");
        $cached = InstallationGeneral::getFromCache();
        $this->assertNull($cached);
    }

    public function testReadInstallationGeneralCacheValid()
    {
        file_put_contents(INSTALLDATA,getSampleJson(new DateTime()));
        $cached = InstallationGeneral::getFromCache();
        $this->assertNotNull($cached);
        $this->assertNotNull($cached->lastStatusChangedAt);
        this->assertEquals(true,$cached->aggregatedStatusOk);
        $this->assertEquals("online",$cached->status);
        $this->assertEquals(86516, $cached->id);
        $this->assertEquals(7571381605216204, $cached->serial);
    }

    public function testReadInstallationGeneralCacheTooOld()
    {
        $now = new DateTime();
        $past = $now->add(DateInterval::createFromDateString('-10 days'));
        $past = $past->add(DateInterval::createFromDateString('-1 minute'));
        file_put_contents(INSTALLDATA,getSampleJson($past));
        $cached = InstallationGeneral::getFromCache();
        $this->assertNull($cached);
    }

    public function testReadInstallationDetail() {
        $login = getLoginData();
        $installData = InstallationData::getFromViessmann($login);
        $this->assertNotNull($installData);
        $this->assertEquals(2,$installData->detail->{"heating.circuits.0.operating.modes.active.value.enum"});
        $this->assertEquals(20,$installData->detail->{"heating.circuits.0.operating.programs.comfort.temperature"});
        $this->assertEquals(19,$installData->detail->{"heating.circuits.0.operating.programs.eco.temperature"});
    }
};


function setGlobals() {
    global $testOverrideLogLevel;
    $testOverrideLogLevel = 7;
}