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
$query='SELECT FossilID, Species, CollectionAcro, CollectionNumber, LocalityID, LocalityName, Country FROM View_Fossils';
$fossil_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of localities
$query='SELECT * FROM View_Localities ORDER BY StratumMinAge';
$locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of age types
$query='SELECT * FROM L_agetypes';
$agetypes_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of phylogenetic justification types
$query='SELECT * FROM L_PhyloTypes ORDER BY PhyloJustType';
$phyjusttype_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of countries
$query='SELECT name FROM L_countries ORDER BY name';
$country_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


?>

  <table width="100%" border="0">
<?php

/// the following code handles entry by species
echo $_POST['EntryType'];
if($_POST['EntryType']=="species") {


// process the taxon information into a useful array
$all_tips=array_slice(explode("/",$_POST['AllTips']),0,$NumTipPairs*2);
$all_ids=array();

$taxonnum=1;
foreach($all_tips as $key => $value) {
		if($_POST[str_replace(" ", "_",$value)."_".$taxonnum]=="New") {
			
//Check to make sure taxon isn't already in database
$query='SELECT * FROM taxa WHERE TaxonName =\''.$_POST[str_replace(" ", "_",$value).'_SpeciesName'].'\' AND CommonName =\''.$_POST[str_replace(" ", "_",$value).'_CommonName'].'\'';
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// following if statement enters the data then shows it to the user
if (mysql_num_rows($result)==0) {
			$query = 'INSERT INTO taxa (TaxonName, CommonName, TaxonAuthor) VALUES (\''.$_POST[str_replace(" ","_",$value).'_SpeciesName'].'\', \''.$_POST[str_replace(" ","_",$value).'_CommonName'].'\', \''.$_POST[str_replace(" ","_",$value).'_Author'].'\')'; 
			$enter_taxon=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			$all_ids[$taxonnum] = mysql_insert_id();
		} else { $row=mysql_fetch_assoc($result);
					$all_ids[$taxonnum] = $row['TaxonID'];
				} 
		} else { $all_ids[$taxonnum] = $_POST[str_replace(" ", "_",$value)."_".$taxonnum]; }
$taxonnum++;
}
?>
<?php

// SOMEWHERE HERE WE NOW HAVE TO ADD THE TIP PAIRS, checking whether the pair already exists, adding it if it doesn't, then inserting the proper!!!!!
$taxa_names=array();
foreach($all_ids as $key => $value) { 
//get the taxon names
$query='SELECT * FROM taxa WHERE TaxonID='.$value;
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$row=mysql_fetch_assoc($result);
array_push($taxa_names,$row['TaxonName']);
echo $row['TaxonName'];
}

for($i=1; $i<= $_POST['NumTipPairs'];$i++) {
	$taxonA=$taxa_names[$i-1];
	$taxonB=$taxa_names[($i+$_POST['NumTipPairs']-1)];
	$query= 'SELECT * FROM Link_Tips WHERE (TaxonA=\''.$taxonA.'\' AND TaxonB=\''.$taxonB.'\') OR (TaxonA=\''.$taxonB.'\' && TaxonB=\''.$taxonA.'\')';
	$pair_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	if( mysql_num_rows($pair_result)==0 ) {
		$query= 'INSERT INTO Link_Tips (TaxonA,TaxonB) VALUES (\''.$taxonA.'\', \''.$taxonB.'\')';
		$newpairs=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$pairID=mysql_insert_id();
		$query='INSERT INTO Link_CalibrationPair (CalibrationID,TipPairsID) VALUES (\''.$_POST['CalibrationID'].'\',\''.$pairID.'\')';
		$newcladepair=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		} else {
			$row=mysql_fetch_assoc($pair_result);
			$pairID=$row['PairID'];
			$query='INSERT INTO Link_CalibrationPair (CalibrationID,TipPairsID) VALUES (\''.$_POST['CalibrationID'].'\',\''.$pairID.'\')';
			$newcladepair=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			}
	
	}

} else {
// above code is for when entry is by species, below is entry by genera


// process the taxon information into a useful array
$all_tips=array_slice(explode("/",$_POST['AllTips']),0,$NumTipPairs*2);
$all_ids=array();

	$all_genera=array();
	foreach($all_tips as $key => $value) { 
		$tmp=explode(" ",$value);
		array_push($all_genera, $tmp[0]);
		}
	$all_tips=$all_genera;


for($i=1; $i<= $_POST['NumTipPairs'];$i++) {
	$genus1=$all_tips[$i-1];
	$genus2=$all_tips[($i+$_POST['NumTipPairs']-1)];
	$query = 'SELECT TaxonName from taxa WHERE TaxonName LIKE \''.$genus1.' %'.'\';';
	$genus1_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$query = 'SELECT TaxonName from taxa WHERE TaxonName LIKE \''.$genus2.' %'.'\';';
	$genus2_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	while($row1=mysql_fetch_assoc($genus1_result)) {
		$taxonA=$row1['TaxonName'];
		while($row2=mysql_fetch_assoc($genus2_result)) {
		$taxonB=$row2['TaxonName'];
	
	$query= 'SELECT * FROM Link_Tips WHERE (TaxonA=\''.$taxonA.'\' AND TaxonB=\''.$taxonB.'\') OR (TaxonA=\''.$taxonB.'\' && TaxonB=\''.$taxonA.'\')';
	$pair_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	if( mysql_num_rows($pair_result)==0 ) {		
		$query= 'INSERT INTO Link_Tips (TaxonA,TaxonB) VALUES (\''.$taxonA.'\', \''.$taxonB.'\')';
		$newpairs=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		$pairID=mysql_insert_id();
		$query='INSERT INTO Link_CalibrationPair (CalibrationID,TipPairsID) VALUES (\''.$_POST['CalibrationID'].'\',\''.$pairID.'\')';
		$newcladepair=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		} else {
			$row=mysql_fetch_assoc($pair_result);
			$pairID=$row['PairID'];
			$query='INSERT INTO Link_CalibrationPair (CalibrationID,TipPairsID) VALUES (\''.$_POST['CalibrationID'].'\',\''.$pairID.'\')';
			$newcladepair=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			}
		}
	}
	}

}

