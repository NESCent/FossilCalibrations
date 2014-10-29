<?php 
/*
 * This is a faceless script that tries to delete a fossil record, then
 * redirect to the main fossils list.
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

$fossilID = $_GET['id'];

$query="DELETE FROM fossils
	WHERE FossilID = '". mysql_real_escape_string($fossilID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// TODO: delete any related/dependent records?

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/manage_fossils.php');
exit();
?>
