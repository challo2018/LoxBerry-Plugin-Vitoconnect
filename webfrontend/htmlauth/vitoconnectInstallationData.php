<?php
require_once "defines.php";
include_once "loxberry_system.php";
require_once "loxberry_log.php";
class ModelInfoEnum extends Enum{
    private function __construct() {
    }
    const VScotHO1_20 = "Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 100 (HO1)";
    const VScotHO1_40 = "Vitodens 200 / 300 mit Vitotronic 200 (HO1) Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 200 (HO1)";
    const VScotHO1_70 = "Vitodens 300-W, Typ WB3E";
    const V200WO1 = "Vitocal 222 / 242 G mit Vitotronic 200 (WO1) Vitocal 333 / 343 G mit Vitotronic 200 (WO1)";
    const CU401B_A = "Vitocal xxx-A mit Vitotronic 200 (Typ WO1C)";

    const CU401B_S = "Vitocal xxx-S mit Vitotronic 200 (Typ WO1C)";
}

class InstallationGeneral {
    public $id;
    public $serial;
    public $version;
    public $aggregatedStatus;
    public $aggregatedStatusOk = false;
    public $gatewayType;
    public $model;
    public $status;
    public $deviceType;
    public $streetaddress;
    public $city;
    public $country;

    public $timestamp;

    public $lastStatusChangedAt;
    private  function __construct() {

    }

    public static function getFromCache(): ?InstallationGeneral
    {
        // Reads installation data from disk
        if (!file_exists(INSTALLDATA)) {
            return null;
        }
        $cachedData = file_get_contents(INSTALLDATA);
        $result = InstallationGeneral::fromJsonString($cachedData);
        if ($result == null) {
            return null;
        }
        $ageInSeconds = time() - $result->lastStatusChangedAt;
        if ($ageInSeconds > 3600 * 24 * 10) { //10 days maximum age
            LOGINF("Discarding stale data, refreshing installation general, age is $ageInSeconds seconds. ");
            return null;
        }
        return $result;
    }

    public static function getFromViessmann($loginData):?InstallationGeneral{
        $modelInstallationJson = Viessmann_GetData ( apiURL."installations?includeGateways=true", $loginData);
        if (is_null($modelInstallationJson)) {
            LOGERR("Unable to get modell installation ");
            return null;
        }
        file_put_contents(INSTALLDATA, $modelInstallationJson );
        return InstallationGeneral::fromJsonString($modelInstallationJson);
    }

    private static function fromJsonString($jsonString): ?InstallationGeneral
    {
        $modelInstallationEntity = json_decode($jsonString, true);
        if (is_null($modelInstallationEntity)) {
            LOGERR("Unable to parse json string");
            return null;
        }

        $result = new InstallationGeneral();
        $result->id = $modelInstallationEntity['data'][0]['gateways'][0]['installationId'];
        $result->serial = $modelInstallationEntity['data'][0]['gateways'][0]['serial'];
        $result->version = $modelInstallationEntity['data'][0]['gateways'][0]['version'];
        $result->aggregatedStatus = $modelInstallationEntity['data'][0]['aggregatedStatus'];
        # There is no documentation which values are possible. The only known thing is WorksProperly means OK
        $result->aggregatedStatusOk = (strcasecmp($result->aggregatedStatus, "WorksProperly") == 0);
        $result->gatewayType = $modelInstallationEntity['data'][0]['gateways'][0]['gatewayType'];
        $result->lastStatusChangedAt = DateTime::createFromFormat('Y-m-d\TH:i:s+',$modelInstallationEntity['data'][0]['gateways'][0]['lastStatusChangedAt'])->getTimestamp();


        $modellCode = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['modelId'];
        $modelEnum = ModelInfoEnum::fromString($modellCode);
        if(isset($modelEnum)) {
            $result->model = $modelEnum;
        } else {
            $result->model = $modellCode;
        }

        $result->status = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['status'];
        $result->deviceType = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['deviceType'];

        $result->streetaddress = $modelInstallationEntity['data'][0]['address']['street']." ".$modelInstallationEntity['data'][0]['address']['houseNumber'];
        $result->city = $modelInstallationEntity['data'][0]['address']['zip']." ".$modelInstallationEntity['data'][0]['address']['city'];
        $result->country = $modelInstallationEntity['data'][0]['address']['country'];
        $result->timestamp = date('r',time());
        return $result;
    }
}

class InstallationData {
    public $general;

