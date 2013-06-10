<?php 
// open and load site variables
require('Site.conf');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$key=array_keys($_GET);
$value=array_values($_GET);

// Get details about calibration
$query = 'SELECT * FROM View_Calibrations WHERE '.$key[0].'=\''.$value[0].'\'';
$calibration_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$calibration_info=mysql_fetch_assoc($calibration_results);

// Get details about fossils associated with this calibration
$query = 'SELECT * FROM Link_CalibrationFossil L, View_Fossils F, fossiltaxa t WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.FossilID=F.FossilID AND L.Species=t.TaxonName';
$fossil_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// find minimum age of fossils associated with this calibration
$query = 'SELECT max(L.MinAge) AS Min FROM Link_CalibrationFossil L, View_Fossils F WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.FossilID=F.FossilID';
$fossil_minage_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$FossMinAge=mysql_fetch_assoc($fossil_minage_results);

/*
// Get details about tip pairs associated with this calibration
$query = 'SELECT * FROM Link_CalibrationPair L, View_TipPairs t WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.TipPairsID=t.PairID ORDER BY TaxonA, TaxonB';
$tippair_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
*/


// Get details about the calibrated-node definition for this calibration

// retrieve node-definition hints for side A
$query="SELECT * 
	FROM node_definitions
	WHERE calibration_id = '". $calibration_info['CalibrationID'] ."' AND definition_side = 'A'
	ORDER BY display_order";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$side_A_hint_data = array();
while($row=mysql_fetch_assoc($result)) {
	$side_A_hint_data[] = $row;
}
mysql_free_result($result);

// retrieve node-definition hints for side B
$query="SELECT * 
	FROM node_definitions
	WHERE calibration_id = '". $calibration_info['CalibrationID'] ."' AND definition_side = 'B'
	ORDER BY display_order";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$side_B_hint_data = array();
while($row=mysql_fetch_assoc($result)) {
	$side_B_hint_data[] = $row;
}
mysql_free_result($result);


// Fetch any image associated with this calibration (its publication)
if ($calibration_info['PublicationID']) {
	$query = 'SELECT * FROM publication_images WHERE PublicationID='.$calibration_info['PublicationID'];
	$image_info_results = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$image_info = mysql_fetch_assoc($image_info_results);
}

$PageTitle = 'View fossil calibration for '.$calibration_info['NodeName'];

// open and print header template
require('header.php');
?>

<p>
<? if (userIsAdmin()) { ?>
   <input type="button" style="float: right;" onclick="window.location ='/protected/edit_calibration.php?id=<?= $calibration_info['CalibrationID'] ?>'; return false;" value="Edit calibration" />
<? } ?>
   <h1><?=$calibration_info['NodeName']?><!-- (ID: <?=$calibration_info['CalibrationID']?>) --></h1>
</p>

<p class="featured-information" style="overflow: hidden;">

<? // if there's an image mapped to this publication, show it
   if (isset($image_info) && $image_info['image']) { ?>
<span class="optional-thumbnail" style="height: 120px; float: right;">
	<img src="/publication_image.php?id=<?= $calibration_info['PublicationID'] ?>" style="height: 120px;"
	alt="<?= $image_info['image_caption'] ?>" title="<?= $image_info['image_caption'] ?>"
	/>
</span>
<? } ?>

<i>calibration from:</i><br />
<?=$calibration_info['FullReference']?>
<?php if($calibration_info['DOI']!="NULL") { echo '<br><font class="small_text">[<a href="http://dx.doi.org/'.$calibration_info['DOI'].'" target="_blank">View electronic resource]</font></a>'; } ?></p>

<table width="100%">

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">node name</i><br><b><?=$calibration_info['NodeName']?></b> 
&nbsp; &nbsp; <font class="small_blue">Look for this name in 
	<a href="http://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?name=<?=$calibration_info['NodeName']?>" target="_blank">NCBI</a> 
	&nbsp;
	<a href="http://en.wikipedia.org/wiki/<?=$calibration_info['NodeName']?>" target="_blank">Wikipedia</a> 
	&nbsp;
	<a href="http://animaldiversity.ummz.umich.edu/site/accounts/information/<?=$calibration_info['NodeName']?>.html" target="_blank">Animal Diversity Web</a></font>
</td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">recommended citations</i><br><b><?=$calibration_info['ShortName']?></b>
</td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">node minimum age </i><br><b><?=$calibration_info['MinAge']?> Ma</b> 
      <?php if ($calibration_info['MinAgeExplanation']) { ?>
	<font style="font-size:10px"><br/><?=$calibration_info['MinAgeExplanation']?></font>
      <?php } ?>
