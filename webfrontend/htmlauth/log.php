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

$plugin_dir = defined("LBPPLUGINDIR") ? LBPPLUGINDIR : "Vitoconnect";
?>

<style>
.mono {
	font-family: monospace;
	font-size: 105%;
	font-weight: bold;
}
.card-box {
	background: #fdfdfd;
	border: 1px solid #d0d0d0;
	border-radius: 6px;
	padding: 15px;
	margin-bottom: 20px;
}
.console-box {
	background: #1e1e1e;
	color: #d4d4d4;
	font-family: 'Courier New', Courier, monospace;
	font-size: 90%;
	line-height: 1.4;
	padding: 12px;
	border-radius: 5px;
	height: 380px;
	max-height: 500px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-break: break-all;
	border: 1px solid #333;
}
.log-err {
	color: #ff6b6b;
	font-weight: bold;
}
.log-warn {
	color: #feca57;
	font-weight: bold;
}
.log-inf {
	color: #1dd1a1;
}
.badge-status-ok {
	background: #28a745;
	color: white;
	padding: 4px 10px;
	border-radius: 4px;
	font-weight: bold;
}
.badge-status-err {
	background: #dc3545;
	color: white;
	padding: 4px 10px;
	border-radius: 4px;
	font-weight: bold;
}
.diag-alert {
	padding: 10px 15px;
	border-radius: 4px;
	margin-top: 10px;
	font-size: 95%;
}
.diag-alert-danger {
	background-color: #f8d7da;
	color: #721c24;
	border: 1px solid #f5c6cb;
}
.diag-alert-success {
	background-color: #d4edda;
	color: #155724;
	border: 1px solid #c3e6cb;
}
</style>

<!-- DIENST-DIAGNOSE & STATUS -->
<div class="card-box" id="diag-container">
	<div class="wide">Dienst-Status & Fehler-Diagnose</div>
	<div class="ui-grid-a" style="margin-top: 10px;">
		<div class="ui-block-a">
			<b>Aktueller Zustand:</b> <span id="diag-badge"><span class="badge-status-err">Prüfe Status...</span></span>
			<span id="diag-pid" style="margin-left: 10px; color: #666;"></span>
		</div>
		<div class="ui-block-b" style="text-align: right;">
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-start">Starten</button>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-restart">Neustarten</button>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-stop">Stoppen</button>
		</div>
	</div>

	<!-- Fehlermeldung / Grund wenn gestoppt -->
	<div id="diag-message-box" class="diag-alert diag-alert-success" style="display: none;">
		<b id="diag-reason-title">Diagnose:</b> <span id="diag-reason-text">-</span>
	</div>
</div>

<!-- LIVE DAEMON LOG CONSOLE -->
<div class="card-box">
	<div class="ui-grid-a" style="margin-bottom: 8px;">
		<div class="ui-block-a">
			<span class="wide" style="font-size: 110%;">Live Daemon Log (<span id="log-filename">daemon.log</span>)</span>
		</div>
		<div class="ui-block-b" style="text-align: right;">
			<label style="display: inline-block; margin-right: 15px;"><input type="checkbox" id="auto-refresh-log" checked> Auto-Refresh (3s)</label>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-refresh-log">Aktualisieren</button>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-clear-log">Log leeren</button>
		</div>
	</div>

	<div class="console-box" id="log-console">Lade Logdatei...</div>
</div>

<!-- LOXBERRY LOGFILES ARCHIV -->
<div class="card-box">
	<div class="wide">LoxBerry System-Logdateien</div>
	<p>Hier findest du alle Logfiles dieses Plugins zur detaillierten Fehleranalyse und für Supportanfragen im LoxForum:</p>

