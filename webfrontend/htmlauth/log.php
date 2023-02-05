<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_log.php";
require_once "defines.php";


$L = LBSystem::readlanguage("language.ini");
$template_title = "Viessmann Vitoconnect Gateway";
$helplink = "https://wiki.loxberry.de/plugins/vitoconnect/start";
$helptemplate = "help.html";

LBWeb::lbheader($template_title, $helplink, $helptemplate);


$navbar[1]['active'] = null;
$navbar[2]['active'] = null;
$navbar[3]['active'] = True;

//LOGFILES
echo '<p class="wide">'. $L['LOGFILES.HEAD']. '</p>';

if ($handle = opendir($lbplogdir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
          echo '<div class="ui-corner-all ui-shadow">';
          echo '<a id="btnlogs" data-role="button" href="/admin/system/tools/logfile.cgi?logfile=plugins/vitoconnect/'. $entry. '&header=html&format=template" target="_blank" data-inline="true" data-mini="true">'.$entry. '</a>';
          echo '</div>';
        }
    }
    closedir($handle);
}

LBWeb::lbfooter();
?>
