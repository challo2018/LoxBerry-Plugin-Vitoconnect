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
	font-size: 100%;
	font-weight: bold;
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
.header-flex {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}
.console-box {
	background: #1e1e1e;
	color: #d4d4d4;
	font-family: 'Courier New', Courier, monospace;
	font-size: 88%;
	line-height: 1.45;
	padding: 14px;
	border-radius: 6px;
	height: 380px;
	max-height: 520px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-break: break-all;
	border: 1px solid #333;
	margin-top: 10px;
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
	background: #10b981;
	color: white;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 0.85rem;
	font-weight: 600;
	display: inline-block;
}
.badge-status-err {
	background: #ef4444;
	color: white;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 0.85rem;
	font-weight: 600;
	display: inline-block;
}
.diag-alert {
	padding: 10px 14px;
	border-radius: 6px;
	margin-top: 12px;
	font-size: 92%;
}
.diag-alert-danger {
	background-color: #fee2e2;
	color: #991b1b;
	border: 1px solid #fecaca;
}
.diag-alert-success {
	background-color: #dcfce7;
	color: #166534;
	border: 1px solid #bbf7d0;
}
@media (max-width: 640px) {
	.card-box {
		padding: 14px;
	}
	.card-header {
		font-size: 1.05rem;
	}
	.console-box {
		height: 280px;
		font-size: 80%;
		padding: 10px;
	}
}
</style>

<!-- DIENST-DIAGNOSE & STATUS -->
<div class="card-box" id="diag-container">
	<h3 class="card-header">Dienst-Status & Fehler-Diagnose</h3>
	<div class="header-flex">
		<div>
			<span style="font-size: 90%; color: #64748b; font-weight: bold;">ZUSTAND:</span>
			<span id="diag-badge" style="margin-left: 6px;"><span class="badge-status-err">Prüfe Status...</span></span>
			<span id="diag-pid" style="margin-left: 8px; color: #64748b; font-size: 85%;"></span>
		</div>
		<div>
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
	<div class="header-flex">
		<div>
			<h3 class="card-header" style="margin: 0; border: none; padding: 0;">Live Daemon Log (<span id="log-filename" style="color: #64748b; font-size: 90%;">daemon.log</span>)</h3>
		</div>
		<div style="display: flex; align-items: center; gap: 8px;">
			<label style="margin: 0; font-size: 85%;"><input type="checkbox" id="auto-refresh-log" checked> Auto-Refresh (3s)</label>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-refresh-log">Aktualisieren</button>
			<button class="ui-btn ui-btn-inline ui-mini" id="btn-clear-log">Log leeren</button>
		</div>
	</div>

	<div class="console-box" id="log-console">Lade Logdatei...</div>
</div>

<!-- LOXBERRY LOGFILES ARCHIV -->
<div class="card-box">
	<h3 class="card-header">LoxBerry System-Logdateien</h3>
	<p style="font-size: 90%; color: #475569; margin: 0 0 10px 0;">Hier findest du alle Logfiles dieses Plugins zur detaillierten Fehleranalyse und für Supportanfragen im LoxForum:</p>

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