?>
<h1>creating node for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)<br /> 
step 5: enter fossil information</h1>
<p>
</p>
<table width="100%" border="0">

<form action="createclade6.php" method="post" name="form1">

<input type="hidden" name="PubID" value="<?=$_POST['PubID']?>">
<input type="hidden" name="CalibrationID" value="<?=$_POST['CalibrationID']?>">
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="NumNodes" value="<?=$_POST['NumNodes']?>">
<input type="hidden" name="NodeCount" value="<?=$_POST['NodeCount']?>">
<input type="hidden" name="NumFossils" value="<?=$_POST['NumFossils']?>">
<input type="hidden" name="FossilCount" value="<?=$_POST['FossilCount']?>">


                <tr>
                  <td align="right" valign="top" width="30%"><strong>species name</strong></td>
                  <td align="left" width="70%"><input type="text" name="SpeciesName" id="SpeciesName" ></td>
                </tr>
                
                <tr>
                  <td align="right" valign="top"><strong>collection acronym</strong></td>
                  <td><select name="CollectionAcro" id="CollectionAcro">
                	<?php
						if(mysql_num_rows($collectionacro_list)==0){
						?>
                    <option value="0">No acronyms in database, add one below.</option>
                	<?php
						} else {
							mysql_data_seek($collectionacro_list,0);
								echo "<option value=\"New\">Add new acronym or choose one from this list</option>";
						while($row=mysql_fetch_assoc($collectionacro_list)) {
							echo "<option value=\"".$row['Acronym']."\">".$row['Acronym'].", ".$row['CollectionName']."</option>";
							}

						}
					?>
                    </select>
                </tr>
               
                <tr>
                  <td align="right" valign="top" width="30%"></td>
                  <td align="left" width="70%">new acronym<input type="text" name="NewAcro" id="NewAcro" size="5" > new institution<input type="text" name="NewInst" id="NewInst" ></td>
                </tr>
                
                
                <tr>
                  <td align="right" valign="top" width="30%"><strong>collection number</strong></td>
                  <td align="left" width="70%"><input type="text" name="CollectionNum" id="CollectionNum" ></td>
                </tr>

                <tr>
                  <td align="right" valign="top"><strong>locality</strong></td>
                  <td><select name="Locality" id="Locality">
                	<?php
						if(mysql_num_rows($locality_list)==0){
							echo "<option value=\"New\">Add new formation on next page</option>";
					} else {
							mysql_data_seek($locality_list,0);
							while($row=mysql_fetch_assoc($locality_list)) {
							echo "<option value=\"".$row['LocalityID']."\">".$row['LocalityName'].", ".$row['Age']."</option>";
							}
							echo "<option value=\"New\">Add new locality on next page</option>";

						}
					?>
                    </select>
                </tr>
                
                <tr>
                  <td align="right" valign="top"><strong>fossil publication</strong></td>
                  <td><select name="FossilPub" id="FossilPub">
                	<?php
						if(mysql_num_rows($publication_list)==0){
							echo "<option value=\"New\">Add new publication below</option>";
					} else {
							mysql_data_seek($publication_list,0);
							echo "<option value=\"New\">Add new publication below or choose from list</option>";
						while($row=mysql_fetch_assoc($publication_list)) {
							echo "<option value=\"".$row['PublicationID']."\">".$row['ShortName']." (ID:".$row['PublicationID'].")</option>";
							}
						}
					?>
                    </select>
                    (<a href="Show_Publications.php" target="_new">Show complete citations</a>)</td>
                </tr>
  <tr><td></td>
    <td>short form <input type="text" name="FossShortForm" id="FossShortForm" size="10"> full ref <input type="text" name="FossFullCite" id="FossFullCite" > doi <input type="text" name="FossDOI" id="FossDOI" size="10"></td>
  </tr>

                <tr>
                  <td align="right" valign="top" width="30%"><strong>minimum age</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossilMinAge" id="FossilMinAge" size=3></td>
                </tr>

                <tr>
                  <td align="right" valign="top"><strong>minimum age type</strong></td>
                  <td><select name="MinAgeType" id="MinAgeType">
                	<?php
						if(mysql_num_rows($agetypes_list)==0){
						?>
                    <option value="0">No age types in database</option>
                	<?php
						} else {
							mysql_data_seek($agetypes_list,0);
							while($row=mysql_fetch_assoc($agetypes_list)) {
							echo "<option value=\"".$row['AgeTypeID']."\">".$row['AgeType']."</option>";
							}
						}
					?>
                    </select>
                </tr>
                
                
                <tr>
                  <td align="right" valign="top" width="30%"><strong>maximum age</strong></td>
                  <td align="left" width="70%"><input type="text" name="FossilMaxAge" id="FossilMaxAge" size=3></td>
                </tr>
                 <tr>
                  <td align="right" valign="top"><strong>maximum age type</strong></td>
                  <td><select name="MaxAgeType" id="MaxAgeType">
                	<?php
						if(mysql_num_rows($agetypes_list)==0){
						?>
                    <option value="0">No age types in database</option>
                	<?php
						} else {
							mysql_data_seek($agetypes_list,0);
							while($row=mysql_fetch_assoc($agetypes_list)) {
							echo "<option value=\"".$row['AgeTypeID']."\">".$row['AgeType']."</option>";
							}
						}
					?>
                    </select>
                </tr>
                
                 <tr>
                  <td align="right" valign="top"><strong>phylogenetic justification type</strong></td>
                  <td><select name="PhyJustType" id="PhyJustType">
                	<?php
						if(mysql_num_rows($phyjusttype_list)==0){
						?>
                    <option value="0">No justification types in database</option>
                	<?php
						} else {
							mysql_data_seek($phyjusttype_list,0);
							while($row=mysql_fetch_assoc($phyjusttype_list)) {
							echo "<option value=\"".$row['PhyloJustID']."\">".$row['PhyloJustType']."</option>";
							}
						}
					?>
                    </select>
                </tr>
                
                
                <tr>
                  <td align="right" valign="top" width="30%"><strong>phylogenetic justification</strong></td>
                  <td align="left" width="70%"><textarea name="PhyJustification" id="PhyJustification" cols="50" rows="5"></textarea></td>
                </tr>

                <tr>
                  <td align="right" valign="top"><strong>phylogeny publication</strong></td>
                  <td><select name="PhyPub" id="PhyPub">
                	<?php
						if(mysql_num_rows($publication_list)==0){
							echo "<option value=\"New\">Add new publication below</option>";
					} else {
							mysql_data_seek($publication_list,0);
							echo "<option value=\"New\">Add new publication below or choose from list</option>";
							while($row=mysql_fetch_assoc($publication_list)) {
							echo "<option value=\"".$row['PublicationID']."\">".$row['ShortName']." (ID:".$row['PublicationID'].")</option>";
							}
						}
					?>
                    </select>
                    (<a href="Show_Publications.php" target="_new">Show complete citations</a>)</td>
                </tr>
  <tr><td></td>
    <td>short form <input type="text" name="PhyloShortForm" id="PhyloShortForm" size="10"> full ref <input type="text" name="PhyloFullCite" id="PhyloFullCite" > doi <input type="text" name="PhyloDOI" id="PhyloDOI" size="10"></td>
  </tr>



<tr><td width="30%"></td><td width="70%"><input name="Submit" type="submit" value="+" /><b>Enter fossils</b></td></tr>
  </table>
</form>


<?php 
//open and print page footer template
require('footer.php');
?>
