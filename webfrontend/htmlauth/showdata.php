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
.topic-table tr:nth-child(even) {
	background-color: #f9f9f9;
}
.card-box {
	background: #fdfdfd;
	border: 1px solid #d0d0d0;
	border-radius: 6px;
	padding: 15px;
	margin-bottom: 20px;
}
.badge-write {
	background: #17a2b8;
	color: white;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 80%;
	font-weight: bold;
}
.badge-read {
	background: #6c757d;
	color: white;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 80%;
	font-weight: bold;
}
.copy-btn {
	cursor: pointer;
	padding: 2px 6px;
	font-size: 80%;
	margin-left: 5px;
}
</style>

<!-- STATUS & HEADER -->
<div class="card-box">
	<div class="wide">Dienst-Status & Live-Zähler</div>
	<div class="ui-grid-b" style="margin-top: 10px;">
		<div class="ui-block-a">
			<b>Dienststatus:</b> <span id="stat-status">-</span><br>
			<b>Erkannte Geräte:</b> <span id="stat-devices">-</span>
		</div>
		<div class="ui-block-b">
			<b>API-Aufrufe (24h):</b> <span id="stat-calls">-</span> / 1.450<br>
			<b>Verbleibend:</b> <b id="stat-remaining" style="color:green;">-</b>
		</div>
		<div class="ui-block-c">
			<b>Letzte Abfrage:</b> <span id="stat-lastpoll">-</span><br>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-force-poll">Jetzt abfragen (Poll)</button>
		</div>
	</div>
	<div id="poll-msg" style="margin-top: 8px; font-weight: bold;"></div>
</div>

