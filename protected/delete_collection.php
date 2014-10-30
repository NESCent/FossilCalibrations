<?php 
/*
 * This is a faceless script that tries to delete a collection record, then
 * redirect to the main collections list.
 * 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */

// open and load site variables
require('../../config.php');
require('../FCD-helpers.php');

// secure this page
requireRoleOrLogin('ADMIN');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$collectionID = $_GET['id'];

$query="DELETE FROM L_CollectionAcro
	WHERE AcroID = '". mysql_real_escape_string($collectionID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// TODO: delete any related/dependent records?

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/manage_collections.php');
exit();
?>
