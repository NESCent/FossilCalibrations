<?php 
/*
 * This is a faceless script that tries to delete a publication record, and 
 * any accompanying image file, then redirect to the main publications list.
 * 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */

// open and load site variables
require('../../config.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$publicationID = $_GET['id'];

$query="DELETE FROM publications
	WHERE PublicationID = '". mysql_real_escape_string($publicationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

$query="DELETE FROM publication_images
	WHERE PublicationID = '". mysql_real_escape_string($publicationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/manage_publications.php');
exit();
?>
