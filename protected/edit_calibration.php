<?php 
// open and load site variables
require('../Site.conf');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// Build a complete add/edit form in one page (using stepwise accordion UI)

// stash a nonce (one-time key) to make sure we don't re-submit this form accidentally
$_SESSION['nonce'] = $nonce = md5('salt'.microtime());

// test up front for calibration ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$CalibrationID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$CalibrationID = 0;
	$addOrEdit = 'ADD';
}

/*
 * If we're editing an existing calibration, load all related records (publications, etc)
 */
if ($addOrEdit == 'EDIT') {
	// retrieve the main calibration record (or die trying)
	$query="SELECT * FROM calibrations WHERE CalibrationID = '".$CalibrationID."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$calibration_data = mysql_fetch_assoc($result);
	if ($calibration_data == false) die("ERROR: Sorry, there's no calibration with this ID: ".$CalibrationID ." Please double-check this URL.");
	mysql_free_result($result);

	// retrieve the main publication for this calibration, if any
	$query="SELECT * FROM publications WHERE PublicationID = '".$calibration_data['NodePub']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$node_pub_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// TODO: retrieve tip taxa pairs for this node, if any (report the count of these?)
/*
	$query="SELECT * FROM publications WHERE PublicationID = '".$calibration_data['NodePub']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$node_pub_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
*/

	// TODO: retrieve fossil record for this node, if any (ASSUMES only one fossil per calibration!)
	$query="SELECT * FROM fossils WHERE FossilID = (SELECT FossilID FROM Link_CalibrationFossil WHERE CalibrationID = '".$CalibrationID."')";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	// TODO: respond more gracefully to missing fossil (skip dependent data below)
	$fossil_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve fossil locality
	$query="SELECT * FROM localities WHERE LocalityID = '".$fossil_data['LocalityID']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$locality_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve fossil collection
	$query="SELECT * FROM L_CollectionAcro WHERE Acronym = '".$fossil_data['CollectionAcro']."'";
	// TODO: force uniqueness of Acronym field here!?
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$collection_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve fossil pub
	$query="SELECT * FROM publications WHERE PublicationID = '".$fossil_data['FossilPub']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$fossil_pub_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve phylogeny pub
	$query="SELECT * FROM publications WHERE PublicationID = '".$fossil_data['PhyloPub']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$phylo_pub_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
}

// Return a desired property from any of the data objects above, or a default if not found.
// This should generally Do the Right Thing, whether we're add a new calibration, editing a 
// complete existing calibration, or one that's partially complete.
function testForProp( $data, $property, $default ) {
	if (!isset($data)) return $default;
	if (!is_array($data)) return $default;
	return $data[$property];
}

/*
 * Query for controlled lists of misc values
 */

// list of all higher taxa
$query='SELECT * FROM L_HigherTaxa';
$highertaxa_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// list of all collection acronyms
$query='SELECT * FROM L_CollectionAcro ORDER BY Acronym';
$collectionacro_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of fossils
$query='SELECT FossilID, Species, CollectionAcro, CollectionNumber, LocalityID, LocalityName, Country FROM View_Fossils';
$fossil_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of localities
$query='SELECT * FROM View_Localities ORDER BY StratumMinAge';
$locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of age types
$query='SELECT * FROM L_agetypes';
$agetypes_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of phylogenetic justification types
$query='SELECT * FROM L_PhyloTypes ORDER BY PhyloJustType';
$phyjusttype_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of geological times
$query='SELECT GeolTimeID, Age, Period, t.ShortName, StartAge FROM geoltime g, L_timescales t WHERE g.Timescale=t.TimescaleID ORDER BY StartAge';
$geoltime_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of countries
$query='SELECT name FROM L_countries ORDER BY name';
$country_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

