<?php 
/*
 * This is a faceless script that tries to delete a calibration record, and 
 * any accompanying records, then redirect to the main calibrations list.
 *
 * NOTE that we'll keep lots of incidental records that may be associated with
 * this calibration, but remain useful to others, like fossils, collections,
 * locations, etc.
 * 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */

// open and load site variables
require('../../config.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$calibrationID = $_GET['id'];

// delete the main calibration record
$query="DELETE FROM calibrations WHERE 
        CalibrationID = '". mysql_real_escape_string($calibrationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


// delete associations between this calibration and fossils
$query="DELETE FROM Link_CalibrationFossil WHERE 
        CalibrationID = '". mysql_real_escape_string($calibrationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


// delete associations between this calibration and tip-taxa pairs
$query="DELETE FROM Link_CalibrationPair WHERE 
        CalibrationID = '". mysql_real_escape_string($calibrationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


// TODO: delete associated FCD trees


// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/manage_calibrations.php');
exit();
?>
