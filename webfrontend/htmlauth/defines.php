<?php

definePluginDefines();

// The Navigation Bar
$navbar[1]['Name'] = "Settings";
$navbar[1]['URL'] = 'index.php';

$navbar[2]['Name'] = "Query links and data";
$navbar[2]['URL'] = 'showdata.php';

$navbar[3]['Name'] = "Log";
$navbar[3]['URL'] = 'log.php';

initializeOperatingMode();
function initializeOperatingMode() {
    global $operatingModeIntStr;
    global $operatingModeStrInt;

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
}
function definePluginDefines() {
    global $lbpconfigdir;
    global $lbpplugindir;
    define ("CONFIGFILE", "$lbpconfigdir/config.json");
    define ("TMPPREFIX", isLoxberryRuntime() ? "/run/shm/${lbpplugindir}_" : "/tmp/vitoconnect/");
    define ("LOGINFILE", TMPPREFIX . "sessiondata.json");
    define ("INSTALLDATA", TMPPREFIX . "installdata.json");

    define ("client_secret", "2e21faa1-db2c-4d0b-a10f-575fd372bc8c-575fd372bc8c");
    define ("callback_uri", "http://localhost:4200/");
    define ("apiURLBase", "https://api.viessmann-platform.io/iot/v1/equipment/");
    define ("apiURL", "https://api.viessmann.com/iot/v1/equipment/");
    define ("apiURLv2", "https://api.viessmann.com/iot/v2/features/");
    define ("authorize_URL", "https://iam.viessmann.com/idp/v2/authorize");
    define ("token_url", "https://iam.viessmann.com/idp/v2/token");
}


function isLoxberryRuntime():bool {
    return is_dir("/run/shm");
}

abstract class Enum
{
    private static $constCacheArray = NULL;

    private static function getConstants()
    {
        if (self::$constCacheArray == NULL) {
            self::$constCacheArray = [];
        }
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$constCacheArray)) {
            $reflect = new \ReflectionClass($calledClass);
            self::$constCacheArray[$calledClass] = $reflect->getConstants();
        }
        return self::$constCacheArray[$calledClass];
    }

    public static function isValidName($name, $strict = false)
    {
        $constants = self::getConstants();

        if ($strict) {
            return array_key_exists($name, $constants);
        }

        $keys = array_map('strtolower', array_keys($constants));
        return in_array(strtolower($name), $keys);
    }

    public static function isValidValue($value, $strict = true)
    {
        $values = array_values(self::getConstants());
        return in_array($value, $values, $strict);
    }

    public static function fromString($name)
    {
        if (self::isValidName($name, $strict = true)) {
            $constants = self::getConstants();
            return $constants[$name];
        }

        return false;
    }

    public static function toString($value)
    {
        if (self::isValidValue($value, $strict = true)) {
            return array_search($value, self::getConstants());
        }

        return false;
    }
}