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


// Load Config

if( ! file_exists(CONFIGFILE) ) {
	echo "<p>You first need to save the configuration.</p>";
	LBWeb::lbfooter();
	exit();
} else {
	$configdata = file_get_contents(CONFIGFILE);
	$config = json_decode($configdata);
	if( empty($config) ) {
		echo "<p>You first need to save the configuration.</p>";
		LBWeb::lbfooter();
		exit(1);
	}
}

// Init variables
$lbzeurl ="http://<lbuser>:<lbpass>@".lbhostname().":".lbwebserverport()."/admin/plugins/".LBPPLUGINDIR."/vitoconnect.php";
$mqtttopic = !empty($config->MQTT->topic) ? $config->MQTT->topic : "vitoconnect";

?>

<style>
.mono {
	font-family:monospace;
	font-size:110%;
	font-weight:bold;
	color:green;

}

table {
border-collapse: collapse;	
}

table, th, td {
  border: 1px solid grey;
  padding: 5px;
  text-align: left;
}

#overlay 
{
  display: none !important;
}


.table {
  width: 90%;
  margin: auto;
  table-layout: fixed;
  display: table;
  border-collapse: collapse;
  border: 1px solid grey;
  padding: 5px;
}

.table_row {
  display: table-row;
  border: 1px solid grey;
  padding: 5px;

}

.table_head {
	color: white;
	background-color: #6dac20;
	font-weight: bold;
	text-shadow: 1px 1px 2px black;
}

.table_head_value {
	width:40%;
}

.table_col {
  display: table-cell;
  border: 1px solid grey;
  padding: 5px;
}

</style>

<div class="wide">Query links and data</div>
<p>In the following link, the combination <span class="mono">&lt;lbuser&gt;:&lt;lbpass&gt; </span>needs to be replaced with your <b>LoxBerry's</b> username and password.</p>
<div style="display:flex; align-items: center; justify-content: center;">
	<div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
		<label for="summarylink">Link to trigger manually data from Viessmann</label>
		<input type="text" id="summarylink" name="summarylink" data-mini="true" value="<?=$lbzeurl."?action=summary";?>" readonly>
	</div>
</div>
<hr>

<div id="content">
</div>
	
<hr>

<?php
LBWeb::lbfooter();
?>

<script>

lbzeurl = '<?=$lbzeurl;?>';
mqtttopic = '<?=$mqtttopic;?>';

$( document ).ready(function() {

querySummary();

/*	$("#saveapply").click(function(){ saveapply(); });
	$("#saveapply").blur(function(){ 
		$("#savemessages").html("");
	});
*/

	$(".copytoclipboard").on("click", function(){ 
		var idToCopy = $(this).data("idtocopy");
		console.log( "Element to copy:", idToCopy );
		if( idToCopy != undefined ) {
			//copyToClipboard( $("#idToCopy") )
			copyToClipboard( idToCopy )
		}
	});



});

function querySummary() 
{
	$("#content").html("<i>Querying data...This may take 10 or 20 seconds...</i>");
	$("#content").css("color", "grey");
	
	$.get( "ajax-handler.php?action=getsummary" )
	.done(function( data ) {
		$("#content").html("<i>Data received...</i>");
		$("#content").css("color", "green");
		console.log("Done:", data);
		showInstallation( data );
	})
	.fail(function( error, textStatus, errorThrown ) {
		console.log("Fail:", error, textStatus, errorThrown);
		if( typeof error.responseJSON !== 'undefined' ) 
			$("#content").html("Error "+error.status+": "+error.responseJSON.error);
		else
			$("#content").html("Error "+error.status+": "+error.statusText);
		$("#content").css("color", "red");
		
	});
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

function showInstallation ( data ) 
{
	$("#content").html("Installation gefunden...");
	$("#content").css("color", "black");
	
	
	
	$Installation = get(data.general, 'id');
	if ( $Installation ) {
			vdivid = 'Installationsdaten';
			$("#content").append('<div id="'+vdivid+'"></div>');
			vdiv=$("#"+vdivid);
			vdiv.append("<h2>Installationsdaten Allgemein:</h2>");
				
				
				
			strHtml = '\
				<div class="table" role="table" id="InstallGeneral" aria-label="Data">\
					<div class="table_row">\
						<div class="table_col table_head">Eigenschaft</div>\
						<div class="table_col table_head table_head_value">Daten</div>\
					</div>\
				</div>\
			';
			vdiv.append(strHtml);
			

			strHtml = "";	
	
			$.each ( data.general, function( prop, val ) { 
			
				strHtml+= '\
					<div class="table_row">\
						<div class="table_col ">'+prop+'</div>\
						<div class="table_col ">'+val+'</div>\
					</div>\
				';		
			});
			
		
			$('#InstallGeneral').append(strHtml);
			
				
			vdiv.append("<h2>Installationsdaten Details:</h2>");
				
				
				
			strHtml = '\
				<div class="table" role="table" id="InstallDetails" aria-label="Data">\
					<div class="table_row">\
						<div class="table_col table_head">Eigenschaft</div>\
						<div class="table_col table_head table_head_value">Daten</div>\
					</div>\
				</div>\
			';
			vdiv.append(strHtml);
			

			strHtml = "";	
	
			$.each ( data.detail, function( prop, val ) { 
			
				strHtml+= '\
					<div class="table_row">\
						<div class="table_col ">'+prop+'</div>\
						<div class="table_col ">'+val+'</div>\
					</div>\
				';		
			});
			
		
			$('#InstallDetails').append(strHtml);	
				//queryBattery(vehicle.VIN);
		
		
	} else {
		$("#content").append("Sorry, no Viessmann Installation found :-(");
	}	
}

// Returns a deep property or object, without checking everything
get = function(obj, key) {
    return key.split(".").reduce(function(o, x) {
        return (typeof o == "undefined" || o === null) ? o : o[x];
    }, obj);
}

// Copies data to clipboard
function copyToClipboard( element ) {
    $("#"+element).select();
	document.execCommand("copy");
}

</script>