<?php
if ($handle = opendir($lbplogdir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            echo '<div class="ui-corner-all ui-shadow" style="margin-bottom: 5px;">';
            echo '<a id="btnlogs" data-role="button" href="/admin/system/tools/logfile.cgi?logfile=/plugins/' . $plugin_dir . '/' . $entry . '&header=html&format=template" target="_blank" data-inline="true" data-mini="true">' . htmlspecialchars($entry) . '</a>';
            echo '</div>';
        }
    }
    closedir($handle);
}
?>
</div>

<?php
LBWeb::lbfooter();
?>

<script>
var logTimer = null;

$(document).ready(function() {
	loadDiagnostics();

	$("#btn-refresh-log").click(function() {
		loadDiagnostics();
	});

	$("#btn-clear-log").click(function() {
		if (confirm("Möchtest du die Datei daemon.log wirklich leeren?")) {
			$.getJSON("ajax-handler.php?action=clear_log").done(function() {
				loadDiagnostics();
			});
		}
	});

	$("#btn-start").click(function() { controlDaemon("start"); });
	$("#btn-restart").click(function() { controlDaemon("restart"); });
	$("#btn-stop").click(function() { controlDaemon("stop"); });

	logTimer = setInterval(function() {
		if ($("#auto-refresh-log").is(":checked")) {
			loadDiagnostics(true);
		}
	}, 3000);
});

function controlDaemon(cmd) {
	$("#diag-reason-text").text("Sende Befehl '" + cmd + "'...");
	$.getJSON("ajax-handler.php?action=daemon_control&cmd=" + cmd)
	.done(function() {
		setTimeout(loadDiagnostics, 1500);
	});
}

function loadDiagnostics(isAuto) {
	$.getJSON("ajax-handler.php?action=get_daemon_diagnostics")
	.done(function(data) {
		// Update Status Badge
		if (data.is_running) {
			$("#diag-badge").html('<span class="badge-status-ok">Aktiv & Läuft</span>');
			$("#diag-pid").text("(PID: " + data.pid + ")");
			$("#diag-message-box").removeClass("diag-alert-danger").addClass("diag-alert-success").show();
			$("#diag-reason-title").text("Status:");
			$("#diag-reason-text").text("Der Hintergrunddienst läuft normal und verarbeitet Abfragen.");
		} else {
			$("#diag-badge").html('<span class="badge-status-err">Gestoppt</span>');
			$("#diag-pid").text("");
			$("#diag-message-box").removeClass("diag-alert-success").addClass("diag-alert-danger").show();
			$("#diag-reason-title").text("Ursache:");
			$("#diag-reason-text").text(data.reason_text || "Dienst ist nicht gestartet.");
		}

		// Update Log Console
		if (data.log_content !== undefined) {
			var formatted = formatLogText(data.log_content);
			var consoleBox = $("#log-console");
			var wasAtBottom = (consoleBox.scrollTop() + consoleBox.innerHeight() >= consoleBox[0].scrollHeight - 30);
			consoleBox.html(formatted || "<i>Logdatei ist momentan leer.</i>");
			if (!isAuto || wasAtBottom) {
				consoleBox.scrollTop(consoleBox[0].scrollHeight);
			}
		}
	})
	.fail(function() {
		$("#diag-badge").html('<span class="badge-status-err">Fehler beim Abruf</span>');
	});
}

function formatLogText(rawText) {
	if (!rawText) return "";
	var lines = rawText.split("\n");
	var output = [];
	for (var i = 0; i < lines.length; i++) {
		var l = escapeHtml(lines[i]);
		if (l.indexOf("[ERROR]") !== -1 || l.indexOf("Traceback") !== -1 || l.indexOf("Error:") !== -1) {
			output.push("<span class='log-err'>" + l + "</span>");
		} else if (l.indexOf("[WARNING]") !== -1 || l.indexOf("[WARN]") !== -1) {
			output.push("<span class='log-warn'>" + l + "</span>");
		} else if (l.indexOf("[INFO]") !== -1) {
			output.push("<span class='log-inf'>" + l + "</span>");
		} else {
			output.push(l);
		}
	}
	return output.join("\n");
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
