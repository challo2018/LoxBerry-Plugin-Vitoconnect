<?php

require_once "loxberry_web.php";
require_once "defines.php";

$navbar[1]['active'] = True;
$navbar[2]['active'] = null;
$navbar[3]['active'] = null;

$L = LBSystem::readlanguage("language.ini");
$template_title = "Viessmann Vitoconnect Gateway";
$helplink = "https://wiki.loxberry.de/plugins/vitoconnect/start";
$helptemplate = "help.html";

LBWeb::lbheader($template_title, $helplink, $helptemplate);

?>

<style>
.mono {
	font-family:monospace;
	font-size:110%;
	font-weight:bold;
	color:green;

}
#overlay 
{
  display: none !important;
}
</style>

<!-- Form SETTINGS -->
<form id="form" onsubmit="return false;">

<!-- Viessmann Vitoconnect -->

<div class="wide">Viessmann Vitoconnect</div>
<p><i>This Plugin connects to Viessmann API to get data from your Viessmann Vitoconnect 100. This allows to fetch data for heating, solar and water.<br>Account at <a href="https://developer.viessmann.com/">Viessmann Developer Portal</a> is necessary.</i></p>

<div data-role="fieldcontain">
	<label for="user">Username</label>
	<input name="user" id="user" type="text" />
	<p class="hint">This is the username (E-Mail Adresse) of your <i>Viessmann Developer Portal</i> account.</p>
</div>

<div data-role="fieldcontain">
	<label for="pass">Password</label>
	<input name="pass" id="pass" type="password">
	<p class="hint">This is the password of your <i>Viessmann Developer Portal</i> account.</p>
</div>

<div data-role="fieldcontain">
	<label for="apikey">Api-Key</label>
	<input name="apikey" id="apikey" type="text" />
	<p class="hint">This is the Api-Key created with the <i>Viessmann Developer Portal</i></p>
</div>

<div class="wide">Data transmission to Miniserver</div>

<fieldset data-role="controlgroup">
	<input type="checkbox" name="Cron.enabled" id="Cron.enabled" class="refreshdisplay">
	<label for="Cron.enabled">Enable Cron Job</label>
	<p class="hint">If you enable this setting, you can define how often the data will be fetched automatically. If you disable the setting you have to trigger manually (see Query links and data) </p>
</fieldset>

<div data-role="fieldcontain" style="display:none" class="cronhidden">
	<label for="Cron.interval">Interval</label>
	<select name="Cron.interval" id="Cron.interval">
		<option value="01min">1 minute</option>
		<option value="03min">3 minutes</option>
		<option value="05min">5 minutes</option>
		<option value="10min">10 minutes</option>
		<option value="15min">15 minutes</option>
		<option value="30min">30 minutes</option>
		<option value="hourly">60 minutes</option>
	</select>
	<p class="hint">Select the time interval how often data from Viessmann is automatically refreshed</span>.</p>
</div>

<p><i>Please activate just one transmission protocol.</i></p>

<!-- MQTT --> 

<fieldset data-role="controlgroup">
	<input type="checkbox" name="MQTT.enabled" id="MQTT.enabled" class="refreshdisplay">
	<label for="MQTT.enabled">Enable to use MQTT for data transfer</label>
	<p class="hint">If you locally have the MQTT Gateway plugin installed, leave Broker host and credentials empty. The Vitoconnect plugin then automatically collects your settings from the MQTT Gateway plugin (not shown in this form). </p>
</fieldset>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.topic">Base topic</label>
	<input name="MQTT.topic" id="MQTT.topic" type="text">
	<p class="hint">This is the base topic, the plugin publishes it's data to. Subscribe for <span class="mono">basetopic/#</span>. Default (if empty) is <span class="mono">vitoconnect</span>.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.host">Broker Hostname:Port</label>
	<input name="MQTT.host" id="MQTT.host" type="text">
	<p class="hint">Example: mybroker:1883. Leave this empty, if your settings from the MQTT Gateway plugin should be used.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.user">Broker Username</label>
	<input name="MQTT.user" id="MQTT.user" type="text">
	<p class="hint">This is the username of your <i>MQTT broker</i>. Leave this empty, if your settings from the MQTT Gateway plugin should be used, or you have enabled anonymous access.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthidden">
	<label for="MQTT.pass">Broker Password</label>
	<input name="MQTT.pass" id="MQTT.pass" type="password">
	<p class="hint">This is the password of your <i>MQTT broker</i>. Leave this empty, if your settings from the MQTT Gateway plugin should be used, or you have enabled anonymous access.</p>
</div>


<!-- Loxone HTTP --> 

<fieldset data-role="controlgroup">
	<input type="checkbox" name="Loxone.enabled" id="Loxone.enabled" class="refreshdisplay">
	<label for="Loxone.enabled">Enable to use HTTP transfer to Miniserver VI's/VTI's</label>
	<p class="hint">This option enables direct pushes to virtual inputs on the Miniserver. VIs need to be named exactly as shown on the <i>Query links and data</i> tab.</p>
</fieldset>

<div class="ui-grid-b loxonehidden" style="display:none;" >
	<div class="ui-block-a" style="line-height: 92px;">
		Miniserver to send
	</div>
	<div class="ui-block-b">

<?php

echo LBWeb::mslist_select_html( [ FORMID => 'Loxone.msnr', DATA_MINI => '0' ] );

?>
	</div>
	<div class="ui-block-b">
	</div>
</div>

<style>
/* Custom indentations are needed because the length of custom labels differs from
   the length of the standard labels */
