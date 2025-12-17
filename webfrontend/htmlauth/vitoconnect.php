#!/usr/bin/php
<?php

error_log("------------------------------------------------------");

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "defines.php";

require_once "./phpMQTT/phpMQTT.php";

require_once "./assert/Assert.php";
require_once "./assert/Assertion.php";
require_once "./assert/AssertionChain.php";
require_once "./assert/AssertionFailedException.php";
require_once "./assert/functions.php";
require_once "./assert/InvalidArgumentException.php";
require_once "./assert/LazyAssertion.php";
require_once "./assert/LazyAssertionException.php";

require_once "./link/LinkInterface.php";
require_once "./link/EvolvableLinkInterface.php";
require_once "./link/LinkProviderInterface.php";
require_once "./link/EvolvableLinkProviderInterface.php";

require_once "./exception-constructor-tools/ExceptionConstructorTools.php";

//camel case, similar to Viessmann. Note that for sending the values, Viessmann needs correct case!
$operatingModeStrInt = [
	"standby" => 1,
	"dhwAndHeating" => 2,
	"dhw" => 3,
	"forcedNormal" => 4,
	"forcedReduced" => 5,
	"undefined" => 9
];

$operatingModeIntStr = array_flip($operatingModeStrInt);

// Ventilation Operating Mode:
$VentilatiOnoperatingModeStrInt = [
	"standby" => 1,
	"standard" => 2,
	"ventilation" => 3,
	"undefined" => 9
];

$VentilatiOnoperatingModeStrInt = array_flip($VentilatiOnoperatingModeStrInt);



// Create and start log
// Shutdown function
//see https://wiki.loxberry.de/entwickler/entwicker_tipps_und_tricks/loxberry_logging_tips_and_tricks
register_shutdown_function('shutdown');
function shutdown()
{
	global $log;
	
	if(isset($log)) {
		LOGEND("Processing finished");
	}
}

// function to check if string is valid json array
// for PHP version <8.3 otherwise use json_validate
function isJson($str) {
    $json = json_decode($str);
    return $json !== false && !is_null($json) && $str != $json;
}

$log = LBLog::newLog( [ "name" => "Vitoconnect", "stderr" => 1 ] );
LOGSTART("Start Logging");

//
// Query parameter 
//

// Convert commandline parameters to query parameter
if (!empty($argv)){
	foreach ($argv as $arg) {
		$e=explode("=",$arg);
		if(count($e)==2)
			$_GET[$e[0]]=$e[1];
		else    
			$_GET[$e[0]]=0;
	}
}

// Parse query paraeters
LOGDEB("GET params:  " . print_r($_GET,true));
// Default action
$action = "summary";
$Parameter = false;
$Value = 0;
$ValueSet=false;

if(!empty($_GET["option"])) {
	$Parameter = $_GET["option"];
}
if(array_key_exists("value", $_GET)) { //could be that value is set to 0 intentionally!
	$Value = $_GET["value"];
	$ValueSet=true;
}

// Actions
if(isset($_GET["a"])) {
	$_GET["action"] = $_GET["a"];
}
if(isset($_GET["action"])) { 
	switch($_GET["action"]) {
		case "summary":
		case "sum":
			$action = "summary";
			break;
		case "setvalue":
			$action = "setvalue";
			break;
		case "relogin":
			$action = "relogin";
			break;
		default: 
			LOGERR("Action '" . $_GET["action"] . "' not supported. Exiting.");
			exit(1);
	}
}

LOGDEB("Calling parameters:");
LOGDEB("  action : $action");
LOGDEB("  option : $Parameter");
LOGDEB("  value  : $Value");

// Validy check
if( $action == "setvalue"  && ($Parameter == false || $ValueSet == false) ) {
	LOGERR("Action {$action} requires parameter option/value. Exiting.");
	exit(1);
}

