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
	font-family: monospace;
	font-size: 105%;
	font-weight: bold;
	color: #007000;
}
.card-box {
	background: #fdfdfd;
	border: 1px solid #d0d0d0;
	border-radius: 6px;
	padding: 15px;
	margin-bottom: 20px;
}
.badge-ok {
	background: #28a745;
	color: white;
	padding: 3px 8px;
	border-radius: 4px;
	font-size: 85%;
	font-weight: bold;
}
.badge-warn {
	background: #ffc107;
	color: #212529;
	padding: 3px 8px;
	border-radius: 4px;
	font-size: 85%;
	font-weight: bold;
}
.badge-err {
	background: #dc3545;
	color: white;
	padding: 3px 8px;
	border-radius: 4px;
	font-size: 85%;
	font-weight: bold;
}
.stat-label {
	font-weight: bold;
	color: #555;
}
</style>

<!-- STATUS & SERVICE CARD -->
<div class="card-box">
	<div class="wide">Dienst- und Bibliotheks-Status</div>
	<div class="ui-grid-b" style="margin-top: 10px;">
		<div class="ui-block-a">
			<span class="stat-label">Hintergrunddienst:</span><br>
			<span id="daemon-status-badge"><span class="badge-warn">Prüfe...</span></span>
			<div style="margin-top: 8px;">
				<button class="ui-btn ui-btn-inline ui-mini" id="btn-daemon-restart">Dienst neustarten</button>
			</div>
		</div>
		<div class="ui-block-b">
			<span class="stat-label">PyViCare Bibliothek:</span><br>
			Installiert: <b id="pyvicare-curr">-</b><br>
			PyPI Version: <b id="pyvicare-pypi">-</b>
			<div style="margin-top: 8px;">
				<button class="ui-btn ui-btn-inline ui-mini" id="btn-pyvicare-update">PyViCare aktualisieren</button>
			</div>
		</div>
		<div class="ui-block-c">
			<span class="stat-label">Viessmann API Quota:</span><br>
			Aufrufe (24h): <b id="quota-calls">-</b> / 1.450<br>
			Verbleibend: <b id="quota-remaining">-</b>
			<p class="hint" style="margin-top: 5px;">Kostenloses Limit: max. 1.450 Calls / 24 Std.</p>
		</div>
	</div>
	<div id="service-msg" style="margin-top: 10px; font-weight: bold;"></div>
</div>

<!-- FORM SETTINGS -->
<form id="form" onsubmit="return false;">

<div class="wide">Viessmann Account & API</div>
<p><i>Verbindet sich mit der Viessmann Developer API über die moderne <b>PyViCare</b> Bibliothek mit automatischem OAuth2 Token-Refresh.</i></p>

<div data-role="fieldcontain">
	<label for="user">E-Mail (Benutzername)</label>
	<input name="user" id="user" type="text" />
	<p class="hint">Deine E-Mail-Adresse des <i>Viessmann Developer Portal</i> bzw. ViCare Accounts.</p>
</div>

<div data-role="fieldcontain">
	<label for="pass">Passwort</label>
	<input name="pass" id="pass" type="password">
	<p class="hint">Das Passwort deines Viessmann Accounts.</p>
</div>

