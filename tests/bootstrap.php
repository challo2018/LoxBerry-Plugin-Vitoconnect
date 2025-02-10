<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/project/webfrontend/htmlauth');
set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/project/tests/sys');
print("Include path: " . get_include_path());
setGlobalsBootstrap();



function setGlobalsBootstrap() {
    global $lbhomedir;
    global $lbpplugindir;
    global $lbsdatadir;
    global $lbconfigdir;
    global $lbplogdir;
    global $lbpconfigdir;
    $lbhomedir = __DIR__ . "/lbhomedir";
    $lbconfigdir = __DIR__ . "/lbhomedir/config";
    $lbsdatadir = __DIR__ . "/lbhomedir/data";
    $lbpplugindir = __DIR__ . "/lbhomedir/plugin";
    $lbplogdir = __DIR__ . "/lbhomedir/log";
    $lbpconfigdir = $lbpplugindir . "/config";
    putenv("LBHOMEDIR=$lbhomedir");
    define ("LBPPLUGINDIR", $lbpplugindir);
}