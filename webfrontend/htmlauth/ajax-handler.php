<?php

require_once "loxberry_system.php";
require_once "defines.php";

if( $_GET["action"] == "saveconfig" ) {
	$data = array();
	foreach( $_POST as $key => $value ) {
		// PHP's $_POST converts dots of post variables to underscores
		$data = generateNew($data, explode("_", $key), 0, $value);
	}
	$jsonstr = json_encode($data, JSON_PRETTY_PRINT);
	if($jsonstr) {
		if ( file_put_contents( CONFIGFILE, $jsonstr ) == false ) {
			sendresponse( 500, "application/json", '{ "error" : "Could not write config file" }' );
		} else {
			
			//unlink all old cronjobs
			if (file_exists(LBHOMEDIR."/system/cron/cron.01min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.01min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.03min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.03min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.05min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.05min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.10min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.10min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.15min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.15min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR);
			}
			if (file_exists(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR))
			{
				unlink(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR);
			}			
			if  (isset($data["Cron"]["enabled"])){
				if ($data["Cron"]["enabled"] == "on"){
					
					$croninterval= $data["Cron"]["interval"];
					$cronpath = LBHOMEDIR."/system/cron/cron.".$croninterval."/".LBPPLUGINDIR;
					// We create a cronjob that updates the status in the background
					$cronentrystr = 
						"#!/bin/bash".PHP_EOL.
						"cd ".LBPHTMLAUTHDIR.PHP_EOL.
						"php ".LBPHTMLAUTHDIR."/vitoconnect.php action=summary".PHP_EOL;
					if (!file_put_contents($cronpath, $cronentrystr)) {
						sendresponse( 500, "application/json", '{ "error" : "creation of CRON jobs failed" }' );
					}
					chmod($cronpath, 0755); 
				}
			}
			sendresponse ( 200, "application/json", file_get_contents(CONFIGFILE) );
		}
	} else {
		sendresponse( 500, "application/json", '{ "error" : "Submitted data are not valid json" }' );
	}
	exit(1);
}

if ( $_GET["action"] == "getsummary" ) {
	shell_exec("php $lbphtmlauthdir/vitoconnect.php action=summary");
	if ( ! file_exists(INSTALLDATA) ) {
		sendresponse ( 500, "application/json", '{ "error" : "Could not query summary" }' );
	}
	sendresponse ( 200, "application/json", file_get_contents( INSTALLDATA ) );
}

sendresponse ( 501, "application/json",  '{ "error" : "No supported action given." }' );
exit(1);

function generateNew($array, $keys, $currentIndex, $value)
    {
        if ($currentIndex == count($keys) - 1)
        {
            $array[$keys[$currentIndex]] = $value;
        }
        else
        {
            if (!isset($array[$keys[$currentIndex]]))
            {
                $array[$keys[$currentIndex]] = array();
            }

            $array[$keys[$currentIndex]] = generateNew($array[$keys[$currentIndex]], $keys, $currentIndex + 1, $value);
        }

        return $array;
    }








function sendresponse( $httpstatus, $contenttype, $response = null )
{

$codes = array ( 
	200 => "OK",
	204 => "NO CONTENT",
	304 => "NOT MODIFIED",
	400 => "BAD REQUEST",
	404 => "NOT FOUND",
	405 => "METHOD NOT ALLOWED",
	500 => "INTERNAL SERVER ERROR",
	501 => "NOT IMPLEMENTED"
);
	
	header($_SERVER["SERVER_PROTOCOL"]." $httpstatus ". $codes[$httpstatus]); 
	header("Content-Type: $contenttype");
	
	if($response) {
		echo $response;
	}
	exit(0);
}


?>