// Init
$token = false;
if( ! file_exists(CONFIGFILE) ) {
	LOGERR("You need to create a json config file. Exiting.");
	exit(1);
} else {
	LOGDEB("Using configfile " . CONFIGFILE );
	$configdata = file_get_contents(CONFIGFILE);
	$config = json_decode($configdata);
	if( empty($config) ) {
		LOGERR("Config file exists, but seems to be empty or invalid. Exiting.");
		if( !empty(json_last_error()) ) {
			LOGERR("JSON Error: " . json_last_error() . " " . json_last_error_msg());
		}
		exit(1);
	}
	$user = $config->user;
	$pass = $config->pass;
	$apikey = $config->apikey;
	$apiversion = $config->apiversion;
}
if( empty($user) || empty($pass) || empty($apikey)) {
	LOGERR("User and/or pass and/or key not set. Exiting.");
	exit(1);
}

// Check if this is a LoxBerry
if ( function_exists("currtime") ) {
	LOGDEB("Running on a LoxBerry");
	$islb = true;
	$msnr = isset($config->Loxone->msnr) ? $config->Loxone->msnr : 1 ;
} else {
	$islb = false;
}

// MQTT support
if( isset($config->MQTT->enabled) && Vitoconnect_is_enabled($config->MQTT->enabled) ) {
	$mqttenabled = true;
	$mqtttopic = !empty($config->MQTT->topic) ? $config->MQTT->topic : "vitoconnect";
	define ("MQTTTOPIC", $mqtttopic);

} else {
	$mqttenabled = false;
}

if ( $mqttenabled == true && !empty($config->MQTT->host) ) {
	$brokeraddress = $config->MQTT->host;
	$brokeruser = !empty($config->MQTT->user) ? $config->MQTT->user : "";
	$brokerpass = !empty($config->MQTT->pass) ? $config->MQTT->pass : "";
}