?>
<script type="text/javascript">
	$(document).ready(function() {
		// set up the main accordion UI
		$('#edit-steps').accordion({
			collapsible: true,	// all panels can be collapsed
			heightStyle: 'content'  // conform to contents of each panel
		});

		// prepare auto-complete widgets
		$('#AC_PubID-display').autocomplete({
			source: '/autocomplete_publications.php',
		     /* source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
		     */
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
	             /* response: function(event, ui) {
				// another place to manipulate returned matches
				console.log("RESPONSE > "+ ui.content);
			},
		      */
			focus: function(event, ui) {
				console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_PubID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_PubID-display').val('');
					$('#AC_PubID').val('');
					$('#AC_PubID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_PubID-display').val(ui.item.label);
				$('#AC_PubID').val(ui.item.value);
				$('#AC_PubID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
		     /* close: function(event, ui) {
				console.log("CLOSING VALUE > "+ this.value);
			},
		      */
			minChars: 3
		});

		$('#AC_FossilPubID-display').autocomplete({
			source: '/autocomplete_publications.php',
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			focus: function(event, ui) {
				console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_FossilPubID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_FossilPubID-display').val('');
					$('#AC_FossilPubID').val('');
					$('#AC_FossilPubID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_FossilPubID-display').val(ui.item.label);
				$('#AC_FossilPubID').val(ui.item.value);
				$('#AC_FossilPubID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
			minChars: 3
		});
		$('#AC_PhyloPubID-display').autocomplete({
			source: '/autocomplete_publications.php',
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			focus: function(event, ui) {
				console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_PhyloPubID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_PhyloPubID-display').val('');
					$('#AC_PhyloPubID').val('');
					$('#AC_PhyloPubID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_PhyloPubID-display').val(ui.item.label);
				$('#AC_PhyloPubID').val(ui.item.value);
				$('#AC_PhyloPubID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
			minChars: 3
		});

		$('#AC_FossilSpeciesID-display').autocomplete({
			source: '/autocomplete_publications.php',
		     /* source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
		     */
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
	             /* response: function(event, ui) {
				// another place to manipulate returned matches
				console.log("RESPONSE > "+ ui.content);
			},
		      */
			focus: function(event, ui) {
				console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_FossilSpeciesID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_FossilSpeciesID-display').val('');
					$('#AC_FossilSpeciesID').val('');
					//$('#AC_FossilSpeciesID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_FossilSpeciesID-display').val(ui.item.label);
				$('#AC_FossilSpeciesID').val(ui.item.value);
				//$('#AC_FossilSpeciesID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
		     /* close: function(event, ui) {
				console.log("CLOSING VALUE > "+ this.value);
			},
		      */
			minChars: 3
		});


		// init widget groups
		$('#newPublication, #existingPublication').unbind('click').click(updatePublicationWidgets);
		updatePublicationWidgets();

		$('#newCollectionAcronym, #existingCollectionAcronym').unbind('click').click(updateCollectionAcronymWidgets);
		updateCollectionAcronymWidgets();

		$('#newLocality, #existingLocality').unbind('click').click(updateLocalityWidgets);
		updateLocalityWidgets();

		$('#newFossilSpecies, #existingFossilSpecies').unbind('click').click(updateFossilSpeciesWidgets);
		updateFossilSpeciesWidgets();

		$('#newFossilPublication, #existingFossilPublication').unbind('click').click(updateFossilPublicationWidgets);
		updateFossilPublicationWidgets();

		$('#newPhylogenyPublication, #existingPhylogenyPublication, #repeatFossilPublication').unbind('click').click(updatePhylogenyPublicationWidgets);
		updatePhylogenyPublicationWidgets();

		$('input.deleteTipTaxaPair').unbind('click').click(function() {
			var $itsRow = $(this).closest('tr');
			// de-activate the autocomplete for its widgets
			$itsRow.find('.select-tip-taxa').each(function() {
				// TODO
			});
			// remove the entire row
			$itsRow.remove();
			// TODO: re-number the remaining rows?
		});
		$('#AddTipTaxaPair').unbind('click').click(function() {
			var $itsRow = $(this).closest('tr');
			var $prevRow = $itsRow.prev('tr'); // should always be a valid pair
			$itsRow.before($prevRow.clone(true));
			var $newRow = $itsRow.prev('tr');
			// clear the new row's inputs and show its delete button
			$newRow.find('input:text').val('');
			$newRow.find('.deleteTipTaxaPair').show();
			// update its visible counter and widget names/IDs
			var $itsCounter = $newRow.find('.nth-pair');
			var position = parseInt($itsCounter.text()) + 1;
			$itsCounter.text( position );
			$newRow.find('input:text[name$=A]').attr('name', 'Pair'+position+'TaxonA').attr('id', 'Pair'+position+'TaxonA');;
			$newRow.find('input:text[name$=B]').attr('name', 'Pair'+position+'TaxonB').attr('id', 'Pair'+position+'TaxonB');;
		});

	});

	// prepare widget groups and dependent widgets
	function updatePublicationWidgets() {
		if ($('#existingPublication').is(':checked')) {
			$('#pick-existing-pub').show();
			$('#enter-new-pub').hide();
		} else {
			$('#pick-existing-pub').hide();
			$('#enter-new-pub').show();
		}		
	}
	function updateCollectionAcronymWidgets() {
		if ($('#existingCollectionAcronym').is(':checked')) {
			$('#pick-existing-collection-acronym').show();
			$('#enter-new-collection-acronym').hide();
		} else {
			$('#pick-existing-collection-acronym').hide();
			$('#enter-new-collection-acronym').show();
		}		
	}
	function updateLocalityWidgets() {
		if ($('#existingLocality').is(':checked')) {
			$('#pick-existing-locality').show();
			$('#enter-new-locality').hide();
		} else {
			$('#pick-existing-locality').hide();
			$('#enter-new-locality').show();
		}		
	}
	function updateFossilPublicationWidgets() {
		if ($('#existingFossilPublication').is(':checked')) {
			$('#pick-existing-fossil-pub').show();
			$('#enter-new-fossil-pub').hide();
		} else {
			$('#pick-existing-fossil-pub').hide();
			$('#enter-new-fossil-pub').show();
		}		
	}
	function updatePhylogenyPublicationWidgets() {
		if ($('#existingPhylogenyPublication').is(':checked')) {
			$('#pick-existing-phylo-pub').show();
			$('#enter-new-phylo-pub').hide();
		} else if ($('#repeatFossilPublication').is(':checked')) {
			$('#pick-existing-phylo-pub').hide();
			$('#enter-new-phylo-pub').hide();
		} else {
			$('#pick-existing-phylo-pub').hide();
			$('#enter-new-phylo-pub').show();
		}		
	}
	function updateFossilSpeciesWidgets() {
		if ($('#existingFossilSpecies').is(':checked')) {
			$('#pick-existing-fossil-species').show();
			$('#enter-new-fossil-species').hide();
		} else {
			$('#pick-existing-fossil-species').hide();
			$('#enter-new-fossil-species').show();
		}		
	}
</script>

<form action="update_calibration.php" method="post" id="edit-calibration">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />

<div style="float: right; text-align: right;">
	<a href="#" onclick="alert('Note that any publications, fossils, or locations created will be preserved.'); return false;">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" value="Save Calibration" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new calibration" : "Edit an existing calibration (id: ".$CalibrationID.")" ?> </h1>

<div id="edit-steps">


<h3>1. Cite the initial publication of this calibration</h3>
<div>
  <p><input type="radio" name="newOrExistingPublication" id="existingPublication" checked="checked"> <label for="existingPublication">Choose an existing publication</label></input></p>
  <table id="pick-existing-pub" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>enter partial name</b></td>
    <td width="75%">
	  <input type="text" name="AC_PubID-display" id="AC_PubID-display" value="<?= testForProp($node_pub_data, 'ShortName', '') ?>" />
	  <input type="text" name="PubID" id="AC_PubID" value="<?= testForProp($node_pub_data, 'PublicationID', '') ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
                    <a href="/Show_Publications.php" target="_new" style="float: right;">Show all publications in a new window</a>
	  <div id="AC_PubID-more-info" class="text-excerpt"><?= testForProp($node_pub_data, 'FullReference', '&nbsp;') ?></p>
    </td>
  </tr>
  </table>
  <p><input type="radio" name="newOrExistingPublication" id="newPublication"> <label for="newPublication">... <b>or</b> enter a new publication into the database</label></input></p>
  <table id="enter-new-pub" class="add-form" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>short form (author, date)</b></td>
    <td width="75%" ><input type="text" name="ShortForm" id="ShortForm"></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>full citation</b></td>
    <td><input type="text" name="FullCite" id="FullCite"  size="50" style="width: 95%;"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>doi</b></td>
    <td width="79%"><input type="text" name="DOI" id="DOI"></td>
  </tr>
</table>
</div>


<h3>2. Provide some basic information</h3>
<div>
	<table width="100%" border="0">
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>node name</strong></td>
                  <td width="79%"><input type="text" name="NodeName" id="NodeName" style="width: 280px;" value="<?= testForProp($calibration_data, 'NodeName', '') ?>">
                    &nbsp;(calibration id: <em><?= ($CalibrationID > 0) ? $CalibrationID : 'new calibration' ?></em>)
                    </td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>higher taxon</strong></td>
                  <td width="79%"><select name="HigherTaxon">
                  <?php
			$currentTaxon = testForProp($calibration_data, 'HigherTaxon', '');
			while ($row = mysql_fetch_array($highertaxa_list)) {
				$thisTaxon = $row['HigherTaxon'];
				if ($currentTaxon == $thisTaxon) {
					echo '<option value="'.$row['HigherTaxon'].'" selected="selected">'.$row['HigherTaxon'].'</option>';
				} else {
					echo '<option value="'.$row['HigherTaxon'].'">'.$row['HigherTaxon'].'</option>';
				}			
			}
		  ?>
                  </select> &nbsp;(choose the most specific applicable group)</td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>minimum age (mya)</strong></td>
                  <td width="79%"><input type="text" name="MinAge" id="MinAge" size=4 style="text-align: right;" value="<?= testForProp($calibration_data, 'MinAge', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>minimum age explanation</strong></td>
                  <td><textarea name="MinAgeJust" id="MinAgeJust" cols="50" rows="5"><?= testForProp($calibration_data, 'MinAgeExplanation', '') ?></textarea></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age (mya)</strong></td>
                  <td><input type="text" name="MaxAge" id="MaxAge" size=4 style="text-align: right;" value="<?= testForProp($calibration_data, 'MaxAge', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age explanation</strong></td>
                  <td><textarea name="MaxAgeJust" id="MaxAgeJust" cols="50" rows="5"><?= testForProp($calibration_data, 'MaxAgeExplanation', '') ?></textarea></td>
                </tr>
<!--
                <tr>
                  <td align="right" valign="top"><strong>number of Node <?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?> tip taxa pairs to enter</strong></td>
                  <td><input type="text" name="NumTipPairs" id="NumTipPairs" size=3></td>
                </tr>
              <tr>
              <td>&nbsp;</td>
              <td><label>
                <input type="submit" name="CreateNode" id="CreateNode" value="+">
                <b>continue to tip entry</b></label></td>
            </tr>
-->
	</table>
</div>


<h3>3. Define tip taxa for the calibrated node</h3>
<!-- NOTE that this also incorporates the old createclade4, which was just validating these taxa -->
<div>

<p>
Enter pairs of extant taxa whose last common ancestor was the node being calibrated. You may enter tip taxa as pairs of species or any other class, e.g., genera or families. If you choose genera (or other higher-level taxa), searches on species within those taxa will also point to this common ancestor.  
</p>
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="NumNodes" value="<?=$_POST['NumNodes']?>">
<input type="hidden" name="NodeCount" value="<?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?>">
<input type="hidden" name="NumTipPairs" value="<?=$_POST['NumTipPairs']?>">

<table width="100%" border="0">
<!--
    <tr>
      <td align="right" valign="top">Specify taxa by species </td>
      <td><input type="radio" name="EntryType" value="species" id="EntryType_0" checked="checked" /> or genus? <input type="radio" name="EntryType" value="genus" id="EntryType_1" /></td>
    </tr>
-->
    <tr>
      <td width="10%" align="right" valign="top">&nbsp;</td>
      <td width="40%" style="background-color: #eee;">&nbsp; <b>Side A</b></td>
      <td width="40%" style="background-color: #eee;">&nbsp; <b>Side B</b></td>
      <td width="10%">&nbsp;</td>
    </tr>
<?php
   $NumTipPairs = 1; // TODO
   for ($i = 1; $i <= $NumTipPairs; $i++) { ?>
    <tr>
      <td align="right" valign="top"><strong>Tip pair <span class="nth-pair"><?=$i?></span></strong></td>
      <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair<?=$i?>TaxonA" id="Pair<?=$i?>TaxonA"></td>
      <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair<?=$i?>TaxonB" id="Pair<?=$i?>TaxonB"></td>
      <td><input type="button" class="deleteTipTaxaPair" style="<?= ($i == 1) ? 'display: none;' : '' ?>" value="delete" /></td>
    </tr>
<? } ?>
    <tr>
      <td>&nbsp;</td>
      <td colspan="3" style="">
	<input type="button" name="AddTipTaxaPair" id="AddTipTaxaPair" value="add a tip-taxa pair" />
      </td>
    </tr>
</table>
</div>

<h3>4. Identify the calibrated fossil species</h3>
<div>
<? /* TODO: Do we still need this section? It tries to reconcile non-matching species name (assigned to fossil) or add a new taxon,
      including some interesting metadata (beyond NCBI stuff) about authorship and PaleoDB taxon IDs.
    */ ?>
<p><input type="radio" name="newOrExistingFossilSpecies" id="existingFossilSpecies" checked="checked"> <label for="existingFossilSpecies">Choose an existing <b>species</b></label></input></p>
<table id="pick-existing-fossil-species" width="100%" border="0">
    <tr>
      <td align="right" valign="top" width="30%"><strong>enter partial name</strong></td>
      <td align="left" width="70%">
	<!-- <input type="text" name="SpeciesName" id="SpeciesName" style="width: 280px;" value=""> -->
	  <input type="text" name="AC_FossilSpeciesID-display" id="AC_FossilSpeciesID-display" value="<?= testForProp($fossil_data, 'Species', '') ?>" />
<? // reckon the matching node-ID for this species name (if name is found in NCBI and FCD names, who wins?) 
   $matchingFossilNodeID = 0; // TODO
?>
	  <input type="text" name="FossilSpeciesID" id="AC_FossilSpeciesID" value="<?= $matchingFossilNodeID ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
      </td>
    </tr>
<? /* Fuzzy matching against entered species name...
    <tr>
	<td width="70%" align="left" valign="top"><select name="SpeciesID" id="SpeciesID">
    
		<?php
			$query = "SELECT *,MATCH(TaxonName, CommonName) AGAINST ('".$_POST['SpeciesName']."') AS score FROM `fossiltaxa` WHERE MATCH(TaxonName, CommonName) AGAINST ('".$_POST['SpeciesName']."' IN NATURAL LANGUAGE MODE) ORDER BY score DESC";
			$close_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			if(mysql_num_rows($close_matches)==0) { echo "<option value=\"New\" id=\"New\">no exact match. choose a species from list or enter new taxon below.</option>"; } 
			else {
			while($row=mysql_fetch_assoc($close_matches)) {
		?>
            <option value="<?=$row['TaxonID']?>" id="<?=$row['TaxonID']?>" /><i><?=$row['TaxonName']?></i> <?=$row['TaxonAuthor']?> (<?=$row['CommonName']?>)</option>
			<?php
				}
			}
			?>
			</select>
		    </td></tr>
*/ ?>
      <tr>
	<td width="30%" align="right" valign="top"><strong>scientific name</strong></td><td width="70%" align="left" valign="top"><em id="existing-fossil-species-scientific-name">-</em></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top"><strong>common name</strong></td><td width="70%" align="left" valign="top"><em id="existing-fossil-species-common-name">-</em></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top"><strong>author and date</strong></td><td width="70%" align="left" valign="top"><input name="ExistingSpeciesAuthor" type="text" /></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top"><strong>PaleoDB taxon number</strong></td><td width="70%" align="left" valign="top"><input name="ExistingSpeciesPBDBTaxonNum" type="text" /></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top">&nbsp;</td><td width="70%" align="left" valign="top">
		<em>Changes to existing species (authorship and PaleoDB) will be reflected in all calibrations!</em>
</td>
      </tr>
</table>

<p><input type="radio" name="newOrExistingFossilSpecies" id="newFossilSpecies"> <label for="newFossilSpecies">... <b>or</b> enter a new species into the database</label></input></p>
<table id="enter-new-fossil-species" class="add-form" width="100%" border="0">
      <tr>
	<td width="30%" align="right" valign="top">Species name</td><td width="70%" align="left" valign="top"><input name="NewSpeciesName" type="text" /></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top">Common name</td><td width="70%" align="left" valign="top"><input name="NewSpeciesCommonName" type="text" /></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top">Author and date</td><td width="70%" align="left" valign="top"><input name="NewSpeciesAuthor" type="text" /></td>
      </tr>
      <tr>
	<td width="30%" align="right" valign="top">PaleoDB taxon number</td><td width="70%" align="left" valign="top"><input name="NewSpeciesPBDBTaxonNum" type="text" /></td>
      </tr>
</table>
</div>

<h3>4. Provide further details about this fossil</h3>
<div>

<p><input type="radio" name="newOrExistingLocality" id="existingLocality" checked="checked"> <label for="existingLocality">Choose an existing <b>locality</b></label></input></p>
<table id="pick-existing-locality" width="100%" border="0">
                <tr>
                  <td width="30%" align="right" valign="top"><strong>locality</strong></td>
                  <td width="70%"><select name="Locality" id="Locality">
                	<?php
			if(mysql_num_rows($locality_list)==0){
				echo "<option value=\"New\">Add a new formation below</option>";
			} else {
				mysql_data_seek($locality_list,0);
				$currentLocality = testForProp($fossil_data, 'LocalityID', '');
				while($row=mysql_fetch_assoc($locality_list)) {
					$thisLocality = $row['LocalityID'];
					if ($currentLocality == $thisLocality) {
						echo '<option value="'.$row['LocalityID'].'" selected="selected">'.$row['LocalityName'].', '.$row['Age'].'</option>';
					} else {
						echo '<option value="'.$row['LocalityID'].'">'.$row['LocalityName'].', '.$row['Age'].'</option>';
					}			
				}
				//echo "<option value=\"New\">Add new locality on next page</option>";
			} ?>
                    </select>
                </tr>
</table>
<p><input type="radio" name="newOrExistingLocality" id="newLocality"> <label for="newLocality">... <b>or</b> enter a new locality into the database</label></input></p>
<table id="enter-new-locality" class="add-form" width="100%" border="0">
  <tr>
    <td width="30%" align="right" valign="top"><b>locality name</b></td>
    <td width="70%" ><input type="text" name="LocalityName" id="LocalityName"></td>
  </tr>
  <tr>
    <td width="30%" align="right" valign="top"><b>stratum name</b></td>
    <td width="70%" ><input type="text" name="Stratum" id="Stratum"></td>
  </tr>
  <tr>
  <td align="right" valign="top" width="30%"><strong>PBDB collection num</strong></td>
  <td align="left" width="70%"><input type="text" name="PBDBNum" id="PBDBNum" ></td>
  </tr>
  <tr>
    <td align="right" valign="top" width="30%"><strong>locality notes</strong></td>
    <td align="left" width="70%"><textarea name="LocalityNotes" id="LocalityNotes" cols="50" rows="5"></textarea></td>
  </tr>
  <tr>
    <td align="right" valign="top"><strong>country</strong></td>
    <td><select name="Country" id="Country">
  	<?php
  				if(mysql_num_rows($country_list)==0){
  					echo "no countries available";
  			} else {
  					mysql_data_seek($country_list,0);
  				while($row=mysql_fetch_assoc($country_list)) {
  					echo "<option value=\"".$row['name']."\">".$row['name']."</option>";
  					}
  				}
  			?>
      </select>
  </tr>
  <tr>
    <td width="30%" align="right" valign="top"><b>top age of stratum</b></td>
    <td width="70%" ><input type="text" name="StratumMinAge" id="StratumMinAge"></td>
  </tr>
  <tr>
    <td width="30%" align="right" valign="top"><b>bottom age of stratum</b></td>
    <td width="70%" ><input type="text" name="StratumMaxAge" id="StratumMaxAge"></td>
  </tr>
  <tr>
    <td align="right" valign="top"><strong>geological age</strong></td>
    <td><select name="GeolTime" id="GeolTime">
  	<?php
	if(mysql_num_rows($geoltime_list)==0){
	?>
      <option value="0">No geological time in database</option>
  	<?php
	} else {
		mysql_data_seek($geoltime_list,0);
		while($row=mysql_fetch_assoc($geoltime_list)) {
			echo "<option value=\"".$row['GeolTimeID']."\">".$row['Age'].", ".$row['Period'].", ".$row['ShortName']."</option>";
		}

	}
	?>
      </select>
  </tr>
</table>
                
<hr/>

<p><input type="radio" name="newOrExistingCollectionAcronym" id="existingCollectionAcronym" checked="checked"> <label for="existingCollectionAcronym">Choose an existing <b>collection</b></label></input></p>
<table id="pick-existing-collection-acronym" width="100%" border="0">
                <tr>
                  <td width="30%" align="right" valign="top"><strong>collection acronym</strong></td>
                  <td width="70%"><select name="CollectionAcro" id="CollectionAcro">
                	<?php
			if(mysql_num_rows($collectionacro_list)==0){
			?>
			    <option value="0">No acronyms in database, add one below.</option>
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
					//echo "<option value=\"".$row['Acronym']."\">".$row['Acronym'].", ".$row['CollectionName']."</option>";
				}
			} ?>
                    </select>
                </tr>
</table>
<p><input type="radio" name="newOrExistingCollectionAcronym" id="newCollectionAcronym"> <label for="newCollectionAcronym">... <b>or</b> enter a new collection acronym into the database</label></input></p>
<table id="enter-new-collection-acronym" class="add-form" width="100%" border="0">
                <tr>
                  <td align="right" valign="top" width="30%"><strong>new acronym</strong></td>
                  <td align="left" width="70%"><input type="text" name="NewAcro" id="NewAcro" size="5" ></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>new institution</strong></td>
                  <td align="left" width="70%"><input type="text" name="NewInst" id="NewInst" ></td>
                </tr>
</table>

<hr/>

<table width="100%" border="0">
                <tr>
                  <td align="right" valign="top" width="30%"><strong>collection number</strong></td>
                  <td align="left" width="70%"><input type="text" name="CollectionNum" id="CollectionNum" value="<?= testForProp($fossil_data, 'CollectionNumber', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>minimum age</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossilMinAge" id="FossilMinAge" size=3 value="<?= testForProp($fossil_data, 'MinAge', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>minimum age type</strong></td>
                  <td><select name="MinAgeType" id="MinAgeType">
                	<?php
			if(mysql_num_rows($agetypes_list)==0){
			?>
			    <option value="0">No age types in database</option>
                	<?php
			} else {
				mysql_data_seek($agetypes_list,0);
				$currentMinAgeType = testForProp($fossil_data, 'MinAgeType', '');
				while($row=mysql_fetch_assoc($agetypes_list)) {
					$thisMinAgeType = $row['AgeTypeID'];
					if ($currentMinAgeType == $thisMinAgeType) {
						echo '<option value="'.$row['AgeTypeID'].'" selected="selected">'.$row['AgeType'].'</option>';
					} else {
						echo '<option value="'.$row['AgeTypeID'].'">'.$row['AgeType'].'</option>';
					}			
				}
			} ?>
                    </select>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>maximum age</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossilMaxAge" id="FossilMaxAge" size=3 value="<?= testForProp($fossil_data, 'MaxAge', '') ?>"></td>
                </tr>
                 <tr>
                  <td align="right" valign="top"><strong>maximum age type</strong></td>
                  <td><select name="MaxAgeType" id="MaxAgeType">
                	<?php
			if(mysql_num_rows($agetypes_list)==0){
			?>
			    <option value="0">No age types in database</option>
                	<?php
			} else {
				mysql_data_seek($agetypes_list,0);
				$currentMaxAgeType = testForProp($fossil_data, 'MaxAgeType', '');
				while($row=mysql_fetch_assoc($agetypes_list)) {
					$thisMaxAgeType = $row['AgeTypeID'];
					if ($currentMaxAgeType == $thisMaxAgeType) {
						echo '<option value="'.$row['AgeTypeID'].'" selected="selected">'.$row['AgeType'].'</option>';
					} else {
						echo '<option value="'.$row['AgeTypeID'].'">'.$row['AgeType'].'</option>';
					}			
				}
			} ?>
                    </select>
                </tr>
                
                 <tr>
                  <td align="right" valign="top"><strong>phylogenetic justification type</strong></td>
                  <td><select name="PhyJustType" id="PhyJustType">
                	<?php
			if(mysql_num_rows($phyjusttype_list)==0){
			?>
			    <option value="0">No justification types in database</option>
                	<?php
			} else {
				mysql_data_seek($phyjusttype_list,0);
				$currentPhyloJustType = testForProp($fossil_data, 'PhyJustificationType', '');
				while($row=mysql_fetch_assoc($phyjusttype_list)) {
					$thisPhyloJustType = $row['PhyloJustID'];
					if ($currentPhyloJustType == $thisPhyloJustType) {
						echo '<option value="'.$row['PhyloJustID'].'" selected="selected">'.$row['PhyloJustType'].'</option>';
					} else {
						echo '<option value="'.$row['PhyloJustID'].'">'.$row['PhyloJustType'].'</option>';
					}			
				}
			} ?>
                    </select>
                </tr>
                
                
                <tr>
                  <td align="right" valign="top" width="30%"><strong>phylogenetic justification</strong></td>
                  <td align="left" width="70%"><textarea name="PhyJustification" id="PhyJustification" cols="50" rows="5"><?= testForProp($fossil_data, 'PhyJustification', '') ?></textarea></td>
                </tr>
</table>
                
<hr/>

<p><input type="radio" name="newOrExistingFossilPublication" id="existingFossilPublication" checked="checked"> <label for="existingFossilPublication">Choose an existing <b>fossil publication</b></label></input></p>
<table id="pick-existing-fossil-pub" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>enter partial name</b></td>
    <td width="75%">
	  <input type="text" name="AC_FossilPubID-display" id="AC_FossilPubID-display" value="<?= testForProp($fossil_pub_data, 'ShortName', '') ?>" />
	  <input type="text" name="FossilPub" id="AC_FossilPubID" value="<?= testForProp($fossil_pub_data, 'PublicationID', '') ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
                    <a href="/Show_Publications.php" target="_new" style="float: right;">Show all publications in a new window</a>
	  <div id="AC_FossilPubID-more-info" class="text-excerpt"><?= testForProp($fossil_pub_data, 'FullReference', '&nbsp;') ?></p>
    </td>
  </tr>
<!--
                <tr>
                  <td align="right" valign="top"><strong>fossil publication</strong></td>
                  <td><select name="FossilPub" id="FossilPub">
                	<?php
						if(mysql_num_rows($publication_list)==0){
							echo "<option value=\"New\">Add new publication below</option>";
					} else {
							mysql_data_seek($publication_list,0);
							echo "<option value=\"New\">Add new publication below or choose from list</option>";
						while($row=mysql_fetch_assoc($publication_list)) {
							echo "<option value=\"".$row['PublicationID']."\">".$row['ShortName']." (ID:".$row['PublicationID'].")</option>";
							}
						}
					?>
                    </select>
                    (<a href="/Show_Publications.php" target="_new">Show complete citations</a>)</td>
                </tr>
-->
</table>
<p><input type="radio" name="newOrExistingFossilPublication" id="newFossilPublication"> <label for="newFossilPublication">... <b>or</b> enter a new publication into the database</label></input></p>
<table id="enter-new-fossil-pub" class="add-form" width="100%" border="0">
                <tr>
                  <td align="right" valign="top" width="30%"><strong>short form (author, date)</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossShortForm" id="FossShortForm" size="10"></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>full citation</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossFullCite" id="FossFullCite" style="width: 95%;"></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>doi</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossDOI" id="FossDOI" size="10"></td>
                </tr>
</table>
                
<hr/>

<p><input type="radio" name="newOrExistingPhylogenyPublication" id="existingPhylogenyPublication" checked="checked"> <label for="existingPhylogenyPublication">Choose an existing <b>phylogeny publication</b></label></input></p>
<table id="pick-existing-phylo-pub" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>enter partial name</b></td>
    <td width="75%">
	  <input type="text" name="AC_PhyloPubID-display" id="AC_PhyloPubID-display" value="<?= testForProp($fossil_pub_data, 'ShortName', '') ?>" />
	  <input type="text" name="PhyPub" id="AC_PhyloPubID" value="<?= testForProp($fossil_pub_data, 'PublicationID', '') ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
                    <a href="/Show_Publications.php" target="_new" style="float: right;">Show all publications in a new window</a>
	  <div id="AC_PhyloPubID-more-info" class="text-excerpt"><?= testForProp($fossil_pub_data, 'FullReference', '&nbsp;') ?></p>
    </td>
  </tr>
<!--
                <tr>
                  <td align="right" valign="top"><strong>phylogeny publication</strong></td>
                  <td><select name="PhyPub" id="PhyPub">
                	<?php
						if(mysql_num_rows($publication_list)==0){
							echo "<option value=\"New\">Add new publication below</option>";
					} else {
							mysql_data_seek($publication_list,0);
							echo "<option value=\"New\">Add new publication below or choose from list</option>";
							while($row=mysql_fetch_assoc($publication_list)) {
							echo "<option value=\"".$row['PublicationID']."\">".$row['ShortName']." (ID:".$row['PublicationID'].")</option>";
							}
						}
					?>
                    </select>
                    (<a href="Show_Publications.php" target="_new">Show complete citations</a>)</td>
                </tr>
-->
</table>
<p><input type="radio" name="newOrExistingPhylogenyPublication" id="repeatFossilPublication"> <label for="repeatFossilPublication">... <b>or</b> re-use the fossil publication above</label></input></p>
<p><input type="radio" name="newOrExistingPhylogenyPublication" id="newPhylogenyPublication"> <label for="newPhylogenyPublication">... <b>or</b> enter a new publication into the database</label></input></p>
<table id="enter-new-phylo-pub" class="add-form" width="100%" border="0">
                <tr>
                  <td align="right" valign="top" width="30%"><strong>short form (author, date)</strong></td>
                  <td align="left" width="70%"><input type="text" name="PhyloShortForm" id="PhyloShortForm" size="10"></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>full citation</strong></td>
                  <td align="left" width="70%"><input type="text" name="PhyloFullCite" id="PhyloFullCite" style="width: 95%;"></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>doi</strong></td>
                  <td align="left" width="70%"><input type="text" name="PhyloDOI" id="PhyloDOI" size="10"></td>
                </tr>
</table>

</div><!-- END of final step -->

</div><!-- END of div#edit-steps -->

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="#" onclick="alert('Note that any publications, fossils, or locations created will be preserved.'); return false;">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" value="Save Calibration" />
</div>

</form><!-- END of form#edit-calibration -->


<?php 
//open and print page footer template
require('../footer.php');
?>

