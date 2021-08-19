#!/usr/bin/php
<?php

error_log("------------------------------------------------------");

include_once "loxberry_system.php";
include_once "loxberry_io.php";
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

// Default action
$action = "summary";
$Parameter = false;
$Value = false;

if(!empty($_GET["option"])) {
	$Parameter = $_GET["option"];
}
if(!empty($_GET["value"])) {
	$Value = $_GET["value"];
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
			echo "Action '" . $_GET["action"] . "' not supported. Exiting.\n";
			exit(1);
	}
}

echo "Calling parameters:\n";
echo "  action : $action\n";
echo "  option : $Parameter\n";
echo "  value  : $Value\n";

// Validy check
if( $action == "setvalue"  && ($Parameter == false || $Value == false) ) {
	echo "Action '$action' requires parameter option/value. Exiting.\n";
	exit(1);
}

// Init
$token = false;
if( ! file_exists(CONFIGFILE) ) {
	echo "You need to create a json config file. Exiting.\n";
	exit(1);
} else {
	echo "Using configfile " . CONFIGFILE . "\n";
	$configdata = file_get_contents(CONFIGFILE);
	$config = json_decode($configdata);
	if( empty($config) ) {
		echo "Config file exists, but seems to be empty or invalid. Exiting.\n";
		if( !empty(json_last_error()) ) {
			echo "JSON Error: " . json_last_error() . " " . json_last_error_msg() . "\n";
		}
		exit(1);
	}
	$user = $config->user;
	$pass = $config->pass;
	$apikey = $config->apikey;	
}
if( empty($user) || empty($pass) || empty($apikey)) {
	echo "User and/or pass and/or key not set. Exiting.\n";
	exit(1);
}

// Check if this is a LoxBerry
if ( function_exists("currtime") ) {
	echo "Running on a LoxBerry\n";
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
		
		echo "Using broker settings from MQTT Gateway plugin:\n";	
	}
	
}

