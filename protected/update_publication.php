<?php 
/*
 * This is a faceless script that tries to add or update an publication record, including 
 * an (optional) accomponpanying image file.
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
    echo ' <a href="/protected/edit_publication.php?id='. $_POST['PublicationID'] .'">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

echo '<pre>'.print_r($_POST, true).'</pre>';

$addOrEdit = $_POST['addOrEdit']; // should be 'ADD' or 'EDIT'
$requestedAction = $_POST['requestedAction'];
$publicationID = $_POST['PublicationID'];

if ($requestedAction == 'Save Publication') {

	/* Add or update the publication record
	 */

	// list all new OR updated values
	$newValues = "
			 PublicationID = '". mysql_real_escape_string($_POST['PublicationID']) ."'
			,ShortName = '". mysql_real_escape_string($_POST['ShortForm']) ."'
			,FullReference = '". mysql_real_escape_string($_POST['FullCite']) ."'
			,DOI = '". mysql_real_escape_string($_POST['DOI']) ."'
			,PublicationStatus = '". mysql_real_escape_string($_POST['PublicationStatus']) ."'
	";
	$query="INSERT INTO publications
		SET $newValues
		ON DUPLICATE KEY UPDATE $newValues";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$publicationID = mysql_insert_id();
	if ($publicationID == 0) {
		// this publication already exists, keep the original ID
		$publicationID = $_POST['PublicationID'];
	}

	// insert (or update) the featured image for this publication
	if ($_FILES['FeaturedImage']['size'] > 0) {
		$tmpName=$_FILES['FeaturedImage']['tmp_name']; // name of the temporary stored file name
		// Read the file
		$fp = fopen($tmpName, 'r');
		$imgContent = fread($fp, filesize($tmpName));
		$imgContent = addslashes($imgContent);
		$imgContent1=base64_encode($imgContent);
		fclose($fp); // close the file handle
		 
		$newValues = "
				 PublicationID = '". mysql_real_escape_string($publicationID) ."'
				,image = '". $imgContent ."'
				,caption = '". mysql_real_escape_string($_POST['ImageCaption']) ."'
		";
		$query="INSERT INTO publication_images
			SET $newValues
			ON DUPLICATE KEY UPDATE $newValues";
		mysql_query($query) or die('Error, query failed');
	} else {
		// try to update just the caption, if we have a featured-image record to store it in
		$query="UPDATE publication_images
			SET caption = '". mysql_real_escape_string($_POST['ImageCaption']) ."'
			WHERE PublicationID = '". mysql_real_escape_string($publicationID) ."'";
		mysql_query($query) or die('Error, query failed<br><br>'.$query);
	}

} else if ($requestedAction == 'delete image') {
	$query="DELETE FROM publication_images WHERE
		PublicationID = '". mysql_real_escape_string($publicationID) ."'";
	mysql_query($query) or die('Error, query failed');
} else {
	echo "Unknown requestedAction! ($requestedAction)";
	exit();
}

// NOTE that we're careful to return to a new publication with its new assigned ID
echo '<a href="/protected/edit_publication.php?id='. $publicationID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_publication.php?id='. $publicationID .'&result=success');
exit();
?>