if ($islb && $mqttenabled && empty($brokeraddress) ) {
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

// Read login data from disk, if exists
if ( $action != "relogin" ) {
	$login = Viessmann_readlogin();
} else {
	$action = "summary";
}

// Call Login
if ( empty($token) ) {
	$login = Viessmann_login($user, $pass, $apikey);
}


// What should we do?

if( $action == "summary" ) {
	Viessmann_summary( $login );
	exit(0);
} 

if( $action == "setvalue" ) {
	Viessmann_SetData( $Parameter, $Value, $apiversion);
	exit(0);
} 

LOGERR("Don't know what to do (action '$action'). Exiting.");
exit(1);


function Viessmann_summary( $login ){
	global $operatingModeStrInt;
			
	LOGDEB("Get Data from Viessmann API Service.");
	
	if ( empty($login) ) {
		LOGERR("JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg());
		return;
	}
	
	$Install= new stdClass();
	$Modellinfo = new stdClass();
	
	$Modellinfo -> VScotHO1_20 = "Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 100 (HO1)";
	$Modellinfo -> VScotHO1_40 = "Vitodens 200 / 300 mit Vitotronic 200 (HO1) Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 200 (HO1)";
	$Modellinfo -> VScotHO1_70 = "Vitodens 300-W, Typ WB3E";
	$Modellinfo -> V200WO1 = "Vitocal 222 / 242 G mit Vitotronic 200 (WO1) Vitocal 333 / 343 G mit Vitotronic 200 (WO1)";
	$Modellinfo -> CU401B_A = "Vitocal xxx-A mit Vitotronic 200 (Typ WO1C)";
	
	
	$modelInstallationJson = Viessmann_GetData ( apiURLv2."equipment/installations?includeGateways=true");
	if (is_null($modelInstallationJson)) {
		LOGERR("Unable to get modell installation - exiting");
		Viessmann_Publish($Install->general);	//Publish General Information to get aggregatedstatus->error when it is not possible to get instalation details. Before no change on MQTT or HTTP when Gateway was offline.
		exit(1);
	}
	LOGDEB("Installations: $modelInstallationJson");
	
	$modelInstallationEntity = json_decode($modelInstallationJson, true);
	
	$Install->general = new \stdClass();
	$Install->general-> id = $modelInstallationEntity['data'][0]['gateways'][0]['installationId'];
	$Install->general-> serial = $modelInstallationEntity['data'][0]['gateways'][0]['serial'];	
	$Install->general-> version = $modelInstallationEntity['data'][0]['gateways'][0]['version'];	
	$Install->general-> aggregatedstatus = $modelInstallationEntity['data'][0]['aggregatedStatus'];
	# There is no documentation which values are possible. The only known thing is WorksProperly means OK
	$Install->general-> aggregatedstatus_ok = (strcasecmp($Install->general->aggregatedstatus, "WorksProperly") == 0);
	$Install->general-> gatewaytype = $modelInstallationEntity['data'][0]['gateways'][0]['gatewayType'];
	
	
	$ModellCode = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['modelId'];
	if(isset($Modellinfo->$ModellCode)) {
		$Install->general-> modelid = $Modellinfo->$ModellCode;
	} else {
		$Install->general-> modelid = $ModellCode;
	}
	
	$Install->general-> status = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['status'];
	$Install->general-> devicetype = $modelInstallationEntity['data'][0]['gateways'][0]['devices'][0]['deviceType'];
	
	
	$Install->general-> streetaddress = $modelInstallationEntity['data'][0]['address']['street']." ".$modelInstallationEntity['data'][0]['address']['houseNumber'];	
	$Install->general-> city = $modelInstallationEntity['data'][0]['address']['zip']." ".$modelInstallationEntity['data'][0]['address']['city'];	
	$Install->general-> country = $modelInstallationEntity['data'][0]['address']['country'];
	$Install->general-> timestamp = date('r',time());
		
	// Write data to ramdisk
	$content  = json_encode($Install);
	file_put_contents(INSTALLDATA, $content );
	//echo $content;
	
	
	//Get Detail Data of your installation
	LOGDEB("Get DeviceData from Viessmann API Service.");
	
	// deprecated endpoint of v1 from 2025-11-17 https://api.viessmann-climatesolutions.com/documentation/static/changelog-2025
	$installationDetailJson = Viessmann_GetData (apiURLv2."features/installations/".$Install->general->id."/gateways/".$Install->general->serial."/devices/0/features/" );
	if (is_null($installationDetailJson)) {
		LOGERR("Unable to get installation details -exiting");
		exit(1);
	}

	LOGDEB("Installation detail $installationDetailJson");
	$Install->detail = new \stdClass();
	$Install->detail->aggregatedstatus= $Install->general->aggregatedstatus;
	$Install->detail->aggregatedstatus_ok= $Install->general->aggregatedstatus_ok;
	$Install->detail-> timestamp = date('r',time());
	
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
						
						$Value = json_encode($value->value);
						break;
						
					case "array":
						//if (isJson($Value) == true) {
						//if (json_validate($Value) == true) {
							$Value = json_encode($value->value);	
							$Value = str_replace(",",";",$Value);
							break;
						//}						
						if (!is_scalar($value->value)) {
							break;
						}
						$Value = join(";",$value->value);						
						break;
						
					case "boolean":
					case "number":
						$Value= var_export($value->value,true);
						break;
						
					case "string":
						$Value = $value->value;
						//map operating modes to int
						if (strEndsWith($Key, 'operating.modes.active.value')) {
							$Int_value = $operatingModeStrInt[$Value];
							$Int_key = $Key . ".enum";
							$Install->detail->$Int_key=$Int_value;
						}
						break;
						
					default: 
						LOGERR("Type '" . $type . "' not supported. Exiting.");
						$Value = "Not supported";
				}
				$Install->detail->$Key=$Value;
		}
	}
	$Install->detail-> timestamp_latestdata_lox = epoch2lox($latestEpochTimeFoundInData);

	$detailcontent  = json_encode($Install);
	$detailcontent  = json_decode($detailcontent,true);
	ksort($detailcontent["detail"]);
	
	// Write data to ramdisk
	$detailcontent  = json_encode($detailcontent);
	file_put_contents(INSTALLDATA, $detailcontent );
	//echo $detailcontent;
	
	Viessmann_Publish($Install->detail);	
}