</td><td width="10%">&nbsp;</td></tr>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">node maximum age </i><br><b><?=$calibration_info['MaxAge']?> Ma</b>
      <?php if ($calibration_info['MaxAgeExplanation']) { ?>
	<font style="font-size:10px"><br/><?=$calibration_info['MaxAgeExplanation']?></font>
      <?php } ?>
</td><td width="10%">&nbsp;</td></tr>


<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">fossils used to date this node</i></td><td width="10%">&nbsp;</td></tr>
<?php
$rowNumber = 0;
while ($row = mysql_fetch_array($fossil_results)) {
	$rowNumber++;
	?>
<tr><td width="10%">&nbsp;</td><td><blockquote class="<?= ($rowNumber % 2)  ? 'odd' : 'even' ?>" style="font-size:10px;">

<?php if($row['PBDBTaxonNum']>0) {?>
	<a href="http://pbdb.org/cgi-bin/bridge.pl?a=checkTaxonInfo&taxon_no=<?=$row['PBDBTaxonNum']?>&is_real_user=1" target="_new">
		<b><?=$row['CollectionAcro']?> <?=$row['CollectionNumber']?></b>
	</a>
<?php } else { ?>
	<b><?=$row['CollectionAcro']?> <?=$row['CollectionNumber']?></b>
<?php } ?>

<br />
	<b><i><?=$row['Species']?></i>, <?=$row['TaxonAuthor']?></b>
<br />
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Locality:</i> <b>
				<?=$row['LocalityName']?><?php if ($row['LocalityName'] && $row['Country']) { ?>, <?php } ?> 
				<?=$row['Country']?>
			     </b> <br />
                           <?php if($row['Stratum']>0) { ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Stratum:</i> <b><?=$row['Stratum']?></b><br />
			   <?php } ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Geological age:</i> <b><?=$row['Age']?>, <?=$row['Epoch']?>, <?=$row['Period']?>, <?=$row['System']?></b><br />
                             <!-- &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Minimum age:</i> <b><?=$row['MinAge']?> Ma</b> <i>Maximum age:</i> <b><?=$row['MaxAge']?> Ma</b><br /> -->
                           <?php if($row['PBDBCollectionNum']>0) { ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[<a href="http://pbdb.org/cgi-bin/bridge.pl?action=basicCollectionSearch&collection_no=<?=$row['PBDBCollectionNum']?>" target="_new">View locality in Paleobiology Database</a>]</font>
			   <?php } ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[all nodes with this fossil]</font></blockquote></td><td width="10%">&nbsp;</td></tr>


	<?php
	}
?>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><p></p></td><td width="10%">&nbsp;</td></tr>
	
<tr><td width="10%"></td><td align="left" valign="top"><i class="small_orange">calibrated-node definition (used to place this calibration in the NBCI taxonomy)</i></td><td width="10%"></td></tr>
<tr><td width="10%"></td><td>
<blockquote style="overflow: hidden;">
	<table width="30%" style="float: left;">
	<tr align="left"><td colspan="2"><b class="small_text">Taxon A</b></td></tr>
	<?php // list any A-side taxa found (if none, just prompt with +/- buttons)
	if ($side_A_hint_data) {
		foreach ($side_A_hint_data as $hint)
		{ ?>
		<tr class="definition-hint">
		  <td align="right" valign="top">
		    <?= $hint['operator']?>
		  </td>
		  <td>
		    <?= $hint['matching_name'] ?>
		  </td>
		</tr>
	     <? } 
	}?>
	</table>
	<table width="30%" style="float: left;">
	<tr align="left"><td colspan="2"><b class="small_text">Taxon B</b></td></tr>
	<?php // list any A-side taxa found (if none, just prompt with +/- buttons)
	if ($side_B_hint_data) {
		foreach ($side_B_hint_data as $hint)
		{ ?>
		<tr class="definition-hint">
		  <td align="right" valign="top">
		    <?= $hint['operator']?>
		  </td>
		  <td>
		    <?= $hint['matching_name'] ?>
		  </td>
		</tr>
	     <? } 
	}?>
	</table>
</blockquote>
</td><td width="10%"></td></tr>
  
<tr><td width="10%"></td><td align="left" valign="top"><p></p></td><td width="10%"></td></tr>
</table>

<?php 
//open and print page footer template
require('footer.php');
?>
