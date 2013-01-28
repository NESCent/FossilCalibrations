<?php 
/*
 * This is a faceless script that tries to add or update calibration records. It expects to
 * find a number of dependent records (publications, fossils, etc) already in place.
 */

// open and load site variables
require('../Site.conf');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($_SESSION['nonce'] != $_POST['nonce']) {
    echo 'This form has already been submitted!';  
    return;
    //die('This form has already been submitted!');  
    // TODO: Nicely redirect somewhere else instead?
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

// set useful variables (assert defaults if not provided?)
$NodeName= isset($_POST['NodeName']) ? $_POST['NodeName'] : '?';
$CalibrationID= isset($_POST['CalibrationID']) ? $_POST['CalibrationID'] : '?';
$NumTipPairs= isset($_POST['NumTipPairs']) ? $_POST['NumTipPairs'] : '?';
$NumFossils= isset($_POST['NumFossils']) ? $_POST['NumFossils'] : '?';
$NumNodes= isset($_POST['NumNodes']) ? $_POST['NumNodes'] : '?';
$NodeCount= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?';
$publicationID= isset($_POST['PubID']) ? $_POST['PubID'] : '?';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_calibration.php?id='. $_POST['id'] .'&result=success');
exit();
?>
