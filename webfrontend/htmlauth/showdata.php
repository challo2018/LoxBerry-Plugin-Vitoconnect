<?php

require_once "loxberry_web.php";
require_once "defines.php";

$navbar[1]['active'] = null;
$navbar[2]['active'] = True;
$navbar[3]['active'] = null;

$L = LBSystem::readlanguage("language.ini");
$template_title = "Viessmann Vitoconnect Gateway";
$helplink = "https://wiki.loxberry.de/plugins/vitoconnect/start";
$helptemplate = "help.html";

LBWeb::lbheader($template_title, $helplink, $helptemplate);

if (!file_exists(CONFIGFILE)) {
	echo "<p>Bitte zuerst die Einstellungen konfigurieren und speichern.</p>";
	LBWeb::lbfooter();
	exit();
}

$configdata = file_get_contents(CONFIGFILE);
$config = json_decode($configdata);
$mqtttopic = !empty($config->MQTT->topic) ? $config->MQTT->topic : "vitoconnect";

?>

<style>
.mono {
	font-family: monospace;
	font-size: 105%;
	font-weight: bold;
	color: #007000;
}
.topic-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
}
.topic-table th, .topic-table td {
	border: 1px solid #d0d0d0;
	padding: 8px 12px;
	text-align: left;
}
.topic-table th {
	background-color: #6dac20;
	color: white;
}
.card-box {
	background: #fdfdfd;
	border: 1px solid #d0d0d0;
	border-radius: 6px;
	padding: 15px;
	margin-bottom: 20px;
}
</style>

<div class="card-box">
	<div class="wide">Live-Status & API-Zähler</div>
	<div id="live-stats-box" style="margin-top: 10px;">
		Lade aktuellen Status...
	</div>
</div>

<div class="wide">MQTT Topics & Loxone Einbindung</div>
<p>
	Das Plugin sendet alle Werte strukturiert über MQTT. Im Loxone Miniserver können diese über das <b>LoxBerry MQTT Gateway</b> (oder direkt über Loxone MQTT) als <i>Virtuelle Eingänge</i> empfangen werden.
</p>

<h3>1. Wichtigste Sensor- und Status-Topics (Lesen)</h3>
<table class="topic-table">
	<thead>
		<tr>
			<th>MQTT Topic</th>
			<th>Beschreibung</th>
			<th>Beispielwert</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/temperature/outside</span></td>
			<td>Außentemperatur (°C)</td>
			<td>12.4</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/temperature/boiler</span></td>
			<td>Kessel- / Vorlauftemperatur (°C)</td>
			<td>45.0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/dhw/temperature</span></td>
			<td>Warmwassertemperatur (°C)</td>
			<td>52.5</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/dhw/target_temperature</span></td>
			<td>Warmwasser-Solltemperatur (°C)</td>
			<td>55.0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/dhw/one_time_charge</span></td>
			<td>Einmalige Warmwasserbereitung aktiv</td>
			<td>0 oder 1</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/burner/active</span></td>
			<td>Brenner aktiv</td>
			<td>0 oder 1</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/burner/hours</span></td>
			<td>Brenner-Betriebsstunden (h)</td>
			<td>3450</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/circuit_0/normal_temperature</span></td>
			<td>Heizkreis 0: Normal-Solltemperatur (°C)</td>
			<td>21.0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/circuit_0/mode</span></td>
			<td>Heizkreis 0: Betriebsmodus</td>
			<td>dhwAndHeating, standby, dhw</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/errors/count</span></td>
			<td>Anzahl aktiver Fehlermeldungen</td>
			<td>0</td>
		</tr>
	</tbody>
</table>

<h3 style="margin-top: 25px;">2. Steuerbefehle an Viessmann senden (Schreiben)</h3>
<p>
	Um Werte von Loxone an Viessmann zu übergeben, sende eine MQTT-Nachricht an das entsprechende <b>set-Topic</b> (z. B. über das MQTT Gateway oder einen Virtuellen Ausgangsbefehl):
</p>
<table class="topic-table">
	<thead>
		<tr>
			<th>Befehl / MQTT Topic</th>
			<th>Erlaubte Werte</th>
			<th>Beschreibung</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/normal_temperature</span></td>
			<td>Temperatur (z. B. <span class="mono">21.5</span>)</td>
			<td>Setzt die normale Raum-Solltemperatur für Heizkreis 0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/comfort_temperature</span></td>
			<td>Temperatur (z. B. <span class="mono">23.0</span>)</td>
			<td>Setzt die Komfort-Temperatur für Heizkreis 0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/reduced_temperature</span></td>
			<td>Temperatur (z. B. <span class="mono">17.0</span>)</td>
			<td>Setzt die reduzierte Temperatur (Absenkung) für Heizkreis 0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/mode</span></td>
			<td><span class="mono">standby</span>, <span class="mono">dhw</span>, <span class="mono">dhwAndHeating</span></td>
			<td>Ändert die Betriebsart von Heizkreis 0</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/dhw/target_temperature</span></td>
			<td>Temperatur (z. B. <span class="mono">55.0</span>)</td>
			<td>Setzt die Warmwasser-Solltemperatur</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/dhw/one_time_charge</span></td>
			<td><span class="mono">start</span> (oder <span class="mono">1</span>) / <span class="mono">stop</span> (oder <span class="mono">0</span>)</td>
			<td>Startet oder stoppt die einmalige Warmwasseraufheizung</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/poll</span></td>
			<td>beliebig (z. B. <span class="mono">1</span>)</td>
			<td>Löst sofort eine manuelle Abfrage bei Viessmann aus</td>
		</tr>
	</tbody>
</table>

<?php
LBWeb::lbfooter();
?>

<script>
$(document).ready(function() {
	loadLiveStats();
});

function loadLiveStats() {
	$.getJSON("ajax-handler.php?action=get_stats")
	.done(function(data) {
		var statusText = data.is_running ? "<span style='color:green;font-weight:bold;'>Online & Aktiv</span>" : "<span style='color:red;font-weight:bold;'>Gestoppt</span>";
		var html = "<p><b>Dienststatus:</b> " + statusText + "<br>" +
				   "<b>Letzte Aktualisierung:</b> " + (data.timestamp || "-") + "<br>" +
				   "<b>Erkannte Geräte:</b> " + (data.devices_count !== undefined ? data.devices_count : "-") + "<br>" +
				   "<b>API-Aufrufe (24h):</b> " + (data.calls_24h !== undefined ? data.calls_24h : "-") + " / 1.450 " +
				   "(Verbleibend: <b>" + (data.remaining_24h !== undefined ? data.remaining_24h : "-") + "</b>)</p>";
		$("#live-stats-box").html(html);
	})
	.fail(function() {
		$("#live-stats-box").html("<p style='color:grey;'>Keine Live-Statistikdaten verfügbar. Prüfe, ob der Dienst gestartet wurde.</p>");
	});
}
</script>
