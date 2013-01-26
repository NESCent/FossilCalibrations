<?php 
// open and load site variables
require('../Site.conf');

// open and print header template
require('../header.php');


// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// set useful variables
$NodeName=$_POST['NodeName'];
$CalibrationID=$_POST['CalibrationID'];
$NumTipPairs=$_POST['NumTipPairs'];
$NumFossils=isset($_POST['NumFossils']) ? $_POST['NumFossils'] : '?';
$NumNodes=isset($_POST['NumNodes']) ? $_POST['NumNodes'] : '?';
$NodeCount=isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?';
$publicationID=$_POST['PubID'];

//Retrieve publication info
$query='SELECT * FROM publications WHERE PublicationID >=\''.$publicationID.'\'';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pub_info=mysql_fetch_assoc($publication_list);


// process the tip taxa into two arrays

$tip_col1=array(); 
$tip_col2=array();
$genera_col1=array();
$genera_col2=array();
for ($i = 1; $i <= $NumTipPairs; $i++) { 
	array_push($tip_col1, $_POST['Pair'.$i.'TaxonA']);
	array_push($tip_col2,$_POST['Pair'.$i.'TaxonB']); 
	$pieces=explode(" ", $_POST['Pair'.$i.'TaxonA']);
	array_push($genera_col1,$pieces[0]);
	$pieces=explode(" ",$_POST['Pair'.$i.'TaxonB']);
	array_push($genera_col2,$pieces[0]);	
}


$all_tips=array_merge($tip_col1,$tip_col2);

if($_POST['EntryType']=="species") {
/// the following code handles entry by species

?> 

<h1>creating Node <?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?> for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)<br /> 
step 3: verifying taxa for entry by species</h1>
<p>
Confirm ambiguous taxa or enter taxa if they are not already in the database.
</p>

<form action="createclade5.php" method="post" name="form1">
  <table width="100%" border="0">

<input type="hidden" name="PubID" value="<?=$_POST['PubID']?>">
<input type="hidden" name="CalibrationID" value="<?=$_POST['CalibrationID']?>">
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="NumNodes" value="<?=$_POST['NumNodes']?>">
<input type="hidden" name="NodeCount" value="<?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?>">
<input type="hidden" name="NumTipPairs" value="<?=$_POST['NumTipPairs']?>">
<input type="hidden" name="EntryType" value="<?=$_POST['EntryType']?>">
<input type="hidden" name="AllTips" value="<?php
foreach($all_tips as $key => $value) { echo $value."/"; }
?>">

<?php
	$taxon_index=1;
	foreach($all_tips as &$value) {
?>

<?php
	$query = "SELECT * FROM `taxa` WHERE TaxonName='".$value."'";
	$exact_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	if(mysql_num_rows($exact_matches)>0) { 
		$row=mysql_fetch_assoc($exact_matches); 
		echo "<tr><td width=\"30%\" align=\"right\" valign=\"top\"><b><i>$value</i></b></td><td width=\"70%\" align=\"left\" valign=\"top\"> found in database</td></tr><input type=\"hidden\" name=\"".str_replace(" ","_",$value).'_'.$taxon_index."\" value=\"".$row['TaxonID']."\">"; 
		} else {
?>

			<tr><td width="30%" align="right" valign="top"><b><i><?=$value?></i></b></td><td width="70%" align="left" valign="top">
            	<select name="<?=str_replace(" ","_",$value).'_'.$taxon_index;?>" id="<?=str_replace(" ","_",$value).'_'.$taxon_index;?>">
                <option value="New">no exact match. choose from list or enter new taxon</option>

<?php
	$query = "SELECT *,MATCH(TaxonName, CommonName) AGAINST ('".$value."') AS score FROM `taxa` WHERE MATCH(TaxonName, CommonName) AGAINST ('".$value."' IN NATURAL LANGUAGE MODE) ORDER BY score DESC";
	$close_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	while($row=mysql_fetch_assoc($close_matches)) {
		?>
				<option value="<?=$row['TaxonID']?>"><i><?=$row['TaxonName']?></i> <?=$row['TaxonAuthor']?> (<?=$row['CommonName']?>)</option>
		<?php
	}

	?>
    		</select><BR />
            <input name="<?=str_replace(" ","_",$value)?>_SpeciesName" type="text" /> Species Name<br />
            <input name="<?=str_replace(" ","_",$value)?>_CommonName" type="text" /> Common Name<br /> 
            <input name="<?=str_replace(" ","_",$value)?>_Author" type="text" /> Author
            </td></tr>
<?php
	}
	$taxon_index++;

}
?>



    <tr>
      <td align="right" valign="top">&nbsp;</td>
      <td><input type="submit" name="Submit" id="Submit" value="+" />
      <b>confirm taxa</b></td>
    </tr>

