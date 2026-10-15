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

// 4th argument true = nojqm (activates LoxBerry DS)
LBWeb::lbheader($template_title, $helplink, $helptemplate, true);

if (!file_exists(CONFIGFILE)) {
	echo "<div class='lb-content'><div class='lb-card'><p>Bitte zuerst die Einstellungen konfigurieren und speichern.</p></div></div>";
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
.test-panel-grid {
	display: grid;
	grid-template-columns: 2fr 1fr auto;
	gap: 12px;
	align-items: end;
	margin-top: 10px;
}
@media (max-width: 768px) {
	.stats-grid {
		grid-template-columns: 1fr;
		gap: 10px;
	}
	.test-panel-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<div class="lb-content">

<!-- STATUS & HEADER -->
<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Dienst-Status & Live-Zähler</h4>
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
				<button class="lb-btn lb-btn-sm" id="btn-force-poll">Jetzt abfragen (Poll)</button>
			</div>
		</div>
	</div>
	<div id="poll-msg" style="margin-top: 10px; font-weight: bold;"></div>
</div>

<!-- INTERAKTIVES TEST-PANEL (BEFEHLE SCHREIBEN) -->
<div class="lb-card" style="margin-bottom: 20px; border-left: 4px solid #0284c7;">
	<h4 style="color: #0369a1;">Steuerung testen (Befehl an Viessmann senden)</h4>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">Hier kannst du Steuerbefehle direkt über MQTT an deine Heizung senden, um die Funktion vor der Einbindung in Loxone Config zu testen.</p>

	<div class="test-panel-grid">
		<div>
			<label class="lb-form-label" for="test-target-select" style="font-size: 85%; font-weight: bold;">Ziel-Datenpunkt auswählen:</label>
			<select class="lb-select" id="test-target-select">
				<option value="<?=$mqtttopic;?>/set/circuit_0/normal_temperature">Heizkreis 0: Normal-Sollwert (z.B. 21.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/comfort_temperature">Heizkreis 0: Komfort-Sollwert (z.B. 23.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/reduced_temperature">Heizkreis 0: Absenk-Sollwert (z.B. 17.0)</option>
				<option value="<?=$mqtttopic;?>/set/circuit_0/mode">Heizkreis 0: Betriebsmodus (z. B. heating, standby, dhw, dhwAndHeating)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/target_temperature">Warmwasser: Sollwert (z.B. 55.0)</option>
				<option value="<?=$mqtttopic;?>/set/dhw/one_time_charge">Warmwasser: Einmalige Aufheizung (start / stop)</option>
			</select>
		</div>
		<div>
			<label class="lb-form-label" for="test-target-val" style="font-size: 85%; font-weight: bold;">Zu sendender Wert:</label>
			<input class="lb-input" type="text" id="test-target-val" placeholder="z. B. 21.5">
		</div>
		<div>
			<button class="lb-btn lb-btn-primary lb-btn-sm" id="btn-send-command">Befehl senden</button>
		</div>
	</div>
	<div id="cmd-feedback" style="margin-top: 8px; font-weight: bold;"></div>
</div>

<!-- LIVE-TABELLE DER VERFÜGBAREN DATENPUNKTE -->
<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Live-Datenpunkte deiner Anlage</h4>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">
		Diese Tabelle zeigt alle aktuell von deiner Viessmann-Anlage gemeldeten Datenpunkte mit aktuellem Live-Wert. 
		Nutze das Suchfeld, um nach bestimmten Werten zu filtern. Klicke auf <b>[Kopieren]</b>, um das Topic für Loxone in die Zwischenablage zu kopieren.
	</p>

	<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px;">
		<div style="flex: 1 1 280px;">
			<input class="lb-input" type="text" id="datapoint-search" placeholder="Filter nach Beschreibung oder Topic (z. B. Temperatur, Brenner, HK0)...">
		</div>
		<div>
			<label class="lb-toggle" style="vertical-align: middle;">
				<input type="checkbox" id="auto-refresh-check" checked>
				<span class="lb-toggle-slider"></span>
			</label>
			<span style="font-size: 85%; margin-left: 6px;">Live-Aktualisierung (5s)</span>
		</div>
	</div>

	<div class="table-responsive">
		<table class="lb-table" id="datapoints-table">
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
<div class="lb-card" style="margin-bottom: 20px;">
	<h4>Übersicht steuerbarer Befehle (Schreiben)</h4>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">Um diese Werte aus dem Loxone Miniserver zu ändern, sende eine Nachricht an das jeweilige <b>set-Topic</b>. Alle hier gelisteten Befehle wurden dynamisch von der Viessmann API für deine Anlage erkannt:</p>

	<div class="table-responsive">
		<table class="lb-table">
			<thead>
				<tr>
					<th>Befehl / MQTT Topic</th>
					<th>Erlaubte Werte</th>
					<th>Wirkung / Beschreibung</th>
				</tr>
			</thead>
			<tbody id="commands-table-body">
				<tr>
					<td><span class="mono"><?=$mqtttopic;?>/poll</span></td>
					<td><span class="mono">1</span></td>
					<td>Löst einen sofortigen Abruf der API aus</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>


</div><!-- /lb-content -->

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

		var btn = $(this);
		btn.prop("disabled", true).text("Sende...");
		$("#cmd-feedback").css("color", "#0284c7").text("Sende Befehl an MQTT Broker (" + topic + " = " + val + ")...");

		$.post("ajax-handler.php?action=send_command", { topic: topic, value: val })
		.done(function(res) {
			btn.prop("disabled", false).text("Befehl senden");
			if (res && res.success) {
				$("#cmd-feedback").css("color", "#16a34a").text("Befehl erfolgreich gesendet: " + topic + " = " + val + ". Aktualisiere Live-Werte...");
				setTimeout(function() {
					loadLiveValues();
				}, 3500);
			} else {
				var err = (res && res.error) ? res.error : "Fehler beim Senden des Befehls.";
				$("#cmd-feedback").css("color", "#dc2626").text(err);
			}
		})
		.fail(function(xhr) {
			btn.prop("disabled", false).text("Befehl senden");
			var err = "Fehler beim Senden des Befehls an MQTT.";
			try {
				var j = JSON.parse(xhr.responseText);
				if (j.error) err = j.error;
			} catch(e) {}
			$("#cmd-feedback").css("color", "#dc2626").text(err);
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
		var writableList = [];
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

			if (writable && dp.set_topic) {
				writableList.push(dp);
			}

			html += "<tr class='dp-row' data-search='" + (desc + " " + topic).toLowerCase() + "'>";
			html += "<td><b>" + escapeHtml(desc) + "</b></td>";
			html += "<td><span style='font-size:105%;font-weight:bold;color:#1e293b;'>" + escapeHtml(String(val)) + unit + "</span></td>";
			html += "<td>" + typeBadge + "</td>";
			html += "<td><span class=\"mono\">" + escapeHtml(topic) + "</span> <button class=\"lb-btn lb-btn-sm\" style=\"margin-left: 6px; padding: 2px 8px; font-size: 80%;\" onclick=\"copyTopic('" + escapeHtml(topic) + "')\">Kopieren</button></td>";
			html += "<td><small style='color:#64748b;'>" + ts + "</small></td>";
			html += "</tr>";
		}
		tbody.html(html);
		filterTable($("#datapoint-search").val().toLowerCase());

		// Dynamisch Befehle-Dropdown & Tabelle aktualisieren
		updateCommandsUI(writableList);
	});
}

function updateCommandsUI(writableList) {
	if (!writableList || writableList.length === 0) return;

	var select = $("#test-target-select");
	var currentSelected = select.val();
	var selectHtml = "";

	var cmdTable = $("#commands-table-body");
	var cmdHtml = "";

	// Standard Poll-Befehl immer an erster Stelle
	cmdHtml += "<tr>";
	cmdHtml += "<td><span class='mono'>" + escapeHtml("<?=$mqtttopic;?>/poll") + "</span> <button class='lb-btn lb-btn-sm' style='margin-left: 6px; padding: 2px 8px; font-size: 80%;' onclick=\"copyTopic('<?=$mqtttopic;?>/poll')\">Kopieren</button></td>";
	cmdHtml += "<td><span class='mono'>1</span></td>";
	cmdHtml += "<td>Löst einen sofortigen Abruf der Viessmann API aus</td>";
	cmdHtml += "</tr>";

	for (var i = 0; i < writableList.length; i++) {
		var item = writableList[i];
		var setTopic = item.set_topic;
		var label = item.description || setTopic;
		var allowed = item.allowed_values || "Zahl / Text";
		var currVal = item.value !== undefined ? " (Aktuell: " + item.value + (item.unit ? " " + item.unit : "") + ")" : "";

		selectHtml += "<option value='" + escapeHtml(setTopic) + "' data-allowed='" + escapeHtml(allowed) + "'>";
		selectHtml += escapeHtml(label) + currVal + " &rarr; " + escapeHtml(setTopic);
		selectHtml += "</option>";

		cmdHtml += "<tr>";
		cmdHtml += "<td><span class='mono'>" + escapeHtml(setTopic) + "</span> <button class='lb-btn lb-btn-sm' style='margin-left: 6px; padding: 2px 8px; font-size: 80%;' onclick=\"copyTopic('" + escapeHtml(setTopic) + "')\">Kopieren</button></td>";
		cmdHtml += "<td><span class='mono' style='color:#0369a1;font-weight:600;'>" + escapeHtml(allowed) + "</span></td>";
		cmdHtml += "<td><b>" + escapeHtml(label) + "</b></td>";
		cmdHtml += "</tr>";
	}

	select.html(selectHtml);
	if (currentSelected && select.find("option[value='" + currentSelected + "']").length > 0) {
		select.val(currentSelected);
	}
	cmdTable.html(cmdHtml);

	// Update placeholder of value input with allowed values
	updateValuePlaceholder();
}

$(document).on("change", "#test-target-select", function() {
	updateValuePlaceholder();
});

function updateValuePlaceholder() {
	var opt = $("#test-target-select option:selected");
	var allowed = opt.attr("data-allowed");
	if (allowed) {
		$("#test-target-val").attr("placeholder", "Erlaubt: " + allowed);
	} else {
		$("#test-target-val").attr("placeholder", "z. B. 21.5");
	}
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
