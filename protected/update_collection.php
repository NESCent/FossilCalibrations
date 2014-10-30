<?php 
/*
 * This is a faceless script that tries to add or update a collection record.
 *
 * NOTE: For now, we should just be editing collections here! New collections can be
 * more easily entered in the calibration editor.
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
    echo ' <a href="/protected/edit_collection.php?id='. $_POST['AcroID'] .'">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

$addOrEdit = $_POST['addOrEdit']; // should be 'ADD' or 'EDIT'
// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($addOrEdit == 'ADD') {
    echo 'This form cannot be used to add new collections! Please choose an existing collection to edit, or add a new one in the calibration editor.';  
    echo ' <a href="/protected/manage_collections.php">Manage Collections</a><br/><br/>';
    return;
}

echo '<pre>'.print_r($_POST, true).'</pre>';
$requestedAction = $_POST['requestedAction'];
$collectionID = $_POST['AcroID'];

if ($requestedAction == 'Save Collection') {

	/* Capture its previous acronym for replacement below
	 */
	$previousAcronym = '';
	$query="SELECT * FROM L_CollectionAcro
		WHERE AcroID = '". mysql_real_escape_string($_POST['AcroID']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	if (mysql_num_rows($result) > 0) {
		$previousAcronym = mysql_fetch_array($result)['Acronym'];
	}
	mysql_free_result($result);

	/* Add or update the fossil record
	 */

	// list all new OR updated values
	$newValues = "
			 AcroID = '". 	mysql_real_escape_string($_POST['AcroID']) ."'
			,Acronym = '". 	mysql_real_escape_string($_POST['Acronym']) ."'
			,CollectionName = '". 	mysql_real_escape_string($_POST['CollectionName']) ."'
	";
	$query="INSERT INTO L_CollectionAcro
		SET $newValues
		ON DUPLICATE KEY UPDATE $newValues";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$collectionID = mysql_insert_id();
	if ($collectionID == 0) {
		// this fossil already exists, keep the original ID
		$collectionID = $_POST['AcroID'];
	}
	mysql_free_result($result);


	/* Find any fossils in the database using the previous acronym, and update them to use the new one.
	 */
	if (!empty($previousAcronym)) {
		$query="UPDATE fossils
			SET CollectionAcro = '". mysql_real_escape_string($_POST['Acronym']) ."'
			WHERE CollectionAcro = '". $previousAcronym ."'";
		$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		mysql_free_result($result);
	}


/* Should we allow deletion from the Edit Collection page? NOTE that there's already a dedicated
 * page 'delete_collection.php' called from the collection list.
 *
} else if ($requestedAction == 'delete collection') {
	// TODO: handle consequences to this! e.g, un-link this collection from all fossils
	$query="DELETE FROM L_CollectionAcro WHERE
		AcroID = '". mysql_real_escape_string($collectionID) ."'";
	mysql_query($query) or die('Error, query failed');
*/

} else {
	echo "Unknown requestedAction! ($requestedAction)";
	exit();
}

// NOTE that we're careful to return to a new collection with its new assigned ID
echo '<a href="/protected/edit_collection.php?id='. $collectionID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_collection.php?id='. $collectionID .'&result=success');
exit();
?>