<div data-role="fieldcontain">
	<label for="apikey">Client-ID (API-Key)</label>
	<input name="apikey" id="apikey" type="text" />
	<p class="hint">Die Client-ID aus dem <a href="https://developer.viessmann.com/" target="_blank">Viessmann Developer Portal</a>. (Redirect URI muss dort auf <span class="mono">http://localhost:4200/</span> oder Standard gesetzt sein).</p>
</div>

<div class="wide">Abfrage & Steuerung</div>

<div data-role="fieldcontain">
	<label for="Cron.interval">Abfrage-Intervall</label>
	<select name="Cron.interval" id="Cron.interval">
		<option value="60">60 Sekunden (~1.440 Calls / Tag)</option>
		<option value="120" selected>120 Sekunden (~720 Calls / Tag - Empfohlen)</option>
		<option value="180">3 Minuten (~480 Calls / Tag)</option>
		<option value="300">5 Minuten (~288 Calls / Tag)</option>
		<option value="600">10 Minuten (~144 Calls / Tag)</option>
		<option value="900">15 Minuten (~96 Calls / Tag)</option>
	</select>
	<p class="hint">Bestimmt, wie oft neue Werte von Viessmann abgerufen werden. <b>Empfehlung:</b> Mindestens 60-120 Sek., um das API-Tageslimit nicht zu überschreiten.</p>
</div>

<div class="wide">MQTT Datenübertragung & Bidirektionale Steuerung</div>

<fieldset data-role="controlgroup">
	<input type="checkbox" name="MQTT.enabled" id="MQTT.enabled" class="refreshdisplay" checked>
	<label for="MQTT.enabled">MQTT aktivieren (Empfohlen)</label>
	<p class="hint">Überträgt Werte bei Änderung (Change-Detection) an den MQTT Broker und erlaubt Steuerung via MQTT.</p>
</fieldset>

<div data-role="fieldcontain" class="mqtthidden">
	<label for="MQTT.topic">Basis-Topic</label>
	<input name="MQTT.topic" id="MQTT.topic" type="text" value="vitoconnect">
	<p class="hint">Standard: <span class="mono">vitoconnect</span>. Der Miniserver abonniert <span class="mono">vitoconnect/#</span>. Befehle können an <span class="mono">vitoconnect/set/...</span> gesendet werden.</p>
</div>

<fieldset data-role="controlgroup" class="mqtthidden">
	<input type="checkbox" name="MQTT.use_gateway" id="MQTT.use_gateway" class="refreshdisplay" checked>
	<label for="MQTT.use_gateway">LoxBerry MQTT Gateway Einstellungen automatisch nutzen</label>
	<p class="hint">Wenn aktiv, werden Broker-Host, Port und Zugangsdaten direkt aus dem LoxBerry MQTT Gateway übernommen.</p>
</fieldset>

<div data-role="fieldcontain" style="display:none" class="mqtthosthidden">
	<label for="MQTT.host">Eigener Broker Host:Port</label>
	<input name="MQTT.host" id="MQTT.host" type="text" placeholder="127.0.0.1:1883">
	<p class="hint">Nur angeben, wenn ein externer Broker verwendet werden soll.</p>
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthosthidden">
	<label for="MQTT.user">Broker Benutzer</label>
	<input name="MQTT.user" id="MQTT.user" type="text">
</div>

<div data-role="fieldcontain" style="display:none" class="mqtthosthidden">
	<label for="MQTT.pass">Broker Passwort</label>
	<input name="MQTT.pass" id="MQTT.pass" type="password">
</div>

</form>

<hr>

<div style="display:flex;align-items:center;justify-content:center;height:24px;min-height:24px">
	<span id="savemessages"></span>
</div>
<div style="display:flex;align-items:center;justify-content:center;">
	<button class="ui-btn ui-btn-icon-right ui-btn-b" id="saveapply" data-inline="true">Einstellungen speichern & anwenden</button>
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
var config = {};

$(document).ready(function() {
	try {
		config = JSON.parse($("#jsonconfig").text());
	} catch(e) {
		config = {};
	}
	formFill();
	viewhide();
	checkPyViCareStatus();
	checkStats();

	$(".refreshdisplay").click(function(){ viewhide(); });
	$("#saveapply").click(function(){ saveapply(); });
	$("#btn-daemon-restart").click(function(){ restartDaemon(); });
	$("#btn-pyvicare-update").click(function(){ updatePyViCare(); });
});

function viewhide() {
	if ($("#MQTT\\.enabled").is(":checked")) {
		$(".mqtthidden").fadeIn();
		if ($("#MQTT\\.use_gateway").is(":checked")) {
			$(".mqtthosthidden").fadeOut();
		} else {
			$(".mqtthosthidden").fadeIn();
		}
	} else {
		$(".mqtthidden").fadeOut();
		$(".mqtthosthidden").fadeOut();
	}
}

function formFill() {
	if (config.user) $("#user").val(config.user);
	if (config.pass) $("#pass").val(config.pass);
	if (config.apikey) $("#apikey").val(config.apikey);

	if (config.Cron && config.Cron.interval) {
		$("#Cron\\.interval").val(config.Cron.interval).selectmenu("refresh", true);
	}

	if (config.MQTT) {
		if (typeof config.MQTT.enabled !== 'undefined') {
			var en = (config.MQTT.enabled === true || config.MQTT.enabled === "true" || config.MQTT.enabled === "1");
			$("#MQTT\\.enabled").prop('checked', en).checkboxradio('refresh');
		}
		if (typeof config.MQTT.use_gateway !== 'undefined') {
			var ug = (config.MQTT.use_gateway === true || config.MQTT.use_gateway === "true" || config.MQTT.use_gateway === "1");
			$("#MQTT\\.use_gateway").prop('checked', ug).checkboxradio('refresh');
		}
		if (config.MQTT.topic) $("#MQTT\\.topic").val(config.MQTT.topic);
		if (config.MQTT.host) $("#MQTT\\.host").val(config.MQTT.host);
		if (config.MQTT.user) $("#MQTT\\.user").val(config.MQTT.user);
		if (config.MQTT.pass) $("#MQTT\\.pass").val(config.MQTT.pass);
	}
}

function checkPyViCareStatus() {
	$.getJSON("ajax-handler.php?action=pyvicare_status")
	.done(function(data) {
		$("#pyvicare-curr").text(data.installed_version || "Nicht installiert");
		$("#pyvicare-pypi").text(data.latest_pypi_version || "Unbekannt");

		if (data.daemon_running) {
			$("#daemon-status-badge").html('<span class="badge-ok">Aktiv & Läuft</span>');
		} else {
			$("#daemon-status-badge").html('<span class="badge-err">Gestoppt</span>');
		}

		if (data.update_available) {
			$("#btn-pyvicare-update").addClass("ui-btn-b").text("Update verfügbar: " + data.latest_pypi_version);
		} else {
			$("#btn-pyvicare-update").text("PyViCare ist aktuell");
		}
	})
	.fail(function() {
		$("#pyvicare-curr").text("Statusfehler");
	});
}

function checkStats() {
	$.getJSON("ajax-handler.php?action=get_stats")
	.done(function(data) {
		if (data.calls_24h !== undefined) {
			$("#quota-calls").text(data.calls_24h);
			$("#quota-remaining").text(data.remaining_24h);
		}
	});
}

function restartDaemon() {
	$("#service-msg").css("color", "blue").text("Starte Dienst neu...");
	$.getJSON("ajax-handler.php?action=daemon_control&cmd=restart")
	.done(function(res) {
		$("#service-msg").css("color", "green").text("Dienst neugestartet.");
		setTimeout(checkPyViCareStatus, 2000);
	})
	.fail(function() {
		$("#service-msg").css("color", "red").text("Fehler beim Neustart des Dienstes.");
	});
}

function updatePyViCare() {
	$("#service-msg").css("color", "blue").text("Aktualisiere PyViCare im Hintergrund (pip install --upgrade)... Bitte warten.");
	$("#btn-pyvicare-update").addClass("ui-disabled");

	$.getJSON("ajax-handler.php?action=pyvicare_upgrade")
	.done(function(res) {
		if (res.success) {
			$("#service-msg").css("color", "green").text("PyViCare erfolgreich auf Version " + res.version + " aktualisiert!");
		} else {
			$("#service-msg").css("color", "red").text("Upgrade fehlgeschlagen: " + (res.error || "Unbekannter Fehler"));
		}
		$("#btn-pyvicare-update").removeClass("ui-disabled");
		checkPyViCareStatus();
	})
	.fail(function() {
		$("#service-msg").css("color", "red").text("Netzwerkfehler beim Aktualisieren.");
		$("#btn-pyvicare-update").removeClass("ui-disabled");
	});
}

function saveapply() {
	$("#savemessages").html("Speichere Konfiguration...");
	$("#savemessages").css("color", "grey");

	$.post("ajax-handler.php?action=saveconfig", $("#form").serialize())
	.done(function(data) {
		$("#savemessages").html("Erfolgreich gespeichert & Dienst neugestartet!");
		$("#savemessages").css("color", "green");
		config = data;
		formFill();
		setTimeout(checkPyViCareStatus, 2000);
	})
	.fail(function(error) {
		$("#savemessages").html("Fehler beim Speichern: " + (error.responseJSON ? error.responseJSON.error : "Serverfehler"));
		$("#savemessages").css("color", "red");
	});
}
</script>
