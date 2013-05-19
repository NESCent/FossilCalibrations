<?php
/* Support a few database operations from the admin dashboard. Simple calls
 * will return normally, while long-running tasks will be handled by invoking
 * mysql directly on the server. (In these cases, we're using flags and status
 * markers in the site_status table to communicate progress.)
 */
// open and load site variables
require('../Site.conf');
require('../FCD-helpers.php');

// this page requires admin login
requireRoleOrLogin('ADMIN');

// retrieve the requested operation
if (!isset($_POST['operation'])) {
	echo "{'ERROR': 'operation not submitted!'}";
	return;
}
$operation = $_POST['operation'];


switch($operation) {
   case 'REBUILD_ALL_CALIBRATION_TREES':
      runSQLScript('protected/REBUILD_ALL_CALIBRATION_TREES.sql');
      echo "['OK']";
      return;

   case 'UPDATE_AUTOCOMPLETE':
      runSQLScript('protected/UPDATE_AUTOCOMPLETE.sql');
      echo "['OK']";
      return;

   case 'UPDATE_CALIBRATIONS_BY_CLADE':
      runSQLScript('protected/UPDATE_CALIBRATIONS_BY_CLADE.sql');
      echo "['OK']";
      return;

   case 'UPDATE_MULTITREE':
      runSQLScript('protected/UPDATE_MULTITREE.sql');
      echo "['OK']";
      return;

   case 'CHECK_UPDATE_STATUS':
      // connect to mySQL server and select the Fossil Calibration database 
      $connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
      mysql_select_db('FossilCalibration') or die ('Unable to select database!');

      $query="SELECT * FROM site_status";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $site_status = mysql_fetch_assoc($result);
      mysql_free_result($result);

      // return our best guess for all fields, or empty values (to clear the UI) if no match was found
      echo json_encode($site_status);
      return;

   default:
      echo "{'ERROR': 'unknown operation submitted: [$operation]'}";
      return;
}
?>
