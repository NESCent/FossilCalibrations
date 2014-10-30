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

// test up front for fossil ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$FossilID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$FossilID = 0;
	$addOrEdit = 'ADD';
}

$fossil_data = null;

if ($addOrEdit == 'EDIT') {
	// retrieve the main record for this fossil
	$query="SELECT * FROM fossils WHERE FossilID = '".mysql_real_escape_string($FossilID)."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$fossil_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
}

/*
 * Query for controlled lists of misc values
 */

//Retrieve list of localities
$query='SELECT * FROM View_Localities ORDER BY LocalityName';
$locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// list of all collection acronyms
$query='SELECT * FROM L_CollectionAcro ORDER BY Acronym';
$collectionacro_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// retrieve fossil pub
$query="SELECT * FROM publications WHERE PublicationID = '".$fossil_data['FossilPub']."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$fossil_pub_data = mysql_fetch_assoc($result);
mysql_free_result($result);

// Build a complete edit form in one page
?>

<form id="edit-fossil-form" action="update_fossil.php" method="post" id="edit-fossil" enctype="multipart/form-data">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />
<input type="hidden" name="addOrEdit" value="<?= $addOrEdit; ?>" />
<input type="hidden" id="FossilID" name="FossilID" value="<?= $FossilID; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_fossils.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Fossil" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new fossil" : "Edit an existing fossil (id: ".$FossilID.")" ?> </h1>

<table id="xxxxxx" width="100%" border="0">
  <tr>
    <td width="30%" align="right" valign="top"><b>collection</b></td>
    <td>&nbsp; 
<!--
<input type="text" name="CollectionAcro" id="CollectionAcro" style="width: 200px;" value="<?= testForProp($fossil_data, 'CollectionAcro', '') ?>" ></td>
-->
<select name="CollectionAcro" id="CollectionAcro">
<?php
	if(mysql_num_rows($collectionacro_list)==0){
		?>
			<option value="">No acronyms found!</option>
			<?php
	} else {
		mysql_data_seek($collectionacro_list,0);
		$currentCollection = testForProp($fossil_data, 'CollectionAcro', '');
		while($row=mysql_fetch_assoc($collectionacro_list)) {
			$thisCollection = $row['Acronym'];
			if ($currentCollection == $thisCollection) {
				echo '<option value="'.$row['Acronym'].'" selected="selected">'.$row['Acronym'].', '.$row['CollectionName'].'</option>';
			} else {
				echo '<option value="'.$row['Acronym'].'">'.$row['Acronym'].', '.$row['CollectionName'].'</option>';
			}			
		}
	} ?>
</select>

  </tr>
  <tr>
    <td align="right" valign="top"><b>collection number</b></td>
    <td>&nbsp; <input type="text" name="CollectionNumber" id="CollectionNumber" style="width: 200px;" value="<?= testForProp($fossil_data, 'CollectionNumber', '') ?>" ></td>
  </tr>
  <tr>
    <!-- Offer a friendly picker for locality! and separate space for stratum? -->
    <td align="right" valign="top"><b>locality</b></td>
    <td>&nbsp; 
<!--
<input type="text" name="LocalityID" id="LocalityID" style="width: 95%;" value="<?= testForProp($fossil_data, 'LocalityID', '') ?>" >
-->
<select name="LocalityID" id="LocalityID">
		<?php
		if(mysql_num_rows($locality_list)==0){
			echo "<option value=\"\">No localities found!</option>";
		} else {
			mysql_data_seek($locality_list,0);
			$currentLocality = testForProp($fossil_data, 'LocalityID', '');
			while($row=mysql_fetch_assoc($locality_list)) {
				$thisLocality = $row['LocalityID'];
				$thisLabel = empty($row['LocalityName']) ? 'NO NAME' : $row['LocalityName'];
				if (!empty($row['Stratum'])) {
					$thisLabel = $thisLabel .' ['. $row['Stratum'] .']';
				}
				if ($currentLocality == $thisLocality) {
					echo '<option value="'.$row['LocalityID'].'" selected="selected">'.$thisLabel.'</option>';
				} else {
					echo '<option value="'.$row['LocalityID'].'">'.$thisLabel.'</option>';
				}			
			}
			//echo "<option value=\"New\">Add new locality on next page</option>";
		} ?>
</select>

</td>
  </tr>
  <tr>
    <!-- Offer a friendly picker for fossil publication -->
    <td align="right" valign="top"><b>fossil publication</b></td>
<!--
    <td>&nbsp; <input type="text" name="FossilPub" id="FossilPub" style="width: 95%;" value="<?= testForProp($fossil_data, 'FossilPub', '') ?>" ></td>
