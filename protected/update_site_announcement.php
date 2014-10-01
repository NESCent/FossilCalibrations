<?php 
/*
 * This is a faceless script that tries to add or update the site announcement.
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
    echo ' <a href="/protected/edit_site_announcement.php">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

echo '<pre>'.print_r($_POST, true).'</pre>';

$requestedAction = $_POST['requestedAction'];

if ($requestedAction == 'Save Announcement') {
	$query="UPDATE site_status SET
		announcement_title = '". mysql_real_escape_string($_POST['announcement_title']) ."',
		announcement_body = '". mysql_real_escape_string($_POST['announcement_body']) ."'";
	mysql_query($query) or die('Error, query failed');

} else if ($requestedAction == 'Clear Announcement') {
	$query="UPDATE site_status SET
		announcement_title = '',
		announcement_body = ''";
	mysql_query($query) or die('Error, query failed');

} else {
	echo "Unknown requestedAction! ($requestedAction)";
	exit();
}

// if there was an error, offer a way back to the editor
echo '<a href="/protected/edit_site_announcement.php">return to editor</a><br/><br/>';

// bounce to the site's home page, to show the new announcement (if any)
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_site_announcement.php');
exit();
?>
