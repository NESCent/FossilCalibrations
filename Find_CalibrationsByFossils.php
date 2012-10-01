<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('Header.txt');
?>

<?php
// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$key=array_keys($_GET);
$value=array_values($_GET);

//retrieve calibrations for fossil ID
switch($key[0]) {
case 'Species':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE Species LIKE "%'.$value[0].'%" ORDER BY NodeName';
	break;

case 'FossilMinAge':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE FossilMinAge>'.$_GET['FossilMinAge'].' AND FossilMaxAge<'.$_GET['FossilMaxAge'].' ORDER BY NodeName';
	break;
	
case 'HigherTaxon':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '.$key[0].'=\''.$value[0].'\' ORDER BY NodeName';
	break;
case 'System':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '.$key[0].'=\''.$value[0].'\' ORDER BY NodeName';
	break;
case 'Period':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '.$key[0].'=\''.$value[0].'\' ORDER BY NodeName';
	break;
case 'Epoch':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '.$key[0].'=\''.$value[0].'\' ORDER BY NodeName';
	break;
case 'Age':
	$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID WHERE '.$key[0].'=\''.$value[0].'\' ORDER BY NodeName';
	break;



default:
$query='Select DISTINCT C.* FROM (SELECT CF.CalibrationID, V.* FROM View_Fossils V JOIN Link_CalibrationFossil CF ON CF.FossilID=V.FossilID) AS J JOIN View_Calibrations C ON J.CalibrationID=C.CalibrationID ORDER BY NodeName';
}

$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
echo '<p>Click on "Show calibration" to view full calibration information.  Click on node name or publication name to find related calibrations.</p>';


?>

<table width="100%" border="0">

  <tr>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>Info</strong></td>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>CalibrationID</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>Node Name</strong></td>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>Min Age</strong></td>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>Max Age</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>Publication</strong></td>
  </tr>

<?php
while ($row = mysql_fetch_array($calibration_list)) {
?>
  
  <tr align="center" valign="top">
    <td><a href="Show_Calibration.php?CalibrationID=<?=$row['CalibrationID']?>">Show calibration</a></td>
    <td><?=$row['CalibrationID']?></td>
    <td><?=$row['NodeName']?></td>
    <td><?=$row['MinAge']?></td>
    <td><?=$row['MaxAge']?></td>
    <td><?=$row['ShortName']?></td>
  </tr>

<?php } ?>  
  
</table>

	

<?php 
//open and print page footer template
require('Footer.txt');
?>
