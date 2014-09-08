<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>

<?php
// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$key=array_keys($_GET);
$value=array_values($_GET);

//retrieve calibrations for fossil ID
if (!isset($key[0])) {
	$key[0] = 'SHOW ALL';	// default, if no query-string args were provided
}
switch($key[0]) {
   case 'Species':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE Species LIKE "%'. mysql_real_escape_string($value[0]) .'%" ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	break;

    case 'FossilMinAge':
	// treat missing min-age argument as "unbounded" minimum
	$trimmedMin = trim( $_GET['FossilMinAge'] );
	$minProvided = !empty($trimmedMin);
	$carefulMinAge = $minProvided ? $trimmedMin : " 0 ";
	//
	// treat missing max-age argument as "unbounded" maximum
	$trimmedMax = trim( $_GET['FossilMaxAge'] );
	$maxProvided = !empty($trimmedMax);
	$carefulMaxAge = $maxProvided ? $trimmedMax : " 1000000 ";
	//
	$query='Select DISTINCT C.*, J.FossilMinAge, J.FossilMaxAge FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE FossilMinAge>'. mysql_real_escape_string($carefulMinAge) .' AND FossilMaxAge<'. mysql_real_escape_string($carefulMaxAge) .' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	//
	// show adaptive prompt, based on which age boundaries were provided
	echo '<h1>'. mysql_num_rows($calibration_list) .' calibration'. (mysql_num_rows($calibration_list) == 1 ? '' : 's') .' with';
	if ($minProvided) {
		echo ' minimum age "'. $carefulMinAge .' Ma"';
	}
	if ($minProvided and $maxProvided) {
		echo ' and ';
	}
	if ($maxProvided) {
		echo ' maximum age "'. $carefulMaxAge .' Ma"';
	}
	if (!$minProvided and !$maxProvided) {
		echo 'in all time periods';
	}
	echo '</h1>';
	break;
	
    case 'HigherTaxon':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '. mysql_real_escape_string($key[0]) .'=\''. mysql_real_escape_string($value[0]) .'\' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	//
	// show adaptive prompt
	echo '<h1>'. mysql_num_rows($calibration_list) .' calibration'. (mysql_num_rows($calibration_list) == 1 ? '' : 's') .' found under clade "'. $value[0] .'"</h1>';
	break;

    case 'System':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '. mysql_real_escape_string($key[0]) .'=\''. mysql_real_escape_string($value[0]) .'\' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	break;

    case 'Period':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '. mysql_real_escape_string($key[0]) .'=\''. mysql_real_escape_string($value[0]) .'\' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	break;

    case 'Epoch':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '. mysql_real_escape_string($key[0]) .'=\''. mysql_real_escape_string($value[0]) .'\' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	break;

    case 'Age':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '. mysql_real_escape_string($key[0]) .'=\''. mysql_real_escape_string($value[0]) .'\' ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	//
	// show adaptive prompt
	echo '<h1>'. mysql_num_rows($calibration_list) .' calibration'. (mysql_num_rows($calibration_list) == 1 ? '' : 's') .' found in "'. $value[0] .'" age</h1>';
	break;


    case 'SHOW ALL':
    default:
	// unknown (or no) query-string arguments provided; show all calibrations by default
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	//
	// show adaptive prompt
	echo '<h1>'. mysql_num_rows($calibration_list) .' calibration'. (mysql_num_rows($calibration_list) == 1 ? '' : 's') .' found</h1>';
}

echo '<p>Click on "Show calibration" to view full calibration information.  Click on node name or publication name to find related calibrations.</p>';

?>

<table width="100%" border="0">

  <tr>
    <td width="10%" align="center" valign="middle" class="small_orange"><strong>Info</strong></td>
    <td width="5%" align="center" valign="middle" class="small_orange"><strong>CalibrationID</strong></td>
    <td width="15%" align="center" valign="middle" class="small_orange"><strong>Node Name</strong></td>
    <td width="5%" align="center" valign="middle" class="small_orange"><strong>Min Age</strong></td>
    <td width="5%" align="center" valign="middle" class="small_orange"><strong>Max Age</strong></td>
    <td width="15%" align="center" valign="middle" class="small_orange"><strong>Publication</strong></td>
  </tr>

<?php
while ($row = mysql_fetch_array($calibration_list)) {
?>
  
  <tr align="center" valign="top">
    <td><a href="Show_Calibration.php?CalibrationID=<?=$row['CalibrationID']?>">Show calibration</a></td>
    <td><?=$row['CalibrationID']?></td>
    <td><a href="#" onclick="alert('Related calibrations COMING SOON...'); return false;"><?=$row['NodeName']?></a></td>
    <td><?=$row['MinAge']?>
	<? if (isset($_GET["test"])) { ?><i class="diagnostic">&nbsp; (<?=$row['FossilMinAge']?>)</i><? } ?>
    </td>
    <td><?=$row['MaxAge']?>  
	<? if (isset($_GET["test"])) { ?><i class="diagnostic">&nbsp; (<?=$row['FossilMaxAge']?>)</i><? } ?>
    </td>
    <td><?=$row['ShortName']?></td>
  </tr>

<?php } ?>  
  
</table>

<? if (isset($_GET["test"])) { echo("<br/><br/><i class='diagnostic'>$query</i>"); } ?>

<?php 
//open and print page footer template
require('footer.php');
?>
