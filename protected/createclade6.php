<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');


// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// set useful variables
$NodeName=$_POST['NodeName'];
$CalibrationID=$_POST['CalibrationID'];
$NumTipPairs=$_POST['NumTipPairs'];
$NumFossils=$_POST['NumFossils'];
$NumNodes=$_POST['NumNodes'];
$NodeCount=$_POST['NodeCount'];
$publicationID=$_POST['PubID'];


//Retrieve publication info
$query='SELECT * FROM publications WHERE PublicationID >=\''.$publicationID.'\'';
$publication=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pub_info=mysql_fetch_assoc($publication);

//Retrieve publication list
$query='SELECT * FROM publications';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pub_info=mysql_fetch_assoc($publication_list);

//Retrieve colleciton acronyms
$query='SELECT * FROM L_CollectionAcro ORDER BY Acronym';
$collectionacro_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of fossils
$query='SELECT FossilID, Species, CollectionAcro, CollectionNumber, LocalityID, Country FROM View_Fossils';
$fossil_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of formations
$query='SELECT * FROM View_Localities ORDER BY StratumMinAge';
$locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of age types
$query='SELECT * FROM L_agetypes';
$agetypes_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of phylogenetic justification types
$query='SELECT * FROM L_PhyloTypes ORDER BY PhyloJustType';
$phyjusttype_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of geological times
$query='SELECT GeolTimeID, Age, Period, t.ShortName, StartAge FROM geoltime g, L_timescales t WHERE g.Timescale=t.TimescaleID ORDER BY StartAge';
$geoltime_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of countries
$query='SELECT name FROM L_countries ORDER BY name';
$country_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());




?>
  <h1>creating node for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)<br /> 
	completing fossil entry</h1>
  <form action="createclade7.php" method="post" name="form1">
