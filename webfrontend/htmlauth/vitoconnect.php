#!/usr/bin/php
<?php

error_log("------------------------------------------------------");
include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "defines.php";
require_once "vitoconnectLogin.php";
require_once "vitoconnectConfig.php";
require_once "vitoconnectInstallationData.php";
require_once "vitoconnectChange.php";
require_once "vitoconnectLox.php";
require_once "vitoconnectCmdPoll.php";

require_once __DIR__."/phpMQTT/phpMQTT.php";

require_once __DIR__."/assert/Assert.php";
require_once __DIR__."/assert/Assertion.php";
require_once __DIR__."/assert/AssertionChain.php";
require_once __DIR__."/assert/AssertionFailedException.php";
require_once __DIR__."/assert/functions.php";
require_once __DIR__."/assert/InvalidArgumentException.php";
require_once __DIR__."/assert/LazyAssertion.php";
require_once __DIR__."/assert/LazyAssertionException.php";

require_once __DIR__."/link/LinkInterface.php";
require_once __DIR__."/link/EvolvableLinkInterface.php";
require_once __DIR__."/link/LinkProviderInterface.php";
require_once __DIR__."/link/EvolvableLinkProviderInterface.php";

require_once __DIR__."/exception-constructor-tools/ExceptionConstructorTools.php";


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
$log = LBLog::newLog( [ "name" => "Vitoconnect", "stderr" => 1 ] );
//$log = LBLog::newLog( ["package"=>"Vitoconnect", "loglevel" => "1", "name" => "Vitoconnect", "stderr" => 1, "nofile" =>1 ] );
LOGSTART("Start Logging");

$command = new Command($argv, $_GET);
$configuration = Configuration::create();

$loginData = null;

// Read login data from disk, if exists
if ( $command->action != "relogin" ) {
	$loginData = LoginData::getFromCache();
} else {
	$command->action = "summary";
}

// Call Login
if ( empty($loginData) ) {
    $loginData = LoginData::doViessmannLogin($configuration->vieUser, $configuration->viePassword, $configuration->vieApiKey);
}

if ( !isset($loginData) ) {
    LOGERR("JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg());
    return;
}

// What should we do?
if( $command->action == "summary" ) {
    $Install= InstallationData::getFromViessmann($loginData);
    publishInstallationDetailToLox($Install->detail, $configuration);
    $Install->persistForControlPlane();
	exit(0);
} 

if( $command->action == "setvalue" ) {
    setValueViessmann( $command->parameter, $command->value, $loginData, $configuration->apiVersion);
	exit(0);
}
if ($command->action == "mqttpoll") {
	LOGDEB("Entering mqtt poll");
	Mqtt_Command_Poll();
	exit(0);
}

LOGERR("Don't know what to do (action '$command->action'). Exiting.");
exit(1);

