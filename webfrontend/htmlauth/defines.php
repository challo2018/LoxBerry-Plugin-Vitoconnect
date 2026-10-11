<?php

define ("CONFIGFILE", "$lbpconfigdir/config.json");
define ("TMPPREFIX", "/run/shm/${lbpplugindir}_");
define ("LOGINFILE", TMPPREFIX . "sessiondata.json");
define ("INSTALLDATA", TMPPREFIX . "installdata.json");
define ("STATSFILE", "/run/shm/Vitoconnect_stats.json");

// The Navigation Bar
$navbar[1]['Name'] = "Settings & Service";
$navbar[1]['URL'] = 'index.php';
 
$navbar[2]['Name'] = "Status & Live Data";
$navbar[2]['URL'] = 'showdata.php';

$navbar[3]['Name'] = "Log";
$navbar[3]['URL'] = 'log.php';
