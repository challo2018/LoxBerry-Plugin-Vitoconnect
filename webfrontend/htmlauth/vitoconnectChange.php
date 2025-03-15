<?php
require_once "vitoconnectInstallationData.php";

function setValueViessmann($Parameter, $Value, $loginData, $apiVersion) {
    global $operatingModeIntStr;

    $installGeneral = InstallationGeneral::getFromCache();

    if ($installGeneral) {
        $installGeneral = InstallationGeneral::getFromViessmann($loginData);
    }
    if (!$installGeneral) {
        LOGERR("Unable to get modell installation - exiting");
        exit(1);
    }

    $id = $installGeneral->id;
    $serial = $installGeneral->serial;

    $url =(apiURL."installations/".$id."/gateways/".$serial."/devices/0/features/" );

    if ($apiVersion == "v2") {
        $url =(apiURLv2."installations/".$id."/gateways/".$serial."/devices/0/features/");
    }

    LOGINF("Set Param: ".$Parameter." to Value: ".$Value);

    switch($Parameter) {
        case "heating.circuits.0.heating.curve":
            $SplitValues = explode("-",$value);
            $url = $url.$Parameter."/commands/setCurve";
            $PostData = "{\"shift\":".$SplitValues[0]."\"slope\":".$SplitValues[1]."}";
            $SplitValues = explode("|", $Value);
            $url = $url.$Parameter."/commands/setCurve";
            $PostData = "{\"shift\":".$SplitValues[0].",\"slope\":".$SplitValues[1]."}";
            break;
        case "heating.circuits.1.heating.curve":
            $SplitValues = explode("-",$value);
            $url = $url.$Parameter."/commands/setCurve";
            $PostData = "{\"shift\":".$SplitValues[0]."\"slope\":".$SplitValues[1]."}";
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

        default:
            LOGERR("Action '" . $Parameter . "' not supported. Exiting.");
            exit(1);
    }

    $curl = curl_init();

    $header = [ ];

    array_push( $header, "Authorization: Bearer $loginData->token" );
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
    LOGDEB("curl_send post data: $PostData");
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