<input type="hidden" name="PubID" value="<?=$_POST['PubID']?>">
<input type="hidden" name="CalibrationID" value="<?=$_POST['CalibrationID']?>">
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="SpeciesLookupName" value="<?=str_replace(" ", "_",$_POST['SpeciesName'])?>">

  <table width="100%" border="0">
	<tr><td width="30%" align="right" valign="top"><b><i><?=$_POST['SpeciesName']?></i></b></td>
    <td width="70%" align="left" valign="top"><select name="SpeciesID" id="SpeciesID">
    
		<?php
			$query = "SELECT *,MATCH(TaxonName, CommonName) AGAINST ('".$_POST['SpeciesName']."') AS score FROM `fossiltaxa` WHERE MATCH(TaxonName, CommonName) AGAINST ('".$_POST['SpeciesName']."' IN NATURAL LANGUAGE MODE) ORDER BY score DESC";
			$close_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			if(mysql_num_rows($close_matches)==0) { echo "<option value=\"New\" id=\"New\">no exact match. choose a species from list or enter new taxon below.</option>"; } 
			else {
			while($row=mysql_fetch_assoc($close_matches)) {
		?>
            <option value="<?=$row['TaxonID']?>" id="<?=$row['TaxonID']?>" /><i><?=$row['TaxonName']?></i> <?=$row['TaxonAuthor']?> (<?=$row['CommonName']?>)</option>
			<?php
				}
			}
			?>
			</select>
		    </td></tr>
			<td width="30%" align="right" valign="top">Species name</td><td width="70%" align="left" valign="top"><input name="SpeciesName" type="text" /></td></tr>
			<td width="30%" align="right" valign="top">Common name</td><td width="70%" align="left" valign="top"><input name="CommonName" type="text" /></td></tr>
			<td width="30%" align="right" valign="top">Author and date</td><td width="70%" align="left" valign="top"><input name="Author" type="text" /></td></tr>
			<td width="30%" align="right" valign="top">PaleoDB taxon number</td><td width="70%" align="left" valign="top"><input name="PBDBTaxonNum" type="text" /></td></tr>

	<?php
	if($_POST['CollectionAcro']=="New") { 
		$query = 'INSERT INTO L_CollectionAcro (Acronym, CollectionName) VALUES (\''.$_POST['NewAcro'].'\',\''.$_POST['NewInst'].'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		echo '<input type="hidden" name="CollectionAcro" value="'.$_POST['NewAcro'].'">';
	} else { echo '<input type="hidden" name="CollectionAcro" value="'.$_POST['CollectionAcro'].'">';} 
	?>
    <input type="hidden" name="CollectionNum" value="<?=$_POST['CollectionNum']?>">
	<input type="hidden" name="FossilMinAge" value="<?=$_POST['FossilMinAge']?>">
	<input type="hidden" name="MinAgeType" value="<?=$_POST['MinAgeType']?>">
	<input type="hidden" name="FossilMaxAge" value="<?=$_POST['FossilMaxAge']?>">
	<input type="hidden" name="MaxAgeType" value="<?=$_POST['MaxAgeType']?>">
	<input type="hidden" name="PhyJustType" value="<?=$_POST['PhyJustType']?>">
	<input type="hidden" name="PhyJustification" value="<?=$_POST['PhyJustification']?>">

	<?php
	if($_POST['FossilPub']=="New") { 
		$query = 'INSERT INTO publications (ShortName, FullReference, DOI) VALUES (\''.$_POST['FossShortForm'].'\',\''.$_POST['FossFullCite'].'\',\''.$_POST['FossDOI'].'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		echo '<input type="hidden" name="FossilPub" value="'.mysql_insert_id().'">';
	} else { echo '<input type="hidden" name="FossilPub" value="'.$_POST['FossilPub'].'">';} 
	if($_POST['PhyPub']=="New") { 
		$query = 'INSERT INTO publications (ShortName, FullReference, DOI) VALUES (\''.$_POST['PhyloShortForm'].'\',\''.$_POST['PhyloFullCite'].'\',\''.$_POST['PhyloDOI'].'\')';
		$enter=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		echo '<input type="hidden" name="PhyPub" value="'.mysql_insert_id().'">';
	} else { echo '<input type="hidden" name="PhyPub" value="'.$_POST['PhyPub'].'">';} 


	if($_POST['Locality']=="New") { 

	?>

  <tr>
    <td width="30%" align="right" valign="top"><h1>enter locality information<h1></td>
    <td width="70%" ></td>
  </tr>

  <tr>
    <td width="30%" align="right" valign="top"><b>locality name</b></td>
    <td width="70%" ><input type="text" name="LocalityName" id="LocalityName"></td>
  </tr>
  <tr>
    <td width="30%" align="right" valign="top"><b>stratum name</b></td>
    <td width="70%" ><input type="text" name="Stratum" id="Stratum"></td>
  </tr>
                  <tr>
                  <td align="right" valign="top" width="30%"><strong>PBDB collection num</strong></td>
                  <td align="left" width="70%"><input type="text" name="PBDBNum" id="PBDBNum" ></td>
                </tr>
                <tr>
                  <td align="right" valign="top" width="30%"><strong>locality notes</strong></td>
                  <td align="left" width="70%"><textarea name="LocalityNotes" id="LocalityNotes" cols="50" rows="5"></textarea></td>
                </tr>

                <tr>
                  <td align="right" valign="top"><strong>country</strong></td>
                  <td><select name="Country" id="Country">
                	<?php
						if(mysql_num_rows($country_list)==0){
							echo "no countries available";
					} else {
							mysql_data_seek($country_list,0);
						while($row=mysql_fetch_assoc($country_list)) {
							echo "<option value=\"".$row['name']."\">".$row['name']."</option>";
							}
						}
					?>
                    </select>
                </tr>


  <tr>
    <td width="30%" align="right" valign="top"><b>top age of stratum</b></td>
    <td width="70%" ><input type="text" name="StratumMinAge" id="StratumMinAge"></td>
  </tr>
  <tr>
    <td width="30%" align="right" valign="top"><b>bottom age of stratum</b></td>
    <td width="70%" ><input type="text" name="StratumMaxAge" id="StratumMaxAge"></td>
  </tr>
                <tr>
                  <td align="right" valign="top"><strong>geological age</strong></td>
                  <td><select name="GeolTime" id="GeolTime">
                	<?php
						if(mysql_num_rows($geoltime_list)==0){
						?>
                    <option value="0">No geolotical time in database</option>
                	<?php
						} else {
							mysql_data_seek($geoltime_list,0);
						while($row=mysql_fetch_assoc($geoltime_list)) {
							echo "<option value=\"".$row['GeolTimeID']."\">".$row['Age'].", ".$row['Period'].", ".$row['ShortName']."</option>";
							}

						}
					?>
                    </select>
                </tr>

<?php
	} else { 	echo '<input type="hidden" name="Locality" value="'.$_POST['Locality'].'">';
 } 
	?>
    
    <tr><td width="30%"></td><td width="70%"><input name="Submit" type="submit" value="+" /><b>Finish node entry </b></td></tr>
  </table>
</form>


<?php 
//open and print page footer template
require('footer.php');
?>
