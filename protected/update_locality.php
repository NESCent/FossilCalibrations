<?php 
/*
 * This is a faceless script that tries to add or update a locality record.
 *
 * NOTE: For now, we should just be editing localities here! New localities can be
 * easily entered in the calibration editor.
 * 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */

// open and load site variables
require('../../config.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($_SESSION['nonce'] != $_POST['nonce']) {
    echo 'This form has already been submitted!';  
    echo ' <a href="/protected/edit_locality.php?id='. $_POST['LocalityID'] .'">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

$addOrEdit = $_POST['addOrEdit']; // should be 'ADD' or 'EDIT'
// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($addOrEdit == 'ADD') {
    echo 'This form cannot be used to add new localities! Please choose an existing locality to edit, or add new localities in the calibration editor.';  
    echo ' <a href="/protected/manage_localities.php">Manage Fossils</a><br/><br/>';
    return;
}

echo '<pre>'.print_r($_POST, true).'</pre>';

$requestedAction = $_POST['requestedAction'];
$localityID = $_POST['LocalityID'];

if ($requestedAction == 'Save Fossil') {

	/* Add or update the locality record
	 */

	// list all new OR updated values
	// NOTE: MinAge and MaxAge fields are not currently used!
	$newValues = "
			 LocalityID = '". 	mysql_real_escape_string($_POST['LocalityID']) ."'
			,LocalityName = '". 	mysql_real_escape_string($_POST['LocalityName']) ."'
			,Stratum = '".	mysql_real_escape_string($_POST['Stratum']) ."'
			,GeolTime = '". 	mysql_real_escape_string($_POST['GeolTime']) ."'
			,Country = '". 	mysql_real_escape_string($_POST['Country']) ."'
			,LocalityNotes = '". 	mysql_real_escape_string($_POST['LocalityNotes']) ."'
			,PBDBCollectionNum = '". 	mysql_real_escape_string($_POST['PBDBCollectionNum']) ."'
	";
	$query="INSERT INTO localities
		SET $newValues
		ON DUPLICATE KEY UPDATE $newValues";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$localityID = mysql_insert_id();
	if ($localityID == 0) {
		// this locality already exists, keep the original ID
		$localityID = $_POST['LocalityID'];
	}

/* Should we allow deletion from the Edit Locality page? NOTE that there's already a dedicated
 * page 'delete_locality.php' called from the locality list.
 *
} else if ($requestedAction == 'delete locality') {
	// TODO: handle consequences to this! e.g, un-link this locality from fossils
	$query="DELETE FROM localities WHERE
		LocalityID = '". mysql_real_escape_string($localityID) ."'";
	mysql_query($query) or die('Error, query failed');
*/

} else {
	echo "Unknown requestedAction! ($requestedAction)";
	exit();
}

// NOTE that we're careful to return to a new locality with its new assigned ID
echo '<a href="/protected/edit_locality.php?id='. $localityID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_locality.php?id='. $localityID .'&result=success');
exit();
?>