.custom-size-flipswitch.ui-flipswitch .ui-btn.ui-flipswitch-on {
    text-indent: -5.9em;
}
.custom-size-flipswitch.ui-flipswitch .ui-flipswitch-off {
    text-indent: 0.5em;
}
/* Custom widths are needed because the length of custom labels differs from
   the length of the standard labels */
.custom-size-flipswitch.ui-flipswitch {
    width: 8.875em;
}
.custom-size-flipswitch.ui-flipswitch.ui-flipswitch-active {
    padding-left: 7em;
    width: 1.875em;
}
@media (min-width: 28em) {
    /*Repeated from rule .ui-flipswitch above*/
    .ui-field-contain > label + .custom-size-flipswitch.ui-flipswitch {
        width: 1.875em;
    }
}
</style>

<div class="ui-grid-b loxonehidden" style="display:none">
	<div class="ui-block-a">
		<label for="Loxone.cachedisabled">Use LoxBerry's cache</label>
	</div>
	<div class="ui-block-b">
		<input type="checkbox" name="Loxone.cachedisabled" id="Loxone.cachedisabled" data-role="flipswitch" data-off-text="Cache" data-on-text="No Cache" data-wrapper-class="custom-size-flipswitch">
	</div>
	<div class="ui-block-c">
		<p class="hint">This setting by default should stay on <b>Cache</b>. LoxBerry's cache prevents re-transmitting the same data multiple times (to reduce Miniserver load). During setup of your VI's/VTI's you may temporarily switch to <b>No Cache</b> to check your configuration.</p>
	</div>
</div>


</form>
<!-- End of form -->
<hr>

<div style="display:flex;align-items:center;justify-content:center;height:16px;min-height:16px">
	<span id="savemessages"></span>
</div>
<div style="display:flex;align-items:center;justify-content:center;">
	<button class="ui-btn ui-btn-icon-right" id="saveapply" data-inline="true">Save and Apply</button>
</div>

<div id="jsonconfig" style="display:none">
<?php
$configjson = file_get_contents(CONFIGFILE);
if( !empty($configjson) or !empty( json_decode($configjson) ) ) {
	echo $configjson;
} else {
	echo "{}";
}
?>
</div>

<?php
LBWeb::lbfooter();
?>

<script>

var config;

$( document ).ready(function() {

	config = JSON.parse( $("#jsonconfig").text() );
	formFill();
	viewhide();
	
	
	$(".refreshdisplay").click(function(){ viewhide(); });
	$("#saveapply").click(function(){ saveapply(); });
	$("#saveapply").blur(function(){ 
		$("#savemessages").html("");
	});
	

});


function viewhide()
{
	if( $("#MQTT\\.enabled").is(":checked") ) {
		$(".mqtthidden").fadeIn();
	} else {
		$(".mqtthidden").fadeOut();
	}
	
	if( $("#Loxone\\.enabled").is(":checked") ) {
		$(".loxonehidden").fadeIn();
	} else {
		$(".loxonehidden").fadeOut();
	}
	
	if( $("#Cron\\.enabled").is(":checked") ) {
		$(".cronhidden").fadeIn();
	} else {
		$(".cronhidden").fadeOut();
	}
}

function formFill()
{
	if (typeof user !== 'undefined') $("#user").val( config.user );
	if (typeof pass !== 'undefined') $("#pass").val( config.pass );
	if (typeof apikey !== 'undefined') $("#apikey").val( config.apikey );
	
		if( typeof config.Cron !== 'undefined') {
		if (typeof config.Cron.enabled !== 'undefined') $("#Cron\\.enabled").prop('checked', config.Cron.enabled).checkboxradio('refresh');
		if (typeof config.Cron.interval !== 'undefined') $("#Cron\\.interval").val( config.Cron.interval).selectmenu("refresh", true);
	}
	
	if( typeof config.MQTT !== 'undefined') {
		if (typeof config.MQTT.enabled !== 'undefined') $("#MQTT\\.enabled").prop('checked', config.MQTT.enabled).checkboxradio('refresh');
		if (typeof config.MQTT.topic !== 'undefined') $("#MQTT\\.topic").val( config.MQTT.topic );
		if (typeof config.MQTT.host !== 'undefined') $("#MQTT\\.host").val( config.MQTT.host );
		if (typeof config.MQTT.user !== 'undefined') $("#MQTT\\.user").val( config.MQTT.user );
		if (typeof config.MQTT.pass !== 'undefined') $("#MQTT\\.pass").val( config.MQTT.pass );		
	}
	
	if( typeof config.Loxone !== 'undefined') {
		if (typeof config.Loxone.enabled !== 'undefined') $("#Loxone\\.enabled").prop('checked', config.Loxone.enabled).checkboxradio('refresh');
		if (typeof config.Loxone.msnr !== 'undefined') $("#Loxone\\.msnr").val( config.Loxone.msnr ).selectmenu("refresh", true);
		if (typeof config.Loxone.cachedisabled !== 'undefined') $("#Loxone\\.cachedisabled").prop('checked', config.Loxone.cachedisabled).flipswitch('refresh');
	}
}

function saveapply() 
{
	$("#savemessages").html("Submitting...");
	$("#savemessages").css("color", "grey");
	
	$.post( "ajax-handler.php?action=saveconfig", $( "#form" ).serialize() )
	.done(function( data ) {
		console.log("Done:", data);
		$("#savemessages").html("Saved successfully");
		$("#savemessages").css("color", "green");
		
		config = data;
		formFill();
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
		$("#savemessages").html("Error "+error.status+": "+error.responseJSON.error);
		$("#savemessages").css("color", "red");
		
	});
}



</script>
