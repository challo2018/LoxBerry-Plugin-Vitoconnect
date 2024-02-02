<?php

define ("CONFIGFILE", "$lbpconfigdir/config.json");
define ("TMPPREFIX", "/run/shm/${lbpplugindir}_");
define ("LOGINFILE", TMPPREFIX . "sessiondata.json");
define ("INSTALLDATA", TMPPREFIX . "installdata.json");

define ("client_secret", "2e21faa1-db2c-4d0b-a10f-575fd372bc8c-575fd372bc8c");
define ("callback_uri", "http://localhost:4200/"); 
define ("apiURLBase", "https://api.viessmann-platform.io/iot/v1/equipment/");
define ("apiURL", "https://api.viessmann.com/iot/v1/equipment/");
define ("apiURLv2", "https://api.viessmann.com/iot/v2/features/");
define ("authorize_URL", "https://iam.viessmann.com/idp/v2/authorize");
define ("token_url", "https://iam.viessmann.com/idp/v2/token");

// The Navigation Bar
$navbar[1]['Name'] = "Settings";
$navbar[1]['URL'] = 'index.php';
 
$navbar[2]['Name'] = "Query links and data";
$navbar[2]['URL'] = 'showdata.php';

$navbar[3]['Name'] = "Log";
$navbar[3]['URL'] = 'log.php'; 
