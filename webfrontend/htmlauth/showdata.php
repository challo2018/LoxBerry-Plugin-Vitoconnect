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
	font-size: 100%;
	font-weight: bold;
	color: #007000;
	word-break: break-all;
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
.table-responsive {
	width: 100%;
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	margin-top: 10px;
}
.topic-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 90%;
}
.topic-table th, .topic-table td {
	border: 1px solid #e2e8f0;
	padding: 9px 12px;
	text-align: left;
}
.topic-table th {
	background-color: #f1f5f9;
	color: #334155;
	font-weight: 700;
}
.topic-table tr:nth-child(even) {
	background-color: #f8fafc;
}
.topic-table tr:hover {
	background-color: #f1f5f9;
}
.badge-write {
	background: #0284c7;
	color: white;
	padding: 2px 7px;
	border-radius: 10px;
	font-size: 78%;
	font-weight: 600;
	display: inline-block;
}
.badge-read {
	background: #64748b;
	color: white;
	padding: 2px 7px;
	border-radius: 10px;
	font-size: 78%;
	font-weight: 600;
	display: inline-block;
}
.copy-btn {
	cursor: pointer;
	padding: 2px 8px;
	font-size: 80%;
	margin-left: 6px;
}
.test-panel-grid {
	display: grid;
	grid-template-columns: 2fr 1fr auto;
	gap: 12px;
	align-items: end;
	margin-top: 10px;
}
@media (max-width: 768px) {
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
	.test-panel-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<!-- STATUS & HEADER -->
<div class="card-box">
	<h3 class="card-header">Dienst-Status & Live-Zähler</h3>
	<div class="stats-grid">
		<div class="stat-card">
			<div>
				<div class="stat-label">Dienststatus</div>
				<div class="stat-value" id="stat-status">-</div>
				<small style="color: #64748b;">Erkannte Geräte: <span id="stat-devices">-</span></small>
			</div>
		</div>

		<div class="stat-card">
			<div>
				<div class="stat-label">API-Aufrufe (24h)</div>
				<div class="stat-value"><span id="stat-calls">-</span> / 1.450</div>
				<small style="color: #16a34a;">Verbleibend: <b id="stat-remaining">-</b></small>
			</div>
		</div>

		<div class="stat-card">
			<div>
				<div class="stat-label">Letzte Abfrage</div>
				<div class="stat-value" id="stat-lastpoll">-</div>
			</div>
			<div style="margin-top: 10px;">
				<button class="ui-btn ui-btn-inline ui-mini" id="btn-force-poll">Jetzt abfragen (Poll)</button>
			</div>
		</div>
	</div>
	<div id="poll-msg" style="margin-top: 10px; font-weight: bold;"></div>
</div>

<!-- INTERAKTIVES TEST-PANEL (BEFEHLE SCHREIBEN) -->
<div class="card-box" style="border-left: 4px solid #0284c7;">
	<h3 class="card-header" style="color: #0369a1;">Steuerung testen (Befehl an Viessmann senden)</h3>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">Hier kannst du Steuerbefehle direkt über MQTT an deine Heizung senden, um die Funktion vor der Einbindung in Loxone Config zu testen.</p>

	<div class="test-panel-grid">
		<div>
			<label for="test-target-select" style="font-size: 85%; font-weight: bold;">Ziel-Datenpunkt auswählen:</label>
			<select id="test-target-select">
				<option value="<?=$mqtttopic;?>/set/circuit_0/normal_temperature">Heizkreis 0: Normal-Sollwert (z.B. 21.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/comfort_temperature">Heizkreis 0: Komfort-Sollwert (z.B. 23.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/reduced_temperature">Heizkreis 0: Absenk-Sollwert (z.B. 17.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/mode">Heizkreis 0: Betriebsmodus (standby, dhw, dhwAndHeating)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/target_temperature">Warmwasser: Sollwert (z.B. 55.0)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/one_time_charge">Warmwasser: Einmalige Aufheizung (start / stop)</option>
			</select>
		</div>
		<div>
			<label for="test-target-val" style="font-size: 85%; font-weight: bold;">Zu sendender Wert:</label>
			<input type="text" id="test-target-val" placeholder="z. B. 21.5">
		</div>
		<div>
			<button class="ui-btn ui-btn-inline ui-btn-b ui-mini" id="btn-send-command">Befehl senden</button>
		</div>
	</div>
	<div id="cmd-feedback" style="margin-top: 8px; font-weight: bold;"></div>
</div>

<!-- LIVE-TABELLE DER VERFÜGBAREN DATENPUNKTE -->
<div class="card-box">
	<h3 class="card-header">Live-Datenpunkte deiner Anlage</h3>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">
		Diese Tabelle zeigt alle aktuell von deiner Viessmann-Anlage gemeldeten Datenpunkte mit aktuellem Live-Wert. 
		Nutze das Suchfeld, um nach bestimmten Werten zu filtern. Klicke auf <b>[Kopieren]</b>, um das Topic für Loxone in die Zwischenablage zu kopieren.
	</p>

	<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px;">
		<div style="flex: 1 1 280px;">
			<input type="text" id="datapoint-search" placeholder="Filter nach Beschreibung oder Topic (z. B. Temperatur, Brenner, HK0)...">
		</div>
		<div>
			<label style="font-size: 85%;"><input type="checkbox" id="auto-refresh-check" checked> Live-Aktualisierung (5s)</label>
		</div>
	</div>

	<div class="table-responsive">
		<table class="topic-table" id="datapoints-table">
			<thead>
				<tr>
					<th style="min-width: 180px;">Beschreibung / Sensor</th>
					<th style="min-width: 120px;">Aktueller Wert</th>
					<th style="min-width: 100px;">Typ</th>
					<th style="min-width: 250px;">MQTT Topic (für Loxone)</th>
					<th style="min-width: 80px;">Zeit</th>
				</tr>
			</thead>
			<tbody id="datapoints-body">
				<tr>
					<td colspan="5" style="text-align: center; color: grey;">Lade Datenpunkte...</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<!-- ÜBERSICHT STEUERBARER BEFEHLE -->
<div class="card-box">
	<h3 class="card-header">Übersicht steuerbarer Befehle (Schreiben)</h3>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">Um diese Werte aus dem Loxone Miniserver zu ändern, sende eine Nachricht an das jeweilige <b>set-Topic</b>:</p>

	<div class="table-responsive">
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
	</div>
</div>

<?php
LBWeb::lbfooter();
?>

<script>
var autoRefreshTimer = null;

$(document).ready(function() {
	loadStats();
	loadLiveValues();

	$("#datapoint-search").on("keyup", function() {
		filterTable($(this).val().toLowerCase());
	});

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
		$("#stat-status").html(data.is_running ? "<span style='color:#16a34a;font-weight:bold;'>Online</span>" : "<span style='color:#dc2626;font-weight:bold;'>Gestoppt</span>");
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
			html += "<td><span style='font-size:105%;font-weight:bold;color:#1e293b;'>" + escapeHtml(String(val)) + unit + "</span></td>";
			html += "<td>" + typeBadge + "</td>";
			html += "<td><span class=\"mono\">" + escapeHtml(topic) + "</span> <button class=\"ui-btn ui-btn-inline ui-mini copy-btn\" onclick=\"copyTopic('" + escapeHtml(topic) + "')\">Kopieren</button></td>";
			html += "<td><small style='color:#64748b;'>" + ts + "</small></td>";
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