function Viessmann_login ( $user, $pass, $apikey ){
	global $token;

	$SessionCode = Viessmann_GetAuthCode( authorize_URL."?client_id=".$apikey."&redirect_uri=".callback_uri."&code_challenge=2e21faa1-db2c-4d0b-a10f-575fd372bc8c-575fd372bc8c&"."&scope=IoT%20User%20offline_access"."&response_type=code", $user,$pass);
	LOGDEB($SessionCode);
	
	//Herausfiltern des AuthenticationCode aus dem Rückgabewert
	preg_match ('/code=(.*)"/', $SessionCode, $matches);
	$code = $matches[1];	
	//echo "FOUND CODE: $code END CODE";

	
	$AccessToken= Viessmann_GetToken ( token_url, "client_id=".$apikey."&code_verifier=".client_secret."&code=".$code."&redirect_uri=".callback_uri."&grant_type=authorization_code");

	$login = json_decode($AccessToken);
	if ( empty($login) ) {
		LOGERR("JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg() );
		return;
	}
	$login->ExpirationDate = (time() + $login->expires_in);
	
	
	// Write data to ramdisk
	file_put_contents(LOGINFILE, json_encode($login));
	
	// Read token
	if( empty($login->access_token) ) {
		LOGERR("Data error, no token found. Response: $logindata");
		return;
	} else {
		$token = $login->access_token;
	}
	return $login;
}

function Viessmann_readlogin (){
	// Reads login data from disk, and checks for expiration of the token
	global $token;
	
	if( ! file_exists(LOGINFILE) ) {
		return;
	}
	$logindata = file_get_contents(LOGINFILE);
	$login = json_decode($logindata);
	
	// Read token
	if( empty($login->access_token) ) {
		LOGINF("File data error, no token found. Fallback to re-login");
		return;
	}
	
	// Get date part of token

	
	$tokenexpires = $login->ExpirationDate;
	LOGDEB("Token expires: " . $tokenexpires . " (" . date('c', $tokenexpires) . ")");
	
	if( $tokenexpires > time()-10 ) {
		// Token is valid
		$token = $login->access_token;
	} else {
		LOGINF("Token expired - refreshing token");
	}
		
	// echo $logindata . "\n";
	return $login;
}

function Viessmann_GetAuthCode( $url, $user, $pwd){
	global $token;
	$curl = curl_init();

	$header = [ ];
		
	array_push( $header, "Content-Type: application/x-www-form-urlencoded" );		
	curl_setopt($curl, CURLOPT_POST, 1);
	
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
	curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);
	curl_setopt($curl, CURLOPT_USERPWD,"$user:$pwd");
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	
	$response = curl_exec($curl);
	
	// Debugging
	$crlinf = curl_getinfo($curl);
	LOGDEB("Status GetAuthCode: " . $crlinf['http_code']);
	
	curl_close($curl);
	
	return $response;
	
}

function Viessmann_GetToken( $url, $PostData ){
	global $token;
	$curl = curl_init();

	$header = [ ];
		
	array_push( $header, "Content-Type: application/x-www-form-urlencoded" );		
	
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
	curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($curl, CURLOPT_POSTFIELDS,$PostData);
	
	$response = curl_exec($curl);
	
	// Debugging
	$crlinf = curl_getinfo($curl);
	LOGDEB("Status GetToken: " . $crlinf['http_code']);
	
	curl_close($curl);
	
	return $response;
	
}

