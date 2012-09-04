<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('Header.txt');

// set useful variables
$NodeName=$_POST['NodeName'];
$CalibrationID=$_POST['CalibrationID'];
$NumTipPairs=$_POST['NumTipPairs'];
$NumFossils=$_POST['NumFossils'];
$NumNodes=$_POST['NumNodes'];
$NodeCount=$_POST['NodeCount'];
$publicationID=$_POST['PubID'];


// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');


?>
  <table width="100%" border="0">
  <form action="createclade8.php" method="post" name="form1">

  <h1>finished entering node <?=$_POST['NodeCount']?> for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)</h1>
	

	<?php
		if($_POST['SpeciesID']=="New") { 

		$query = 'INSERT INTO fossiltaxa (TaxonName, CommonName, TaxonAuthor, PBDBTaxonNum) VALUES (\''.$_POST['SpeciesName'].'\',\''.$_POST['CommonName'].'\',\''.$_POST['Author'].'\',\''.$_POST['PBDBTaxonNum'].'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$fossil_speciesID=mysql_insert_id();
		} else { $fossil_speciesID=$_POST['SpeciesID']; } 
		$query='SELECT * FROM fossiltaxa WHERE TaxonID='.$fossil_speciesID;
		$foss_sp_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$foss_sp_info=mysql_fetch_assoc($foss_sp_result);
		
		if($_POST['LocalityName']) { 
		$query = 'INSERT INTO localities (LocalityName, Stratum, MinAge, MaxAge, GeolTime, Country, LocalityNotes, PBDBCollectionNum) VALUES (\''.$_POST['LocalityName'].'\',\''.$_POST['Stratum'].'\',\''.$_POST['StratumMinAge'].'\',\''.$_POST['StratumMaxAge'].'\',\''.$_POST['GeolTime'].'\',\''.$_POST['Country'].'\',\''.$_POST['LocalityNotes'].'\',\''.$_POST['PBDBNum'].'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$localityID=mysql_insert_id();
		} else { $localityID=$_POST['Locality']; } 


		$query='INSERT INTO fossils (Species, CollectionAcro, CollectionNumber, LocalityID, FossilPub, MinAge, MinAgeType, MaxAge, MaxAgeType, PhyJustificationType, PhyJustification, PhyloPub) VALUES (\''.$foss_sp_info['TaxonName'].'\',\''.$_POST['CollectionAcro'].'\',\''.$_POST['CollectionNum'].'\',\''.$localityID.'\',\''.$_POST['FossilPub'].'\',\''.$_POST['FossilMinAge'].'\',\''.$_POST['MinAgeType'].'\',\''.$_POST['FossilMaxAge'].'\',\''.$_POST['MaxAgeType'].'\',\''.$_POST['PhyJustType'].'\',\''.$_POST['PhyJustification'].'\',\''.$_POST['PhyPub'].'\')'; 
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$fossilID=mysql_insert_id();
		
		$query='INSERT INTO Link_CalibrationFossil (CalibrationID, FossilID) VALUES (\''.$_POST['CalibrationID'].'\',\''.$fossilID.'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	?>

      </table>
</form>

<!-- Calibration view code here -->
<p>
<a href="Show_Calibration.php?CalibrationID=<?=$_POST['CalibrationID']?>">View this calibration</a>
</p>
<!-- end Calibration view code -->



<?php 
//open and print page footer template
require('Footer.txt');
?>
