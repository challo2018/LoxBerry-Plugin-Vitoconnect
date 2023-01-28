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

$html = LBLog::get_notifications_html( LBPPLUGINDIR, null);
echo $html;

if(empty($html))
{
    echo $L['LOGFILES.EMPTY'];
}

LBWeb::lbfooter();