function Viessmann_GetData( $url ){
	global $token;
	$curl = curl_init();

	$header = [ ];
		
	array_push( $header, "Authorization: Bearer $token" );	
	
	
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

function Viessmann_SetData( $Parameter, $Value, $apiversion ){
	global $token;
	global $operatingModeIntStr;
	
	
	$installationJson = Viessmann_GetData ( apiURLv2."equipment/installations?includeGateways=true");
	if (is_null($installationJson)) {
		LOGERR("Unable to get installation data, unable to proceed setting data");
		exit(1);
	}
	
	$installationJsonDecode = json_decode($installationJson, true);
	
	
	$id = $installationJsonDecode['data'][0]['gateways'][0]['installationId'];
	$serial = $installationJsonDecode['data'][0]['gateways'][0]['serial'];	
	

	$url =(apiURLv2."features/installations/".$id."/gateways/".$serial."/devices/0/features/");
	
	LOGINF("Set Param: ".$Parameter." to Value: ".$Value);
	
	switch($Parameter) {
		case "heating.circuits.0.heating.curve":
			$SplitValues = explode("|", $Value);
			$url = $url.$Parameter."/commands/setCurve";
			$PostData = "{\"shift\":".$SplitValues[0].",\"slope\":".$SplitValues[1]."}";
			break;
		case "heating.circuits.1.heating.curve":
			$SplitValues = explode("|",$Value);
			$url = $url.$Parameter."/commands/setCurve";
			$PostData = "{\"shift\":".$SplitValues[0].",\"slope\":".$SplitValues[1]."}";
			break;
		case "heating.circuits.2.heating.curve":
			$SplitValues = explode("|",$Value);
			$url = $url.$Parameter."/commands/setCurve";
			$PostData = "{\"shift\":".$SplitValues[0].",\"slope\":".$SplitValues[1]."}";
			break;
		case "heating.dhw.temperature.main":
			$url = $url.$Parameter."/commands/setTargetTemperature";
			$PostData = "{\"temperature\":".$Value."}";
			break;
		case "heating.dhw.temperature":
			$url = $url.$Parameter."/commands/setTargetTemperature";
			$PostData = "{\"temperature\":".$Value."}";
			break;
		case "heating.dhw.temperature.temp2":
			$url = $url.$Parameter."/commands/setTargetTemperature";
			$PostData = "{\"temperature\":".$Value."}";
			break;
		case "heating.dhw.temperature.hysteresis":
			$url = $url.$Parameter."/commands/setHysteresis";
			$PostData = "{\"hysteresis\":".$Value."}";
			break;		
		case "heating.circuits.0.operating.modes.active":
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Value."\"}";
			break;
		case "heating.circuits.1.operating.modes.active":
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Value."\"}";
			break;
		case "heating.circuits.2.operating.modes.active":
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Value."\"}";
			break;
		case "heating.circuits.0.operating.modes.active.enum":
		case "heating.circuits.1.operating.modes.active.enum":
		case "heating.circuits.2.operating.modes.active.enum":
			if ((int)$Value == 0) {
				LOGINF("Ignoring 0 value - Loxone sends this sometimes as no choice");
				return;
			}
			$Str_value = isset($operatingModeIntStr[(int)$Value]) ? $operatingModeIntStr[(int)$Value] : "";
			$Parameter = substr($Parameter, 0, -5);//remove .enum at the end
			if (empty($Str_value) || $Str_value == "undefined") {
				LOGERR("Illegal enum value " . $Value );
				return null;
			}
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Str_value."\"}";
			break;
		case "heating.circuits.0.operating.programs.normal":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.1.operating.programs.normal":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.2.operating.programs.normal":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.reduced":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.1.operating.programs.reduced":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.2.operating.programs.reduced":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.comfort":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.1.operating.programs.comfort":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.2.operating.programs.comfort":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.dhw.oneTimeCharge":
			if($Value == "start"){
				$url = $url.$Parameter."/commands/activate";
			}
			if($Value == "stop"){
				$url = $url.$Parameter."/commands/deactivate";
			}
			$PostData = "{}";
			break;	
		case "ventilation.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		case "heating.dhw.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		case "heating.dhw.pumps.circulation.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		case "heating.circuits.0.heating.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		case "heating.circuits.1.heating.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		case "heating.circuits.2.heating.schedule":
			$url = $url.$Parameter."/commands/setSchedule";
			$PostData = "{\"newSchedule\":".$Value."}";
			break;
		/* added by Flanki */
		case "heating.dhw.temperature.hysteresis":
			$url = $url.$Parameter."/commands/setHysteresisSwitchOnValue";
			$PostData = "{\"hysteresis\":".$Value."}";
			break;
		// min: 0, max: 2.5
		case "heating.dhw.temperature.hysteresis":
			$url = $url.$Parameter."/commands/setHysteresisSwitchOffValue";
			$PostData = "{\"hysteresis\":".$Value."}";
			break;
		// note: only working if dhw mode is comfort and not eco
		case "heating.dhw.hygiene":
			if($Value == "enable"){
				$url = $url.$Parameter."/commands/enable";
			}
			$PostData = "{}";
			break;
		// values: off - eco - comfort
		case "heating.dhw.operating.modes.active":
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Value."\"}";
			break;
		case "heating.circuits.0":
		case "heating.circuits.0.name":
			$url = $url.$Parameter."/commands/setName";
			$PostData = "{\"name\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.forcedLastFromSchedule":
			if($Value == "activate"){
				$url = $url.$Parameter."/commands/activate";
			}
			if($Value == "deactivate"){
				$url = $url.$Parameter."/commands/deactivate";
			}
			$PostData = "{}";
			break;
		case "heating.circuits.0.operating.programs.reducedHeating":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.normalHeating":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.comfortHeating":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;			
		case "heating.circuits.0.operating.programs.reducedCooling":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.normalCooling":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.operating.programs.comfortCooling":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.0.temperature.levels.setMin":
			$url = $url."heating.circuits.0.temperature.levels/commands/setMin";
			$PostData = "{\"temperature\":".$Value."}";
			break;
		case "heating.circuits.0.temperature.levels.setMax":
			$url = $url."heating.circuits.0.temperature.levels/commands/setMax";
			$PostData = "{\"temperature\":".$Value."}";
			break;
		case "heating.circuits.0.temperature.levels.setLevels":
			$SplitValues = explode("|",$Value);
			$url = $url."heating.circuits.0.temperature.levels/commands/setLevels";
			$PostData = "{\"minTemperature\":".$SplitValues[0]."{\"maxTemperature\":".$SplitValues[1]."}";
			break;
		case "heating.operating.programs.holidayAtHome.schedule":
			$SplitValues = explode("|",$Value);
			$url = $url."heating.operating.programs.holidayAtHome/commands/schedule";
			$PostData = "{\"start\":".$SplitValues[0]."{\"end\":".$SplitValues[1]."}";
			break;
		case "heating.operating.programs.holidayAtHome.unschedule":
			$url = $url."heating.operating.programs.holidayAtHome/commands/unschedule";
			$PostData = "{}";
			break;
		case "heating.operating.programs.holiday.schedule":
			$SplitValues = explode("|",$Value);
			$url = $url."heating.operating.programs.holiday/commands/schedule";
			$PostData = "{\"start\":".$SplitValues[0]."{\"end\":".$SplitValues[1]."}";
			break;
		case "heating.operating.programs.holiday.unschedule":
			$url = $url."heating.operating.programs.holiday/commands/unschedule";
			$PostData = "{}";
			break;
		case "ventilation.operating.modes.active.enum":
			if ((int)$Value == 0) {
				LOGINF("Ignoring 0 value - Loxone sends this sometimes as no choice");
				return;	
			}
			$Str_value = isset($VentilatiOnoperatingModeStrInt[(int)$Value]) ? $VentilatiOnoperatingModeStrInt[(int)$Value] : "";
			$Parameter = substr($Parameter, 0, -5);//remove .enum at the end
			if (empty($Str_value) || $Str_value == "undefined") {
				LOGERR("Illegal enum value " . $Value );
				return null;
			}
			$url = $url.$Parameter."/commands/setMode";
			$PostData = "{\"mode\":\"".$Str_value."\"}";
			break;	
		case "ventilation.quickmodes.comfort":
			if($Value == "activate"){
				$url = $url.$Parameter."/commands/activate";
			}
			if($Value == "deactivate"){
				$url = $url.$Parameter."/commands/deactivate";
			}
			$PostData = "{}";
			break;		
		case "ventilation.quickmodes.eco":
			if($Value == "activate"){
				$url = $url.$Parameter."/commands/activate";
			}
			if($Value == "deactivate"){
				$url = $url.$Parameter."/commands/deactivate";
			}
			$PostData = "{}";
			break;	
		case "ventilation.schedule.resetSchedule":
			$url = $url."ventilation.schedule/commands/resetSchedule";
			$PostData = "{}";
			break;				
		default: 
			LOGERR("Action '" . $Parameter . "' not supported. Exiting.");
			exit(1);
	}
	
	$curl = curl_init();

	$header = [ ];
		
	array_push( $header, "Authorization: Bearer $token" );	
	array_push( $header, "Content-Type: application/json" );
	array_push( $header, "Accept: application/vnd.siren+json" );
	
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
	curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);
	curl_setopt($curl, CURLOPT_TIMEOUT,10);
	//curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($curl, CURLOPT_POSTFIELDS,$PostData);

	LOGINF("curl_send URL: $url");
	LOGDEB("curl_send post data: {$PostData}");
	$response = curl_exec($curl);
	
	LOGDEB("curl_exec finished");
	// Debugging
	$crlinf = curl_getinfo($curl);

	if ( ((int)$crlinf['http_code']) >= 400) {
		LOGERR("Status GetData: " . $crlinf['http_code']);
		LOGERR("Response " . $response);
		$response = null;
	} else {
		LOGINF("Status:  " . $crlinf['http_code']);
	}
	LOGDEB("Status:  " . print_r($crlinf,true));
	
	curl_close($curl);
	
	return $response;
	
}