    public $detail;
    private  function __construct() {

    }
    public static function getFromViessmann($loginData){
        LOGDEB("Get Data from Viessmann API Service.");

	    $Install= new InstallationData();

        $Install->general = InstallationGeneral::getFromCache();

        if (!$Install->general) {
            $Install->general = InstallationGeneral::getFromViessmann($loginData);
        }
        if (!$Install->general) {
            LOGERR("Unable to get modell installation - exiting");
            exit(1);
        }

        $installationDetailJson = Viessmann_GetData (apiURL."installations/".$Install->general->id."/gateways/".$Install->general->serial."/devices/0/features/", $loginData );
        if (is_null($installationDetailJson)) {
            LOGERR("Unable to get installation details -exiting");
            exit(1);
        }
        $Install->detail = InstallationData::installDetailFromJson($installationDetailJson);
        $Install->detail->aggregatedstatus= $Install->general->aggregatedStatus;
        $Install->detail->aggregatedstatus_ok= $Install->general->aggregatedStatusOk;
        return $Install;
    }

    private static function installDetailFromJson($installationDetailJson){
        global $operatingModeStrInt;
        LOGDEB("Installation detail $installationDetailJson");
        $result = new \stdClass();

        $result-> timestamp = date('r',time());
        $installationDetailEntity = json_decode($installationDetailJson, false);
        $latestEpochTimeFoundInData=0; //store the latest time found within the data itself. Some change quite seldomly, while e.g. outside temperature changes frequently

        foreach($installationDetailEntity->data as $entity){
            $DetailEntity = $entity;

            foreach ($DetailEntity->properties as $key => $value){
                $Key = $entity->feature;
                $Key = $Key.".".$key;
                $type =  $value->type;
                $currentDateTimeFeature = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $entity->timestamp, new DateTimeZone('UTC'))->getTimestamp(); // fun fact that DateTime::ISO8601 does not work with fraction of seconds
                if ($currentDateTimeFeature > $latestEpochTimeFoundInData) {
                    $latestEpochTimeFoundInData = $currentDateTimeFeature;
                }

                switch($type) {

                    case "ErrorListChanges":
                    case "Schedule":
                        //Bei der Rückmeldung der Felder heating.errors.avtive und heating.errors.history wird, wenn kein Fehler vorhanden ist, ein leeres Array übermittelt
                        //Da somit kein Wert vorhanden ist, erfolgt auch keine Änderung des MQTT Topics
                        //Überprüfung des Arrays und falls leer wird es mit pseudowerten gefüllt
                        if ($Key == "heating.errors.active.entries" || $Key =="heating.errors.history.entries"){
                            foreach($value->value as $subkey => $subval){
                                if(empty($subval)){
                                    $value->value[$subkey] = [["errorCode"=>"F00","timestamp"=>date("c",time()),"accessLevel"=>"customer","priority"=>"Info"]];
                                }
                            }

                        }

                        $Value= json_encode($value->value);
                        break;

                    case "array":
                        $Value= join(",",$value->value);
                        break;

                    case "boolean":
                    case "number":
                        $Value= var_export($value->value,true);
                        break;

                    case "string":
                        $Value= $value->value;
                        //map operating modes to int
                        if (strEndsWith($Key, 'operating.modes.active.value')) {
                            $Int_value = $operatingModeStrInt[$Value];
                            $Int_key = $Key . ".enum";
                            $result->$Int_key=$Int_value;
                        }
                        break;

                    default:
                        LOGERR("Type '" . $type . "' not supported. Exiting.");
                        $Value = "Not supported";
                }
                $result->$Key=$Value;
            }
        }
        $result-> timestamp_latestdata_lox = epoch2lox($latestEpochTimeFoundInData);
        return $result;
    }
}

function Viessmann_GetData( $url, $loginData ){
    $curl = curl_init();

    $header = [ ];

    array_push( $header, "Authorization: Bearer $loginData->token" );


    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
    curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);

    $response = curl_exec($curl);

    // Debugging
    $crlinf = curl_getinfo($curl);
    if ( ((int)$crlinf['http_code']) >= 400) {
        LOGERR("Status GetData: " . $crlinf['http_code']);
        LOGERR("Response " . $response);
        $response = null;
    } else {
        LOGDEB("Status GetData: " . $crlinf['http_code']);
    }


    curl_close($curl);

    return $response;

}

function strEndsWith($haystack, $needle): bool
{
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}