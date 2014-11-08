<?php 
/* 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */
// open and load site variables
require('../../config.php');
require('../FCD-helpers.php');

// secure this page
requireRoleOrLogin('ADMIN');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// stash a nonce (one-time key) to make sure we don't re-submit this form accidentally
$_SESSION['nonce'] = $nonce = md5('salt'.microtime());

// test up front for locality ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$LocalityID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$LocalityID = 0;
	$addOrEdit = 'ADD';
}

$locality_data = null;

if ($addOrEdit == 'EDIT') {
	// retrieve the main record for this locality
	$query="SELECT * FROM localities WHERE LocalityID = '".mysql_real_escape_string($LocalityID)."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$locality_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
}

/*
 * Query for controlled lists of misc values
 */

//Retrieve list of geological times (hierarchy is Period, Epoch, Age)
$query='SELECT DISTINCT GeolTimeID, Period, Epoch, Age, t.ShortName, StartAge FROM geoltime g, L_timescales t WHERE g.Timescale=t.TimescaleID ORDER BY StartAge DESC, Age, Epoch;';
$geoltime_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of countries
$query='SELECT name FROM L_countries ORDER BY name';
$country_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// Build a complete edit form in one page
?>

<form id="edit-locality-form" action="update_locality.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />
<input type="hidden" name="addOrEdit" value="<?= $addOrEdit; ?>" />
<input type="hidden" id="LocalityID" name="LocalityID" value="<?= $LocalityID; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_localities.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Fossil" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new locality" : "Edit an existing locality (id: ".$LocalityID.")" ?> </h1>

<table id="xxxxxx" width="100%" border="0">
  <tr>
    <td width="30%" align="right" valign="top"><b>locality name</b></td>
    <td>&nbsp; 
<input type="text" name="LocalityName" id="LocalityName" style="width: 95%;" value="<?= testForProp($locality_data, 'LocalityName', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>stratum name</b></td>
    <td>&nbsp; <input type="text" name="Stratum" id="Stratum" style="width: 95%;" value="<?= testForProp($locality_data, 'Stratum', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>PBDB collection number</b></td>
    <td>&nbsp; <input type="text" name="PBDBCollectionNum" id="PBDBCollectionNum" style="width: 200px;" value="<?= testForProp($locality_data, 'PBDBCollectionNum', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>notes</b></td>
    <td>&nbsp; <textarea name="LocalityNotes" id="LocalityNotes" style="width: 95%;" rows="4"
	><?= testForProp($locality_data, 'LocalityNotes', '') ?></textarea>
    </td>
  </tr>
<!-- NOTE: MinAge and MaxAge fields are not currently used! -->
  <tr>
    <!-- Offer a friendly picker for country -->
    <td align="right" valign="top"><b>country</b></td>
    <td>&nbsp; <select name="Country" id="Country">
	<?php
	if(mysql_num_rows($country_list)==0){
		echo "no countries available";
	} else {
		mysql_data_seek($country_list,0);
		while($row=mysql_fetch_assoc($country_list)) {
			echo "<option ";
			if ($row['name'] == testForProp($locality_data, 'Country', '')) {
				echo "selected=\"selected\" ";
			}
			echo "value=\"".$row['name']."\">".$row['name']."</option>";
		}
	}
	?>
	</select>
  </tr>
  <tr>
    <!-- Offer a friendly picker for geological time -->
    <td align="right" valign="top"><b>geological age</b></td>
    <td>&nbsp; 
	<select name="GeolTime" id="GeolTime">
	<?php
	if(mysql_num_rows($geoltime_list)==0){
		?>
			<option value="0">No geological time in database</option>
			<?php
	} else {
		mysql_data_seek($geoltime_list,0);
		while($row=mysql_fetch_assoc($geoltime_list)) {
			echo "<option ";
			if ($row['GeolTimeID'] == testForProp($locality_data, 'GeolTime', '')) {
				echo "selected=\"selected\" ";
			}
			echo "value=\"".$row['GeolTimeID']."\">".$row['Period'];
			if ($row['Epoch']) {
				echo " / ".$row['Epoch'];
				if ($row['Age']) {
					echo " / ".$row['Age'];
				};
			};
			echo "</option>";
		}

	}
	?>
	</select>
    </td>
  </tr>
</table>

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_localities.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Fossil" />
</div>

</form>

<script type="text/javascript">

   $(document).ready(function() {
	initFossilAutocompleteWidgets();
	$('#edit-locality-form').submit(function() {
		if ($('#CollectionNumber').val().trim() === '') {
			alert("Collection number field cannot be empty!");
			return false; // block form submission
		}
		return true; // allow normal submission
	});
   });

   function initFossilAutocompleteWidgets() {
	$('#AC_FossilPubID-display').not('.ui-autocomplete-input').autocomplete({
		source: '/autocomplete_publications.php',
		autoSelect: true,  // recognizes typed-in values if they match an item
		autoFocus: true,
		delay: 20,
		minLength: 3,
		focus: function(event, ui) {
			///console.log("FOCUSED > "+ ui.item.FullReference);
			// clobber any existing hidden value!?
			$('#AC_FossilPubID').val('');
			// override normal display (would show numeric ID!)
			return false;
		},
		change: function(event, ui) {
			///console.log("CHANGED TO ITEM > "+ ui.item);
			if (!ui.item) {
				// widget blurred with invalid value; clear any 
				// stale values from the UI
				$('#AC_FossilPubID-display').val('');
				$('#AC_FossilPubID').val('');
				$('#AC_FossilPubID-more-info').html('&nbsp;');
			}
		},
		select: function(event, ui) {
			///console.log("CHOSEN > "+ ui.item.FullReference);
			$('#AC_FossilPubID-display').val(ui.item.label);
			$('#AC_FossilPubID').val(ui.item.value);
			$('#AC_FossilPubID-more-info').html(ui.item.FullReference);
			// override normal display (would show numeric ID!)
			return false;
		},
		minChars: 3
	});
   }

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
