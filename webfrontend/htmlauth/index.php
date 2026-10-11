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

// 4th argument true = nojqm (activates LoxBerry DS components.css)
LBWeb::lbheader($template_title, $helplink, $helptemplate, true);

?>

<div class="lb-content">

<style>
.mono {
	font-family: monospace;
	font-size: 105%;
	font-weight: bold;
	color: #007000;
}
.card-box {
	background: #ffffff;
	border: 1px solid #dcdde1;
	border-radius: 8px;
	padding: 18px 20px;
	margin-bottom: 22px;
	box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.card-header {
	font-size: 1.15rem;
	font-weight: 600;
	color: #2f3640;
	margin: 0 0 14px 0;
	padding-bottom: 8px;
	border-bottom: 2px solid #f1f2f6;
}
.stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
	gap: 14px;
	margin-top: 10px;
}
.stat-card {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	padding: 12px 14px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.stat-label {
	font-size: 0.85rem;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-weight: 700;
	color: #718096;
	margin-bottom: 6px;
}
.stat-value {
	font-size: 1.05rem;
	font-weight: bold;
	color: #2d3748;
}
.badge-ok {
	background: #10b981;
	color: #ffffff;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 0.85rem;
	font-weight: 600;
	display: inline-block;
}
.badge-warn {
	background: #f59e0b;
	color: #ffffff;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 0.85rem;
	font-weight: 600;
	display: inline-block;
}
.badge-err {
	background: #ef4444;
	color: #ffffff;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 0.85rem;
	font-weight: 600;
	display: inline-block;
}
@media (max-width: 640px) {
	.card-box {
		padding: 14px;
	}
	.card-header {
		font-size: 1.05rem;
	}
	.stats-grid {
		grid-template-columns: 1fr;
		gap: 10px;
	}
}
</style>

<!-- STATUS & SERVICE CARD -->
<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Dienst- und Bibliotheks-Status</h4>
	<div class="stats-grid">
		<div class="stat-card">
			<div>
				<div class="stat-label">Hintergrunddienst</div>
				<div class="stat-value" id="daemon-status-badge"><span class="badge-warn">Prüfe...</span></div>
			</div>
			<div style="margin-top: 12px;">
				<button class="lb-btn lb-btn-sm" id="btn-daemon-restart">Dienst neustarten</button>
			</div>
		</div>

		<div class="stat-card">
			<div>
				<div class="stat-label">PyViCare Bibliothek</div>
				<div class="stat-value">
					Installiert: <span id="pyvicare-curr">-</span><br>
					<small style="color: #666; font-weight: normal;">PyPI: <span id="pyvicare-pypi">-</span></small>
				</div>
			</div>
			<div style="margin-top: 12px;">
				<button class="lb-btn lb-btn-sm" id="btn-pyvicare-update">PyViCare aktualisieren</button>
			</div>
		</div>

		<div class="stat-card">
			<div>
				<div class="stat-label">Viessmann API Quota</div>
				<div class="stat-value">
					Aufrufe: <span id="quota-calls">-</span> / 1.450<br>
					<small style="color: green; font-weight: normal;">Verbleibend: <b id="quota-remaining">-</b></small>
				</div>
			</div>
			<p class="lb-form-help hint" style="margin: 8px 0 0 0; font-size: 75%;">Max. 1.450 Calls / 24 Std.</p>
		</div>
	</div>
	<div id="service-msg" style="margin-top: 12px; font-weight: bold;"></div>
</div>

<!-- FORM SETTINGS -->
<form id="form" onsubmit="return false;">

<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Viessmann Account & API</h4>
	<p><i>Verbindet sich mit der Viessmann Developer API über die moderne <b>PyViCare</b> Bibliothek mit automatischem OAuth2 Token-Refresh.</i></p>

	<div class="lb-form-row">
		<label class="lb-form-label" for="user">E-Mail (Benutzername)</label>
		<div class="lb-form-field">
			<input class="lb-input" name="user" id="user" type="text" placeholder="name@example.com" />
		</div>
	</div>
	<div class="lb-form-help hint">Deine E-Mail-Adresse des <i>Viessmann Developer Portal</i> bzw. ViCare Accounts.</div>

	<div class="lb-form-row" style="margin-top: 12px;">
		<label class="lb-form-label" for="pass">Passwort</label>
		<div class="lb-form-field">
			<input class="lb-input" name="pass" id="pass" type="password">
		</div>
	</div>
	<div class="lb-form-help hint">Das Passwort deines Viessmann Accounts.</div>

	<div class="lb-form-row" style="margin-top: 12px;">
		<label class="lb-form-label" for="apikey">Client-ID (API-Key)</label>
		<div class="lb-form-field">
			<input class="lb-input" name="apikey" id="apikey" type="text" />
		</div>
	</div>
	<div class="lb-form-help hint">Die Client-ID aus dem <a href="https://developer.viessmann.com/" target="_blank">Viessmann Developer Portal</a>. <b>Wichtig:</b> Redirect URI dort exakt auf <span class="mono">vicare://oauth-callback/everest</span> setzen und <i>Google reCAPTCHA</i> auf <b>Disabled</b> stellen.</div>
</div>

<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Abfrage-Intervall</h4>

	<div class="lb-form-row">
		<label class="lb-form-label" for="Cron.interval">Intervall</label>
		<div class="lb-form-field">
			<select class="lb-select" name="Cron.interval" id="Cron.interval">
				<option value="60">60 Sekunden (~1.440 Calls / Tag)</option>
				<option value="120" selected>120 Sekunden (~720 Calls / Tag - Empfohlen)</option>
				<option value="180">3 Minuten (~480 Calls / Tag)</option>
				<option value="300">5 Minuten (~288 Calls / Tag)</option>
				<option value="600">10 Minuten (~144 Calls / Tag)</option>
				<option value="900">15 Minuten (~96 Calls / Tag)</option>
			</select>
		</div>
	</div>
	<div class="lb-form-help hint">Bestimmt, wie oft neue Werte von Viessmann abgerufen werden. <b>Empfehlung:</b> Mindestens 60-120 Sek., um das API-Tageslimit nicht zu überschreiten.</div>
</div>

<div class="lb-card" style="margin-bottom: 20px;">
	<h4>MQTT Datenübertragung & Bidirektionale Steuerung</h4>

	<div class="lb-form-row">
		<label class="lb-form-label" for="MQTT.enabled">MQTT aktivieren</label>
		<div class="lb-form-field">
			<label class="lb-toggle">
				<input type="checkbox" name="MQTT.enabled" id="MQTT.enabled" class="refreshdisplay" checked>
				<span class="lb-toggle-slider"></span>
			</label>
		</div>
	</div>
	<div class="lb-form-help hint">Überträgt Werte bei Änderung (Change-Detection) an den MQTT Broker und erlaubt Steuerung via MQTT.</div>

	<div class="mqtthidden" style="margin-top: 14px;">
		<div class="lb-form-row">
			<label class="lb-form-label" for="MQTT.topic">Basis-Topic</label>
			<div class="lb-form-field">
				<input class="lb-input" name="MQTT.topic" id="MQTT.topic" type="text" value="vitoconnect">
			</div>
		</div>
		<div class="lb-form-help hint">Standard: <span class="mono">vitoconnect</span>. Der Miniserver abonniert <span class="mono">vitoconnect/#</span>. Befehle können an <span class="mono">vitoconnect/set/...</span> gesendet werden.</div>

		<div class="lb-form-row" style="margin-top: 14px;">
			<label class="lb-form-label" for="MQTT.use_gateway">LoxBerry MQTT Gateway</label>
			<div class="lb-form-field">
				<label class="lb-toggle">
					<input type="checkbox" name="MQTT.use_gateway" id="MQTT.use_gateway" class="refreshdisplay" checked>
					<span class="lb-toggle-slider"></span>
				</label>
			</div>
		</div>
		<div class="lb-form-help hint">Automatische Erkennung der Broker-Zugangsdaten aus LoxBerry (keine manuelle Eingabe nötig).</div>

		<div class="mqtthosthidden" style="display:none; margin-top: 14px;">
			<div class="lb-form-row">
				<label class="lb-form-label" for="MQTT.host">Eigener Broker Host:Port</label>
				<div class="lb-form-field">
					<input class="lb-input" name="MQTT.host" id="MQTT.host" type="text" placeholder="127.0.0.1:1883">
				</div>
			</div>
			<div class="lb-form-help hint">Nur angeben, wenn ein externer Broker verwendet werden soll.</div>

			<div class="lb-form-row" style="margin-top: 12px;">
				<label class="lb-form-label" for="MQTT.user">Broker Benutzer</label>
				<div class="lb-form-field">
					<input class="lb-input" name="MQTT.user" id="MQTT.user" type="text">
				</div>
			</div>

			<div class="lb-form-row" style="margin-top: 12px;">
				<label class="lb-form-label" for="MQTT.pass">Broker Passwort</label>
				<div class="lb-form-field">
					<input class="lb-input" name="MQTT.pass" id="MQTT.pass" type="password">
				</div>
			</div>
		</div>
	</div>
</div>

</form>

<div style="display:flex;align-items:center;justify-content:center;height:24px;min-height:24px;margin-top:10px;">
	<span id="savemessages"></span>
</div>
<div style="display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
	<button class="lb-btn lb-btn-primary" id="saveapply">Einstellungen speichern & anwenden</button>
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

</div><!-- /lb-content -->

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

function isTrue(val) {
	if (val === true || val === 1) return true;
	if (typeof val === 'string') {
		var s = val.toLowerCase().trim();
		return (s === 'true' || s === '1' || s === 'on' || s === 'yes');
	}
	return false;
}

function formFill() {
	if (config.user) $("#user").val(config.user);
	if (config.pass) $("#pass").val(config.pass);
	if (config.apikey) $("#apikey").val(config.apikey);

	if (config.Cron && config.Cron.interval) {
		$("#Cron\\.interval").val(config.Cron.interval);
	}

	if (config.MQTT) {
		var en = (typeof config.MQTT.enabled !== 'undefined') ? isTrue(config.MQTT.enabled) : true;
		$("#MQTT\\.enabled").prop('checked', en);

		var ug = (typeof config.MQTT.use_gateway !== 'undefined') ? isTrue(config.MQTT.use_gateway) : true;
		$("#MQTT\\.use_gateway").prop('checked', ug);

		if (config.MQTT.topic) $("#MQTT\\.topic").val(config.MQTT.topic);
		if (config.MQTT.host) $("#MQTT\\.host").val(config.MQTT.host);
		if (config.MQTT.user) $("#MQTT\\.user").val(config.MQTT.user);
		if (config.MQTT.pass) $("#MQTT\\.pass").val(config.MQTT.pass);
	} else {
		$("#MQTT\\.enabled").prop('checked', true);
		$("#MQTT\\.use_gateway").prop('checked', true);
	}
	viewhide();
}

function checkPyViCareStatus() {
	$.getJSON("ajax-handler.php?action=pyvicare_status")
	.done(function(data) {
		$("#pyvicare-curr").text(data.installed_version || "Nicht installiert");
		$("#pyvicare-pypi").text(data.latest_pypi_version || "Unbekannt");

		if (data.daemon_running) {
			$("#daemon-status-badge").html('<span class="badge-ok">Aktiv & Läuft</span>');
			$("#service-msg").html("");
		} else {
			$("#daemon-status-badge").html('<span class="badge-err">Gestoppt</span>');
			$.getJSON("ajax-handler.php?action=get_daemon_diagnostics")
			.done(function(diag) {
				if (!diag.is_running && diag.reason_text) {
					$("#service-msg").html("<span style='color:#c0392b;'>⚠️ <b>Hinweis:</b> " + escapeHtml(diag.reason_text) + "</span>");
				}
			});
		}

		if (data.update_available) {
			$("#btn-pyvicare-update").addClass("lb-btn-primary").text("Update verfügbar: " + data.latest_pypi_version);
		} else {
			$("#btn-pyvicare-update").removeClass("lb-btn-primary").text("PyViCare ist aktuell");
		}
	})
	.fail(function() {
		$("#pyvicare-curr").text("Statusfehler");
	});
}

function escapeHtml(text) {
	if (!text) return "";
	return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
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
		$("#service-msg").css("color", "green").text(res.message || "Dienst neugestartet.");
		setTimeout(checkPyViCareStatus, 2000);
	})
	.fail(function() {
		$("#service-msg").css("color", "red").text("Fehler beim Neustart des Dienstes.");
	});
}

function updatePyViCare() {
	$("#service-msg").css("color", "blue").text("Aktualisiere PyViCare im Hintergrund (pip install --upgrade)... Bitte warten.");
	$("#btn-pyvicare-update").prop("disabled", true).addClass("lb-disabled");

	$.getJSON("ajax-handler.php?action=pyvicare_upgrade")
	.done(function(res) {
		if (res.success) {
			$("#service-msg").css("color", "green").text("PyViCare erfolgreich auf Version " + res.version + " aktualisiert!");
		} else {
			$("#service-msg").css("color", "red").text("Upgrade fehlgeschlagen: " + (res.error || "Unbekannter Fehler"));
		}
		$("#btn-pyvicare-update").prop("disabled", false).removeClass("lb-disabled");
		checkPyViCareStatus();
	})
	.fail(function() {
		$("#service-msg").css("color", "red").text("Netzwerkfehler beim Aktualisieren.");
		$("#btn-pyvicare-update").prop("disabled", false).removeClass("lb-disabled");
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
