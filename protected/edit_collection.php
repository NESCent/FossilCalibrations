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

// test up front for collection ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$AcroID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$AcroID = 0;
	$addOrEdit = 'ADD';
}

$collection_data = null;

if ($addOrEdit == 'EDIT') {
	// retrieve the main record for this collection
	$query="SELECT * FROM L_CollectionAcro WHERE AcroID = '".mysql_real_escape_string($AcroID)."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$collection_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
}

/*
 * Query for controlled lists of misc values
 */

// list of all existing collection acronyms (to watch for duplicates)
$query='SELECT * FROM L_CollectionAcro ORDER BY Acronym';
$collectionacro_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// Build a complete edit form in one page
?>

<form id="edit-collection-form" action="update_collection.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />
<input type="hidden" name="addOrEdit" value="<?= $addOrEdit; ?>" />
<input type="hidden" id="AcroID" name="AcroID" value="<?= $AcroID; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_collections.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Collection" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new collection" : "Edit an existing collection (id: ".$AcroID.")" ?> </h1>

<table id="xxxxxx" width="100%" border="0">
  <tr>
    <td width="30%" align="right" valign="top"><b>acronym</b></td>
    <td>&nbsp; <input type="text" name="Acronym" id="Acronym" style="width: 200px;" value="<?= testForProp($collection_data, 'Acronym', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>collection name</b></td>
    <td>&nbsp; <input type="text" name="CollectionName" id="CollectionName" style="width: 200px;" value="<?= testForProp($collection_data, 'CollectionName', '') ?>" ></td>
  </tr>
</table>

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_collections.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Collection" />
</div>

</form>

<script type="text/javascript">

   var existingCollectionAcronyms = [ ];
<? while ($row = mysql_fetch_array($collectionacro_list)) { ?>
   existingCollectionAcronyms.push( '<?= $row['Acronym'] ?>'.trim() );
<? } ?>
   var currentAcronym = '<?= $collection_data['Acronym'] ?>'.trim();  // incoming acronym, can of course be unchanged

   $(document).ready(function() {
	$('#edit-collection-form').submit(function() {
		var proposedAcronym = $('#Acronym').val().trim();
		if (proposedAcronym === '') {
			alert("Collection acronym field cannot be empty!");
			return false; // block form submission
		}
		if ((proposedAcronym !== currentAcronym) && 
		    (existingCollectionAcronyms.indexOf(proposedAcronym) !== -1)) {
			alert("There's already a collection with this acronym!");
			return false; // block form submission
		}
		if ($('#CollectionName').val().trim() === '') {
			alert("Collection name field cannot be empty!");
			return false; // block form submission
		}
		return true; // allow normal submission
	});
   });

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
