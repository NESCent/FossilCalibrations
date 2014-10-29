<?php 
/*
 * This is a faceless script that tries to add or update a fossil record.
 *
 * NOTE: For now, we should just be editing fossils here! New fossils can be
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
    echo ' <a href="/protected/edit_fossil.php?id='. $_POST['FossilID'] .'">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

$addOrEdit = $_POST['addOrEdit']; // should be 'ADD' or 'EDIT'
// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($addOrEdit == 'ADD') {
    echo 'This form cannot be used to add new fossils! Please choose an existing fossil to edit, or add new fossils in the calibration editor.';  
    echo ' <a href="/protected/manage_fossils.php">Manage Fossils</a><br/><br/>';
    return;
}

echo '<pre>'.print_r($_POST, true).'</pre>';

$requestedAction = $_POST['requestedAction'];
$fossilID = $_POST['FossilID'];

if ($requestedAction == 'Save Fossil') {

	/* Add or update the fossil record
	 */

	// list all new OR updated values
	$newValues = "
			 FossilID = '". 	mysql_real_escape_string($_POST['FossilID']) ."'
			,CollectionAcro = '". 	mysql_real_escape_string($_POST['CollectionAcro']) ."'
			,CollectionNumber = '".	mysql_real_escape_string($_POST['CollectionNumber']) ."'
			,LocalityID = '". 	mysql_real_escape_string($_POST['LocalityID']) ."'
			,FossilPub = '". 	mysql_real_escape_string($_POST['FossilPub']) ."'
	";
	$query="INSERT INTO fossils
		SET $newValues
		ON DUPLICATE KEY UPDATE $newValues";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossilID = mysql_insert_id();
	if ($fossilID == 0) {
		// this fossil already exists, keep the original ID
		$fossilID = $_POST['FossilID'];
	}

/* Should we allow deletion from the Edit Fossil page? NOTE that there's already a dedicated
 * page 'delete_fossil.php' called from the fossil list.
 *
} else if ($requestedAction == 'delete fossil') {
	// TODO: handle consequences to this! e.g, un-link this fossil from calibrations
	$query="DELETE FROM fossils WHERE
		FossilID = '". mysql_real_escape_string($fossilID) ."'";
	mysql_query($query) or die('Error, query failed');
*/

} else {
	echo "Unknown requestedAction! ($requestedAction)";
	exit();
}

// NOTE that we're careful to return to a new fossil with its new assigned ID
echo '<a href="/protected/edit_fossil.php?id='. $fossilID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_fossil.php?id='. $fossilID .'&result=success');
exit();
?>
