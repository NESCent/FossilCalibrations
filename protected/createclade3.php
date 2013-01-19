<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>

<?php
// Set some useful variables
$now=date("Y-m-d H:i:s");
$publicationID=$_POST['PubID'];

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

//Retrieve publication info
$query='SELECT * FROM publications WHERE PublicationID >=\''.$publicationID.'\'';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pub_info=mysql_fetch_assoc($publication_list);

//Check to make sure calibration isn't already in database
$query='SELECT * FROM calibrations WHERE NodeName =\''.$_POST['NodeName'].'\' AND NodePub =\''.$publicationID.'\'';
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
// following if statement enters the data then shows it to the user
if (mysql_num_rows($result)==0) {

// the following several queries enter the information from Form 1 into the database

//Enter initial calibration/clade information
$query='INSERT INTO calibrations (NodeName, HigherTaxon, MinAge, MinAgeExplanation, MaxAge, MaxAgeExplanation, NodePub) VALUES (\''.$_POST['NodeName'].'\',\''.$_POST['HigherTaxon'].'\',\''.$_POST['MinAge'].'\',\''.$_POST['MinAgeJust'].'\',\''.$_POST['MaxAge'].'\',\''.$_POST['MaxAgeJust'].'\',\''.$_POST['PubID'].'\')';
$enter_calibration=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Query the database for the clade that was just entered, which should be the only one newer than "$now", which is when the script started
$query='SELECT * FROM calibrations WHERE DateCreated >= \''.$now.'\'';
$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

$row=mysql_fetch_assoc($calibration_list);
$NodeName=$row['NodeName'];
$CalibrationID=$row['CalibrationID'];
$NumTipPairs=$_POST['NumTipPairs'];

?>
<h1>The following node information was entered in the database (justifications not shown)</h1>

<table width="100%" border="0">
   <tr>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>ID</strong></td>
    <td width="20%" align="center" valign="middle" bgcolor="#999999"><strong>Node Name</strong></td>
    <td width="20%" align="center" valign="middle" bgcolor="#999999"><strong>Higher Taxon</strong></td>
    <td width="20%" align="center" valign="middle" bgcolor="#999999"><strong>Minimum Age</strong></td>
    <td width="20%" align="center" valign="middle" bgcolor="#999999"><strong>Maximum Age</strong></td>
  </tr>

  
  <tr align="center" valign="top">
    <td><?=$row['CalibrationID']?></td>
    <td><?=$row['NodeName']?></td>
    <td><?=$row['HigherTaxon']?></td>
    <td><?=$row['MinAge']?></td>
    <td><?=$row['MaxAge']?></td>
  </tr>

  
</table>
<P></P>

<?php
// the following bracket ends the if statement related to testing whether the calibration is already in the database
} else {
	$row=mysql_fetch_assoc($result);
	$NodeName=$row['NodeName'];
	$CalibrationID=$row['CalibrationID'];
	$NumTipPairs=$_POST['NumTipPairs'];
}



?>


<h1>creating Node <?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?> for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)<br /> 
add tip taxa pairs</h1>
<p>
Enter pairs of extant taxa whose last common ancestor was the node being calibrated. You may enter tip taxa as pairs of species or as blocks of genera.  If you choose blocks of genera, the individual pairs of species from those genera will be automatically entered in the database.  
</p>
<form action="createclade4.php" method="post" name="form1">
  <table width="100%" border="0">
  
<input type="hidden" name="PubID" value="<?=$_POST['PubID']?>">
<input type="hidden" name="CalibrationID" value="<?=$CalibrationID?>">
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="NumNodes" value="<?=$_POST['NumNodes']?>">
<input type="hidden" name="NodeCount" value="<?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?>">
<input type="hidden" name="NumTipPairs" value="<?=$_POST['NumTipPairs']?>">

    <tr>
      <td align="right" valign="top">Specify taxa by species </td>
      <td><input type="radio" name="EntryType" value="species" id="EntryType_0" checked="checked" /> or genus? <input type="radio" name="EntryType" value="genus" id="EntryType_1" /></td>
    </tr>


<?php
for ($i = 1; $i <= $NumTipPairs; $i++) {
?>
    <tr>
      <td width="30%" align="right" valign="top"><strong>Tip pair <?=$i?>: taxon A</strong></td>
      <td width="70%"><input type="text" name="Pair<?=$i?>TaxonA" id="Pair<?=$i?>TaxonA"></td>
    </tr>
    <tr>
      <td align="right" valign="top"><strong>taxon B</strong></td>
      <td><input type="text" name="Pair<?=$i?>TaxonB" id="Pair<?=$i?>TaxonB"></td>
    </tr>
    
<?php
}
?>

    <tr>
      <td align="right" valign="top">&nbsp;</td>
      <td><input type="submit" name="Submit" id="Submit" value="+" />
      enter tip pairs</td>
    </tr>


  </table>
</form>
<p>&nbsp;</p>


<?php 
//open and print page footer template
require('footer.php');
?>
