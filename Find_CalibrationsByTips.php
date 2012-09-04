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

//retrieve calibrations when Taxon B is entered
if($_GET['TaxonB']) {
$query='SELECT DISTINCT V.TaxonA, V.ACommonName, V.TaxonB,V.BCommonName, C.* FROM View_CalibrationPair V JOIN View_Calibrations C ON V.CalibrationID=C.CalibrationID 
WHERE (V.TaxonA LIKE "%'.$_GET['TaxonA'].'%" AND V.TaxonB LIKE "%'.$_GET['TaxonB'].'%") 
OR (V.TaxonB LIKE "%'.$_GET['TaxonA'].'%" AND V.TaxonA LIKE "%'.$_GET['TaxonB'].'%")
OR (V.ACommonName LIKE "%'.$_GET['TaxonA'].'%" AND V.BCommonName LIKE "%'.$_GET['TaxonB'].'%")
OR (V.BCommonName LIKE "%'.$_GET['TaxonA'].'%" AND V.ACommonName LIKE "%'.$_GET['TaxonB'].'%")';
$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
echo '<h1>calibrations with tip taxa "'.$_GET['TaxonA'].'" and "'.$_GET['TaxonB'].'"</h1>';
echo '<p>Click on "Show calibration" to view full calibration information.  Click on node name or publication name to find related calibrations.</p>';
} else {
$query='SELECT Distinct C.* FROM View_CalibrationPair V JOIN View_Calibrations C ON V.CalibrationID=C.CalibrationID  
WHERE V.TaxonA LIKE "%'.$_GET['TaxonA'].'%" 
OR V.TaxonB LIKE "%'.$_GET['TaxonA'].'%" 
OR V.ACommonName LIKE "%'.$_GET['TaxonA'].'%" 
OR V.BCommonName LIKE "%'.$_GET['TaxonA'].'%"';
$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
echo '<h1>calibrations with tip taxon "'.$_GET['TaxonA'].'" </h1>';
echo '<p>Click on "Show calibration" to view full calibration information.  Click on node name or publication name to find related calibrations.</p>';
}
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