function Viessmann_Publish( $data ){
	
	foreach ($data as $key => $val) {
		$sendbuffer[$key] = $val;		
	}
	relay( $sendbuffer );	
}

// Central sending function to relay to Loxone and/or MQTT
function relay ( $sendbuffer ){
	global $islb, $config, $msnr, $mqttenabled;
	
	// Show values
	LOGDEB("Sending following values to Loxone and/or MQTT (function relay ( $sendbuffer ):)");
	foreach ($sendbuffer as $key => $value) {
		LOGDEB("   {$key}: {$value}");
	}
	
	// Send via HTTP to Loxone Miniserver
	if( $islb && isset($config->Loxone->enabled) && Vitoconnect_is_enabled($config->Loxone->enabled) ) {
		LOGDEB("Sending data to Loxone Miniserver No. {$msnr}...");
		if( isset($config->Loxone->cachedisabled) && Vitoconnect_is_enabled($config->Loxone->cachedisabled) ) {
			mshttp_send( $msnr, $sendbuffer );
		} else {
			mshttp_send_mem( $msnr, $sendbuffer );
		}
	}
	// Send to MQTT
	if( $mqttenabled ) {
		mqtt_publish_local( $sendbuffer );
	}
}

####################################################
# MQTT handler
####################################################
function mqtt_publish_local ( $keysandvalues ) {
	
	global $brokeraddress, $brokeruser, $brokerpass;
	
	$broker = explode(':', $brokeraddress, 2);
	$broker[1] = !empty($broker[1]) ? $broker[1]  : 1883;
	
	$client_id = uniqid(gethostname()."_vitoconnect");
	$mqtt = new Bluerhinos\phpMQTT($broker[0],  $broker[1], $client_id);
	if( $mqtt->connect(true, NULL, $brokeruser, $brokerpass) ) {
		foreach ($keysandvalues as $key => $value) {
			//$keysplit=explode("_", $key, 2);
			$key=str_replace(".","/",$key);
			LOGDEB("MQTT publishing " . MQTTTOPIC . "/".$key.": $value...");
			$mqtt->publish(MQTTTOPIC . "/". $key, $value, 0, 1);
		}
		$mqtt->close();
	}
}

####################################################
# is_enabled - tries to detect if a string says 'True'
####################################################
function Vitoconnect_is_enabled($text){ 
	$text = trim($text);
	$text = strtolower($text);
	
	$words = array("true", "yes", "on", "enabled", "enable", "1", "check", "checked", "select", "selected");
	if (in_array($text, $words)) {
		return 1;
	}
	return NULL;
}
//PHP8 has a solution, PHP 7 not
function strEndsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}