<?php
} else {
// above code is for when entry is by species, below is entry by genera
?>


<h1>creating Node <?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?> for <?=$pub_info['ShortName']?>: <?=$NodeName?> (ID: <?=$CalibrationID?>)<br /> 
step 3: verifying taxa for entry by genus</h1>
<p>
Confirm ambiguous taxa or enter taxa if they are not already in the database.
</p>

<form action="createclade5.php" method="post" name="form1">
  <table width="100%" border="0">

<input type="hidden" name="PubID" value="<?=$_POST['PubID']?>">
<input type="hidden" name="CalibrationID" value="<?=$_POST['CalibrationID']?>">
<input type="hidden" name="NodeName" value="<?=$_POST['NodeName']?>">
<input type="hidden" name="NumNodes" value="<?=$_POST['NumNodes']?>">
<input type="hidden" name="NodeCount" value="<?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?>">
<input type="hidden" name="NumTipPairs" value="<?=$_POST['NumTipPairs']?>">
<input type="hidden" name="EntryType" value="<?=$_POST['EntryType']?>">
<input type="hidden" name="AllTips" value="<?php
foreach($all_tips as $key => $value) { echo $value."/"; }
?>">



<?php
	$all_genera=array();
	foreach($all_tips as $key => $value) { 
		$tmp=explode(" ",$value);
		array_push($all_genera, $tmp[0]); 
		}
	$all_tips=$all_genera;
	$taxon_index=1;
	foreach($all_tips as &$value) {
		
?>

<?php
	$query = "SELECT * FROM `taxa` WHERE TaxonName LIKE '".$value." %"."'";
	$exact_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	if(mysql_num_rows($exact_matches)>0) { 
		$row=mysql_fetch_assoc($exact_matches); 
		echo "<tr><td width=\"30%\" align=\"right\" valign=\"top\"><b><i>$value</i></b></td><td width=\"70%\" align=\"left\" valign=\"top\"> found in database</td></tr><input type=\"hidden\" name=\"".str_replace(" ","_",$value).'_'.$taxon_index."\" value=\"".$row['TaxonID']."\">"; 
		} else {
?>

			<tr><td width="30%" align="right" valign="top"><b><i><?=$value?></i></b></td><td width="70%" align="left" valign="top">
            	<select name="<?=str_replace(" ","_",$value).'_'.$taxon_index;?>" id="<?=str_replace(" ","_",$value).'_'.$taxon_index;?>">
                <option value="New">no exact match. choose a species of the correct genus from list or backup to enter new spelling.</option>

<?php
	$query = "SELECT *,MATCH(TaxonName, CommonName) AGAINST ('".$value."') AS score FROM `taxa` WHERE MATCH(TaxonName, CommonName) AGAINST ('".$value."' IN NATURAL LANGUAGE MODE) ORDER BY score DESC";
	$close_matches=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	while($row=mysql_fetch_assoc($close_matches)) {
		?>
				<option value="<?=$row['TaxonID']?>"><i><?=$row['TaxonName']?></i> <?=$row['TaxonAuthor']?> (<?=$row['CommonName']?>)</option>
		<?php
	}

	?>
    		</select>
            </td></tr>
<?php
	}
	$taxon_index++;

}
?>



    <tr>
      <td align="right" valign="top">&nbsp;</td>
      <td><input type="submit" name="Submit" id="Submit" value="+" />
      <b>confirm taxa</b></td>
    </tr>



<?php
}
?>
  </table>
</form>


<?php 
//open and print page footer template
require('../footer.php');
?>
