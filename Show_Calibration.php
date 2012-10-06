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
$query = 'SELECT * FROM Link_CalibrationFossil L, View_Fossils F, fossiltaxa t WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.FossilID=F.FossilID AND F.Species=t.TaxonName';
$fossil_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// find minimum age of fossils associated with this calibration
$query = 'SELECT max(FossilMinAge) AS Min FROM Link_CalibrationFossil L, View_Fossils F WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.FossilID=F.FossilID';
$fossil_minage_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$FossMinAge=mysql_fetch_assoc($fossil_minage_results);

// Get details about tip pairs associated with this calibration
$query = 'SELECT * FROM Link_CalibrationPair L, View_TipPairs t WHERE L.CalibrationID='.$calibration_info['CalibrationID'].' AND L.TipPairsID=t.PairID ORDER BY TaxonA, TaxonB';
$tippair_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


$PageTitle = 'View fossil calibration for '.$calibration_info['NodeName'];

// open and print header template
require('Header.txt');
?>

<p><h1>View calibration:  <?=$calibration_info['NodeName']?> (ID: <?=$calibration_info['CalibrationID']?>)</h1></p>

<p class="featured-information"><i>calibration from:</i><br />
<?=$calibration_info['FullReference']?>
<?php if($calibration_info['DOI']!="NULL") { echo '<br><font class="small_text">[<a href="http://dx.doi.org/'.$calibration_info['DOI'].'" target="_blank">View electronic resource]</font></a>'; } ?></p>

<p><h2><?=$calibration_info['NodeName']?></h2></p>

<table width="100%">

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">node name</i><br><b><?=$calibration_info['NodeName']?></b> 
<font class="small_blue"><?=$calibration_info['NodeName']?> in <a href="http://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?name=<?=$calibration_info['NodeName']?>" target="_blank">NCBI</a> <a href="http://en.wikipedia.org/wiki/<?=$calibration_info['NodeName']?>" target="_blank">Wikipedia</a> <a href="http://animaldiversity.ummz.umich.edu/site/accounts/information/<?=$calibration_info['NodeName']?>.html" target="_blank">Animal Diversity Web</a></font></td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">publication</i><br><b><?=$calibration_info['ShortName']?></b>
<font class="small_blue">all nodes from <?=$calibration_info['ShortName']?></font></td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">node min age </i><br><b><?=$FossMinAge['Min']?> mya</b> <font style="font-size:10px">(min age of oldest fossil)</font></td><td width="10%">&nbsp;</td></tr>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">node max age </i><br><b><?=$calibration_info['MaxAge']?> mya</b><font style="font-size:10px"> (<?=$calibration_info['MaxAgeExplanation']?>)</font></td><td width="10%">&nbsp;</td></tr>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><p></p></td><td width="10%">&nbsp;</td></tr>


<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">fossils used to date this node</i></td><td width="10%">&nbsp;</td></tr>
<?php
$rowNumber = 0;
while ($row = mysql_fetch_array($fossil_results)) {
	$rowNumber++;
	?>
<tr><td width="10%">&nbsp;</td><td><blockquote class="<?= ($rowNumber % 2)  ? 'odd' : 'even' ?>" style="font-size:10px;">
							 <b><?=$row['CollectionAcro']?> <?=$row['CollectionNumber']?></b><br />
						 	 <?php if($row['PBDBTaxonNum']>0) {?><a href="http://pbdb.org/cgi-bin/bridge.pl?a=checkTaxonInfo&taxon_no=<?=$row['PBDBTaxonNum']?>&is_real_user=1" target="_new"><i><b><?=$row['Species']?></i></a>, <?=$row['TaxonAuthor']?></b><?php } else { ?><i><b><?=$row['Species']?>, <?=$row['TaxonAuthor']?></b></i><?php } ?><br />
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Locality:</i> <b><?=$row['LocalityName']?>, <?=$row['Country']?></b> <i>Stratum:</i> <b><?=$row['Stratum']?></b><br />
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Geological age:</i> <b><?=$row['Age']?>, <?=$row['Epoch']?>, <?=$row['Period']?>, <?=$row['System']?></b><br />
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Minimum age:</i> <b><?=$row['FossilMinAge']?> mya</b> <i>Maximum age:</i> <b><?=$row['FossilMaxAge']?> mya</b><br />
                             <?php if($row['PBDBCollectionNum']>0) { ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[<a href="http://pbdb.org/cgi-bin/bridge.pl?action=basicCollectionSearch&collection_no=<?=$row['PBDBCollectionNum']?>" target="_new">View locality in Paleobiology Database</a>]</font>
									<?php } ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[all nodes with this fossil]</font></blockquote></td><td width="10%">&nbsp;</td></tr>


	<?php
	}
?>
	
<tr><td width="10%"></td><td align="left" valign="top"><i class="small_orange">extant tip pairs that stem from this node</i></td><td width="10%"></td></tr>
<tr><td width="10%"></td><td><blockquote>
<table width="60%" align="left">
<tr align="left"><td><b class="small_text">Taxon A</b></td><td><b class="small_text">Taxon B</b></td><td></td></tr>
<?php
$rowNumber = 0;
while ($row = mysql_fetch_array($tippair_results)) {
	$rowNumber++;
	?>
	<tr align="left" class="<?= ($rowNumber % 2)  ? 'odd' : 'even' ?>"><td style="><i class="small_text"><?=$row['TaxonA']?></i></td><td><i class="small_text"><?=$row['TaxonB']?></i></td><td class="small_blue">[<a href="Find_CalibrationsByTips.php?TaxonA=<?=$row['TaxonA']?>&TaxonB=<?=$row['TaxonB']?>">all nodes with this pair</a>]</td></tr>


	<?php
	}
?>
  </blockquote></table></td><td width="10%"></td></tr>
  
<tr><td width="10%"></td><td align="left" valign="top"><p></p></td><td width="10%"></td></tr>
</table>

	

<?php 
//open and print page footer template
require('Footer.txt');
?>