// Final MQTT check
if ( $mqttenabled ) {
	if ( empty($brokeraddress) ) {
		echo "MQTT is enabled, but no broker is set. Disabling MQTT.\n";
		$mqttenabled = false;
	} else {
		echo "Broker host : $brokeraddress\n";
		echo "Broker user : $brokeruser\n";
		echo "Broker pass : " . substr($brokerpass, 0, 1) . str_repeat("*", strlen($brokerpass)-1) . "\n";		
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
	Viessmann_SetData( $Parameter, $Value);
	exit(0);
} 

echo "Don't know what to do (action '$action'). Exiting.\n";
exit(1);



function Viessmann_summary( $login ){
			
	echo "Get Data from Viessmann API Service.\n";
	
	if ( empty($login) ) {
		echo "JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg() . "\n";
		return;
	}
	
	$Install= new stdClass();
	$Modellinfo = new stdClass();
	
	$Modellinfo -> VScotHO1_20 = "Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 100 (HO1)";
	$Modellinfo -> VScotHO1_40 = "Vitodens 200 / 300 mit Vitotronic 200 (HO1) Vitodens 222-F, 242-F, 333-F, 343-F mit Vitotronic 200 (HO1)";
	$Modellinfo -> VScotHO1_70 = "Vitodens 300-W, Typ WB3E";
	$Modellinfo -> V200WO1 = "Vitocal 222 / 242 G mit Vitotronic 200 (WO1) Vitocal 333 / 343 G mit Vitotronic 200 (WO1)";
	$Modellinfo -> CU401B_A = "Vitocal xxx-A mit Vitotronic 200 (Typ WO1C)";
	
	
	//$installationJson = Viessmann_GetData ( apiURLBase."gateways");

	$modelInstallationJson = Viessmann_GetData ( apiURLBase."installations?includeGateways=true");
	
	$modelInstallationEntity = json_decode($modelInstallationJson, true);
	
	$Install->general = new \stdClass();
	$Install->general-> id = $modelInstallationEntity['data'][0]['gateways'][0]['installationId'];
	$Install->general-> serial = $modelInstallationEntity['data'][0]['gateways'][0]['serial'];	
	$Install->general-> version = $modelInstallationEntity['data'][0]['gateways'][0]['version'];	
	$Install->general-> aggregatedstatus = $modelInstallationEntity['data'][0]['aggregatedStatus'];
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
	echo "\n";
	echo "Get DeviceData from Viessmann API Service.\n";
	
	$installationDetailJson = Viessmann_GetData (apiURLBase."installations/".$Install->general->id."/gateways/".$Install->general->serial."/devices/0/features/" );
	
	//echo $installationDetailJson;
	$Install->detail = new \stdClass();
	$Install->detail->aggregatedstatus= $Install->general->aggregatedstatus;	
	$Install->detail-> timestamp = date('r',time());
	
	$installationDetailEntity = json_decode($installationDetailJson, false);
	
	foreach($installationDetailEntity->data as $entity){		
		$DetailEntity = $entity;	
		
		foreach ($DetailEntity->properties as $key => $value){
			$Key = $entity->feature;
			$Key = $Key.".".$key;
			$type =  $value->type;
			
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
						break;
						
					default: 
						echo "Type '" . $type . "' not supported. Exiting.\n";
						$Value = "Not supported";
				}
				$Install->detail->$Key=$Value;
		}
	}
	
	
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
	echo $SessionCode;
	
	//Herausfiltern des AuthenticationCode aus dem Rückgabewert
	preg_match ('/code=(.*)"/', $SessionCode, $matches);
	$code = $matches[1];	
	//echo "FOUND CODE: $code END CODE";

	
	$AccessToken= Viessmann_GetToken ( token_url, "client_id=".$apikey."&code_verifier=".client_secret."&code=".$code."&redirect_uri=".callback_uri."&grant_type=authorization_code");

	$login = json_decode($AccessToken);
	if ( empty($login) ) {
		echo "JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg() . "\n";
		return;
	}
	$login->ExpirationDate = (time() + $login->expires_in);
	
	
	// Write data to ramdisk
	file_put_contents(LOGINFILE, json_encode($login));
	
	// Read token
	if( empty($login->access_token) ) {
		echo "Data error, no token found. Response: $logindata\n";
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
		echo "File data error, no token found. Fallback to re-login\n";
		return;
	}
	
	// Get date part of token

	
	$tokenexpires = $login->ExpirationDate;
	echo "Token expires: " . $tokenexpires . " (" . date('c', $tokenexpires) . ")\n";
	
	if( $tokenexpires > time()-10 ) {
		// Token is valid
		$token = $login->access_token;
	} else {
		echo "Token expired - refreshing token\n";
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
	echo "Status: " . $crlinf['http_code'] . "\n";
	
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
	echo "Status: " . $crlinf['http_code'] . "\n";
	
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
	echo "Status: " . $crlinf['http_code'] . "\n";
	
	curl_close($curl);
	
	return $response;
	
}

function Viessmann_SetData( $Parameter, $Value ){
	global $token;
	
	
	$installationJson = Viessmann_GetData ( apiURLBase."installations?includeGateways=true");
	
	$installationJsonDecode = json_decode($installationJson, true);
	
	
	$id = $installationJsonDecode['data'][0]['gateways'][0]['installationId'];
	$serial = $installationJsonDecode['data'][0]['gateways'][0]['serial'];	
		
	$url =(apiURLBase."installations/".$id."/gateways/".$serial."/devices/0/features/" );
	
	echo "Set Param: ".$Parameter." to Value: ".$Value;
	
	switch($Parameter) {
		case "heating.circuits.0.heating.curve":
			$SplitValues = explode("-",$value);
			$url = $url.$Parameter."/commands/setCurve";			
			$PostData = "{\"shift\":".$SplitValues[0]."\"slope\":"$SplitValues[1]."}";
			break;
		case "heating.circuits.1.heating.curve":
			$SplitValues = explode("-",$value);
			$url = $url.$Parameter."/commands/setCurve";			
			$PostData = "{\"shift\":".$SplitValues[0]."\"slope\":"$SplitValues[1]."}";
			break;
		case "heating.dhw.temperature.main":
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
		case "heating.circuits.0.operating.programs.normal":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.1.operating.programs.normal":
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
		case "heating.circuits.0.operating.programs.comfort":
			$url = $url.$Parameter."/commands/setTemperature";
			$PostData = "{\"targetTemperature\":".$Value."}";
			break;
		case "heating.circuits.1.operating.programs.comfort":
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

		default: 
			echo "Action '" . $Parameter . "' not supported. Exiting.\n";
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

	echo "curl_send URL: $url\n";
	$response = curl_exec($curl);
	
	echo "curl_exec finished\n";
	// Debugging
	$crlinf = curl_getinfo($curl);
	echo "Status: " . $crlinf['http_code'] . "\n";
	
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
	foreach ($sendbuffer as $key => $value) {
		echo "   $key: $value\n";
	}
	
	// Send via HTTP to Loxone Miniserver
	if( $islb && isset($config->Loxone->enabled) && Vitoconnect_is_enabled($config->Loxone->enabled) ) {
		echo "Sending data to Loxone Miniserver No. $msnr...\n";
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
			echo "MQTT publishing " . MQTTTOPIC . "/".$key.": $value...\n";
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

