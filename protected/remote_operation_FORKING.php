<?php
/* Let's handle long-running operations by forking the PHP process. This 
 * means that the same code will be run in two instances, one parent (returns
 * immediately) and one child process that goes on until completion. For this
 * reason, we should suspend normal PHP timeout values.
 *
 * Also, wait til we're in a child process before establishing any DB
 * connections.
 * 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);

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
   case 'UPDATE_AUTOCOMPLETE':
      $pid = pcntl_fork();
      if($pid == -1) {
         // Something went wrong (handle errors here)
         echo "{'ERROR': 'failure forking PHP!'}";
         return;
      } elseif($pid == 0) {
         // This part is only executed in the child

         // connect to mySQL server and select the Fossil Calibration database
         $connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
         mysql_select_db('FossilCalibration') or die ('Unable to select database!');

         $query="CALL refreshAutoCompleteTables('FINAL')";
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         //mysql_free_result($result);
         return;

      } else {
        // This part is only executed in the parent
        echo "{'child PID': $pid}";
        return;
      }

   case 'UPDATE_MULTITREE':
      $pid = pcntl_fork();
      if($pid == -1) {
         // Something went wrong (handle errors here)
         echo "{'ERROR': 'failure forking PHP!'}";
         return;
      } elseif($pid == 0) {
         // This part is only executed in the child

         // connect to mySQL server and select the Fossil Calibration database
         $connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
         mysql_select_db('FossilCalibration') or die ('Unable to select database!');

         $query="CALL refreshMultitree('FINAL')";
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         //mysql_free_result($result);
         return;

      } else {
        // This part is only executed in the parent
        echo "{'child PID': $pid}";
        return;
      }

   case 'CHECK_UPDATE_STATUS':
      // This command does not spawn a child!

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