<!-- INTERAKTIVES TEST-PANEL (BEFEHLE SCHREIBEN) -->
<div class="card-box" style="border-left: 5px solid #17a2b8;">
	<div class="wide">Steuerung testen (Befehl an Viessmann senden)</div>
	<p>Hier kannst du Steuerbefehle direkt über MQTT an deine Heizung senden, um die Funktion vor der Einbindung in Loxone Config zu testen.</p>

	<div class="ui-grid-a">
		<div class="ui-block-a" style="padding-right: 10px;">
			<label for="test-target-select"><b>Ziel-Datenpunkt auswählen:</b></label>
			<select id="test-target-select">
				<option value="<?=$mqtttopic;?>/set/circuit_0/normal_temperature">Heizkreis 0: Normal-Sollwert (z.B. 21.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/comfort_temperature">Heizkreis 0: Komfort-Sollwert (z.B. 23.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/reduced_temperature">Heizkreis 0: Absenk-Sollwert (z.B. 17.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/mode">Heizkreis 0: Betriebsmodus (standby, dhw, dhwAndHeating)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/target_temperature">Warmwasser: Sollwert (z.B. 55.0)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/one_time_charge">Warmwasser: Einmalige Aufheizung (start / stop)</option>
			</select>
		</div>
		<div class="ui-block-b">
			<label for="test-target-val"><b>Zu sendender Wert:</b></label>
			<input type="text" id="test-target-val" placeholder="z. B. 21.5 oder dhwAndHeating">
		</div>
	</div>

	<div style="margin-top: 10px;">
		<button class="ui-btn ui-btn-inline ui-btn-b ui-mini" id="btn-send-command">Befehl jetzt senden</button>
		<span id="cmd-feedback" style="margin-left: 15px; font-weight: bold;"></span>
	</div>
</div>

<!-- LIVE-TABELLE DER VERFÜGBAREN DATENPUNKTE -->
<div class="wide">Live-Datenpunkte deiner Anlage</div>
<p>
	Diese Tabelle zeigt alle aktuell von deiner Viessmann-Anlage gemeldeten Datenpunkte mit aktuellem Live-Wert. 
	Nutze das Suchfeld, um nach bestimmten Werten zu filtern. Klicke auf <b>[Kopieren]</b>, um das Topic für Loxone in die Zwischenablage zu kopieren.
</p>

<div class="ui-grid-a" style="margin-bottom: 10px;">
	<div class="ui-block-a">
		<input type="text" id="datapoint-search" placeholder="Filter nach Beschreibung oder Topic (z. B. Temperatur, Brenner, HK0)...">
	</div>
	<div class="ui-block-b" style="text-align: right; line-height: 40px;">
		<label><input type="checkbox" id="auto-refresh-check" checked> Live-Aktualisierung alle 5s</label>
	</div>
</div>

<table class="topic-table" id="datapoints-table">
	<thead>
		<tr>
			<th style="width: 25%;">Beschreibung / Sensor</th>
			<th style="width: 20%;">Aktueller Wert</th>
			<th style="width: 10%;">Typ</th>
			<th style="width: 35%;">MQTT Topic (für Loxone)</th>
			<th style="width: 10%;">Zeitstempel</th>
		</tr>
	</thead>
	<tbody id="datapoints-body">
		<tr>
			<td colspan="5" style="text-align: center; color: grey;">Lade Datenpunkte...</td>
		</tr>
	</tbody>
</table>

<h3 style="margin-top: 30px;">Übersicht steuerbarer Befehle (Schreiben)</h3>
<p>Um diese Werte aus dem Loxone Miniserver zu ändern, sende eine Nachricht an das jeweilige <b>set-Topic</b>:</p>

<table class="topic-table">
	<thead>
		<tr>
			<th>Befehl / MQTT Topic</th>
			<th>Erlaubte Werte</th>
			<th>Wirkung</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/normal_temperature</span></td>
			<td>z. B. <span class="mono">20.5</span></td>
			<td>Setzt die normale Raum-Solltemperatur (HK 0)</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/comfort_temperature</span></td>
			<td>z. B. <span class="mono">22.0</span></td>
			<td>Setzt die Komfort-Temperatur (HK 0)</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/reduced_temperature</span></td>
			<td>z. B. <span class="mono">17.0</span></td>
			<td>Setzt die reduzierte Absenktemperatur (HK 0)</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/circuit_0/mode</span></td>
			<td><span class="mono">standby</span>, <span class="mono">dhw</span>, <span class="mono">dhwAndHeating</span></td>
			<td>Wechselt die Betriebsart (HK 0)</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/dhw/target_temperature</span></td>
			<td>z. B. <span class="mono">55.0</span></td>
			<td>Setzt die Warmwasser-Solltemperatur</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/set/dhw/one_time_charge</span></td>
			<td><span class="mono">start</span> (1) / <span class="mono">stop</span> (0)</td>
			<td>Aktiviert oder stoppt die einmalige Warmwasserbereitung</td>
		</tr>
		<tr>
			<td><span class="mono"><?=$mqtttopic;?>/poll</span></td>
			<td><span class="mono">1</span></td>
			<td>Löst einen sofortigen Abruf der API aus</td>
		</tr>
	</tbody>
</table>

<?php
LBWeb::lbfooter();
?>

<script>
var autoRefreshTimer = null;

$(document).ready(function() {
	loadStats();
	loadLiveValues();

	// Search filter
	$("#datapoint-search").on("keyup", function() {
		filterTable($(this).val().toLowerCase());
	});

	// Force Poll
	$("#btn-force-poll").click(function() {
		$("#poll-msg").css("color", "blue").text("Sende Poll-Anforderung an Daemon...");
		$.post("ajax-handler.php?action=send_command", { topic: "<?=$mqtttopic;?>/poll", value: "1" })
		.done(function() {
			$("#poll-msg").css("color", "green").text("Poll angefordert. Daten werden in Kürze aktualisiert.");
			setTimeout(function() {
				loadStats();
				loadLiveValues();
			}, 3000);
		})
		.fail(function() {
			$("#poll-msg").css("color", "red").text("Fehler beim Senden der Poll-Anforderung.");
		});
	});

	// Send Command Test
	$("#btn-send-command").click(function() {
		var topic = $("#test-target-select").val();
		var val = $("#test-target-val").val().trim();
		if (!val) {
			$("#cmd-feedback").css("color", "red").text("Bitte einen Wert eingeben!");
			return;
		}

		$("#cmd-feedback").css("color", "blue").text("Sende Befehl an MQTT...");
		$.post("ajax-handler.php?action=send_command", { topic: topic, value: val })
		.done(function(res) {
			$("#cmd-feedback").css("color", "green").text("Befehl gesendet: " + topic + " = " + val);
			setTimeout(function() {
				loadLiveValues();
			}, 4000);
		})
		.fail(function() {
			$("#cmd-feedback").css("color", "red").text("Fehler beim Senden des Befehls.");
		});
	});

	// Auto-refresh interval
	autoRefreshTimer = setInterval(function() {
		if ($("#auto-refresh-check").is(":checked")) {
			loadStats();
			loadLiveValues();
		}
	}, 5000);
});

function loadStats() {
	$.getJSON("ajax-handler.php?action=get_stats")
	.done(function(data) {
		$("#stat-status").html(data.is_running ? "<span style='color:green;font-weight:bold;'>Online</span>" : "<span style='color:red;font-weight:bold;'>Gestoppt</span>");
		$("#stat-devices").text(data.devices_count !== undefined ? data.devices_count : "-");
		$("#stat-calls").text(data.calls_24h !== undefined ? data.calls_24h : "-");
		$("#stat-remaining").text(data.remaining_24h !== undefined ? data.remaining_24h : "-");
		if (data.timestamp) {
			var d = new Date(data.timestamp);
			$("#stat-lastpoll").text(d.toLocaleTimeString());
		}
	});
}

function loadLiveValues() {
	$.getJSON("ajax-handler.php?action=get_live_values")
	.done(function(data) {
		var tbody = $("#datapoints-body");
		var keys = Object.keys(data);
		if (keys.length === 0) {
			tbody.html("<tr><td colspan='5' style='text-align: center; color: grey;'>Noch keine Live-Werte vorhanden. Bitte prüfen, ob der Dienst läuft und Anmeldedaten stimmen.</td></tr>");
			return;
		}

		var html = "";
		keys.sort();
		for (var i = 0; i < keys.length; i++) {
			var dp = data[keys[i]];
			var topic = dp.topic || keys[i];
			var val = dp.value;
			var unit = dp.unit ? " " + dp.unit : "";
			var desc = dp.description || topic;
			var writable = dp.writable;
			var typeBadge = writable ? "<span class='badge-write'>Lesen/Schreiben</span>" : "<span class='badge-read'>Nur Lesen</span>";
			var ts = dp.timestamp ? (new Date(dp.timestamp)).toLocaleTimeString() : "-";

			html += "<tr class='dp-row' data-search='" + (desc + " " + topic).toLowerCase() + "'>";
			html += "<td><b>" + escapeHtml(desc) + "</b></td>";
			html += "<td><span style='font-size:110%;font-weight:bold;'>" + escapeHtml(String(val)) + unit + "</span></td>";
			html += "<td>" + typeBadge + "</td>";
			html += "<td><span class='mono'>" + escapeHtml(topic) + "</span> <button class='ui-btn ui-btn-inline ui-mini copy-btn' onclick=\"copyTopic('" + escapeHtml(topic) + "')\">Kopieren</button></td>";
			html += "<td><small>" + ts + "</small></td>";
			html += "</tr>";
		}
		tbody.html(html);
		filterTable($("#datapoint-search").val().toLowerCase());
	});
}

function filterTable(query) {
	if (!query) {
		$(".dp-row").show();
		return;
	}
	$(".dp-row").each(function() {
		var txt = $(this).attr("data-search") || "";
		if (txt.indexOf(query) !== -1) {
			$(this).show();
		} else {
			$(this).hide();
		}
	});
}

function copyTopic(text) {
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text).then(function() {
			alert("Topic kopiert: " + text);
		});
	} else {
		var temp = $("<input>");
		$("body").append(temp);
		temp.val(text).select();
		document.execCommand("copy");
		temp.remove();
		alert("Topic kopiert: " + text);
	}
}

function escapeHtml(text) {
	if (!text) return "";
	return String(text)
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}
</script>