-->
    <td>&nbsp; 
	<input type="text" style="width: 200px;" name="AC_FossilPubID-display" id="AC_FossilPubID-display" value="<?= testForProp($fossil_pub_data, 'ShortName', '') ?>" placeholder="Enter partial name" />
	<input type="text" name="FossilPub" id="AC_FossilPubID" value="<?= testForProp($fossil_data, 'FossilPub', '') ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
	<a href="/protected/manage_publications.php" target="_new" style="float: right;">Show all publications in a new window</a>
	<br/>&nbsp;
	<div id="AC_FossilPubID-more-info" class="text-excerpt" style="display: inline-block; width: 400px;"><?= testForProp($fossil_pub_data, 'FullReference', '&nbsp;') ?></div>
    </td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>species</b></td>
    <td rowspan="8" style="background-color: #ddd; Xborder-radius: 8px; padding: 20px; 30px;">
	<p style="margin-top: 0px;">
	REMINDER: Many fossil properties are subject to interpretation, so they're
	actually part of the linked-fossil record for each linked calibration.
	Click the links below to review (or edit) these in a new browser window.
	</p>
	<?
	// How many calibrations link to this fossil?
        $query="SELECT Link_CalibrationFossil.*, calibrations.NodeName 
		  FROM Link_CalibrationFossil
		  INNER JOIN calibrations 
		  ON Link_CalibrationFossil.CalibrationID=calibrations.CalibrationID
		  WHERE Link_CalibrationFossil.FossilID=". mysql_real_escape_string($FossilID);
	$linkedCalibrations=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedCalibrationCount = mysql_num_rows($linkedCalibrations);

	if ($linkedCalibrationCount == 0) {
	    ?><br/>
	      <em>This fossil is not currently linked to any calibration.</em><?
	} else {
	      $nthCalibration = 0;
	      while ($linkedCalibration = mysql_fetch_array($linkedCalibrations)) {
		  $nthCalibration++; ?>
	<div style="display: block; margin: 0.25em 200px 0.25em 40px;">
	    <a target="_blank" href="/Show_Calibration.php?CalibrationID=<?= $linkedCalibration['CalibrationID'] ?>"
	       ><?= $linkedCalibration['NodeName'] ?></a>
	    &nbsp;
	    &nbsp;
	    <a target="_blank" href="/protected/edit_calibration.php?id=<?= $linkedCalibration['CalibrationID'] ?>"
	       >(edit)</a>
        </div>
	   <? } 
	}
	mysql_free_result($linkedCalibrations);
	?>
    </td>
<!--
    <td>&nbsp; <input type="text" name="Species" id="Species" style="width: 200px;" value="<?= testForProp($fossil_data, 'Species', '') ?>" ></td>
-->
  </tr>
  <tr>
    <td align="right" valign="top"><b>minimum age</b></td>
<!--
    <td>&nbsp; <input type="text" name="MinAge" id="MinAge" style="width: 200px;" value="<?= testForProp($fossil_data, 'MinAge', '') ?>" ></td>
-->
  </tr>
  <tr>
    <td align="right" valign="top"><b>minimum age type</b></td>
<!--
    <td>&nbsp; <input type="text" name="MinAgeType" id="MinAgeType" style="width: 200px;" value="<?= testForProp($fossil_data, 'MinAgeType', '') ?>" ></td>
-->
  </tr>
  <tr>
    <td align="right" valign="top"><b>maximum age</b></td>
<!--
    <td>&nbsp; <input type="text" name="MaxAge" id="MaxAge" style="width: 200px;" value="<?= testForProp($fossil_data, 'MaxAge', '') ?>" ></td>
  </tr>
-->
  <tr>
    <td align="right" valign="top"><b>maximum age type</b></td>
<!--
    <td>&nbsp; <input type="text" name="MaxAgeType" id="MaxAgeType" style="width: 200px;" value="<?= testForProp($fossil_data, 'MaxAgeType', '') ?>" ></td>
-->
  </tr>
  <tr>
    <td align="right" valign="top"><b>phylo. justification</b></td>
<!--
    <td>&nbsp; <textarea name="PhyJustification" id="PhyJustification" rows="3" style="width: 95%; height: 3.5em;" ><?= testForProp($fossil_data, 'PhyJustification', '') ?></textarea></td>
-->
  </tr>
  <tr>
    <td align="right" valign="top"><b>phylo. justification type</b></td>
<!--
    <td>&nbsp; <input type="text" name="PhyJustificationType" id="PhyJustificationType" style="width: 200px;" value="<?= testForProp($fossil_data, 'PhyJustificationType', '') ?>" ></td>
-->
  <tr>
    <td align="right" valign="top"><b>phylo. publication</b></td>
<!--
    <td>&nbsp; <input type="text" name="PhyloPub" id="PhyloPub" style="width: 95%;" value="<?= testForProp($featured_image_data, 'PhyloPub', '') ?>" ></td>
-->
  </tr>
</table>

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_fossils.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Fossil" />
</div>

</form>

<script type="text/javascript">

   $(document).ready(function() {
	initFossilAutocompleteWidgets();
	$('#edit-fossil-form').submit(function() {
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
