<?php 
// open and load site variables
require('../config.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$key=array_keys($_GET);
$value=array_values($_GET);

// Get details about calibration
$query = "SELECT * FROM View_Calibrations 
            WHERE ". mysql_real_escape_string($key[0]) ."='". mysql_real_escape_string($value[0]) ."'".
	// non-admin users should only see *Published* calibrations
	((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? '' :  
	       "  AND CalibrationID IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)"
	);

$calibration_results= mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$calibration_info=mysql_fetch_assoc($calibration_results);
if (!$calibration_info) {
	die("The requested calibration was not found (or has not yet been published).");
}

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
	WHERE calibration_id = '". mysql_real_escape_string($calibration_info['CalibrationID']) ."' AND definition_side = 'A'
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
	WHERE calibration_id = '". mysql_real_escape_string($calibration_info['CalibrationID']) ."' AND definition_side = 'B'
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

// Fetch any image for this calibration's tree (stuffed into the same table, using negative integers)
$treeImageID = $calibration_info['CalibrationID'] * -1;
$query = "SELECT * FROM publication_images WHERE PublicationID=". $treeImageID;
$tree_image_info_results = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$tree_image_info = mysql_fetch_assoc($tree_image_info_results);

// Fetch lineage from the most closely related NCBI node
$query = 'SELECT clade_root_multitree_id FROM calibrations_by_NCBI_clade WHERE calibration_id='.$calibration_info['CalibrationID'];
$results = mysql_query($query) or die ('Error in sql: '.$query.'|'. mysql_error());
$row = mysql_fetch_array($results);
$nearestNCBINodeMultitreeID = $row['clade_root_multitree_id'];
mysql_free_result($results);
// fetch information on the current node's ancestor path (NCBI only)
$query = 'CALL getAllAncestors('. $nearestNCBINodeMultitreeID .', "TEMP_ancestors", "NCBI" )';
$results = mysql_query($query) or die ('Error in sql: '.$query.'|'. mysql_error());
// more info about ancestors
$query = 'CALL getFullNodeInfo("TEMP_ancestors", "TEMP_ancestors_info" )';
$results = mysql_query($query) or die ('Error in sql: '.$query.'|'. mysql_error());
// filter results further
$query = 'SELECT * FROM TEMP_ancestors_info 
	WHERE source_tree = "NCBI"
	  AND multitree_node_id IN (SELECT multitree_node_id FROM calibration_browsing_tree)';
$result = mysql_query($query) or die ('Error in sql: '.$query.'|'. mysql_error());
// gather all results into an array 
$ancestors = array();
while($row=mysql_fetch_assoc($result)) {
	$ancestors[]=$row;
}
mysql_free_result($result);

$PageTitle = 'View fossil calibration for '.$calibration_info['NodeName'];

// open and print header template
require('header.php');
?>
<script type="text/javascript">
function toggleFossilDetails(clicked) {
	var $toggle = $(clicked);
	var $details = $toggle.closest('.single-fossil').find('.fossil-details');
	if ($details.is(':visible')) {
		$details.slideUp('fast');
		$toggle.text('show fossil details');
	} else {
		$details.slideDown('fast');
		$toggle.text('hide fossil details');
	}
}
</script>

<p>
<? if (userIsAdmin()) { ?>
   <input type="button" style="float: right;" onclick="window.location ='/protected/edit_calibration.php?id=<?= $calibration_info['CalibrationID'] ?>'; return false;" value="Edit calibration" />
<? } else { ?>
   <a style="float: right;" href="mailto:contact@calibrations.palaeo-electronica.org?subject=Comment%20on%20calibration%20<?= $calibration_info['CalibrationID'] ?>">
	<img src="/images/flag-icon.png" title="" valign="middle" style="padding-right: 2px;"/>comment on this calibration
   </a>
<? } ?>
   <h1><?=$calibration_info['NodeName']?><!-- (ID: <?=$calibration_info['CalibrationID']?>) --></h1>
</p>

<? /*
<pre><?= var_dump($ancestors) ?></pre>
*/ ?>

<div class="ancestor-path">
	<strong>Lineage (NCBI)</strong>: 
<?
    if (count($ancestors) == 0) { 
	// NOTE: This should never happen, but perhaps if tables are stale...
?>
	<i>This node has no ancestors.</i>
<?
    } else {
	 $nthAncestor = 0;
	 foreach ($ancestors as $row) {
	 /* ?><br/><pre><? var_dump($row); ?></pre><? */
		$nthAncestor++;
		// show each ancestor as a breadcrumb/link in chain of ancestry ?>
		<? if ($nthAncestor > 1) { ?><span class="path-divider">&raquo;</span><? } ?>
		<a title="Browse to ancestor clade" href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
		<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->
      <? }
    } ?>
</div><!-- end of .ancestor-path -->

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
<?php if(!empty($calibration_info['DOI'])) { 
	echo '<br><font class="small_text"><a href="'. 
	formatDOIForHyperlink($calibration_info['DOI']) 
	.'" target="_blank">[View electronic resource]</font></a>'; 
} ?></p>

<table width="100%">

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">node name</i><br><b><?=$calibration_info['NodeName']?></b> 
<!--
&nbsp; &nbsp; <font class="small_blue">Look for this name in 
	<a href="http://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?name=<?=$calibration_info['NodeName']?>" target="_blank">NCBI</a> 
	&nbsp;
	<a href="http://en.wikipedia.org/wiki/<?=$calibration_info['NodeName']?>" target="_blank">Wikipedia</a> 
	&nbsp;
	<a href="http://animaldiversity.ummz.umich.edu/site/accounts/information/<?=$calibration_info['NodeName']?>.html" target="_blank">Animal Diversity Web</a></font>
-->
</td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">recommended citations</i><br><b><?=$calibration_info['ShortName']?></b>
</td><td width="10%">&nbsp;</td></tr>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">node minimum age </i><br><b><?=$calibration_info['MinAge']?> Ma</b> 
      <?php if ($calibration_info['MinAgeExplanation']) { ?>
	<font style="font-size: 90%;"><br/><?=$calibration_info['MinAgeExplanation']?></font>
      <?php } ?>
</td><td width="10%">&nbsp;</td></tr>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top">
	<i class="small_orange">node maximum age </i><br>
      <?php if ($calibration_info['MaxAgeExplanation']) { ?>
	<b><?=$calibration_info['MaxAge']?> Ma</b>
	<font style="font-size: 90%;"><br/><?=$calibration_info['MaxAgeExplanation']?></font>
      <?php } else { ?>
	<b>None specified</b>
      <?php } ?>
</td><td width="10%">&nbsp;</td></tr>


<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">primary fossil used to date this node</i></td><td width="10%">&nbsp;</td></tr>
<?php
// peek ahead to first linked fossil and grab its ID, then reset pointer
$row = mysql_fetch_array($fossil_results);
if ($row && isset($row['FCLinkID'])) {
	$firstLinkedFossilID = $row['FCLinkID'];
} else {
	$firstLinkedFossilID = null;
}
if (mysql_num_rows($fossil_results) > 0) {
	mysql_data_seek($fossil_results, 0);
}

/* Show the explicitly marked primary fossil and its phylogenetic justification. 
 * IF marker is NULL or empty, use the first (probably only) fossil.
 * IF marker is no longer valid (ID of a fossil since un-linked), use the first fossil.
 */
$primaryLinkedFossil = null;
$primaryLinkedFossilID = empty($calibration_info['PrimaryLinkedFossilID']) ? 
	$firstLinkedFossilID : 
	$calibration_info['PrimaryLinkedFossilID'];

while ($row = mysql_fetch_array($fossil_results)) {
	// treat this as the primary fossil? grab the first one by default, and override if we get a match
	if ($primaryLinkedFossil == null || ($calibration_info['PrimaryLinkedFossilID'] == $row['FCLinkID'])) {
		$primaryLinkedFossil = $row; 
	}
} 
if ($primaryLinkedFossil !== null) { 
	$row = $primaryLinkedFossil; ?>
<tr><td width="10%">&nbsp;</td><td><blockquote class="single-fossil odd" style="font-size: 90%;">

<b><?=$row['CollectionAcro']?> <?=$row['CollectionNumber']?></b>

<br />

	<b>
		<i><?=empty($row['Species']) ? 'NO SPECIES' : $row['Species'] ?></i>, 
		<?=empty($row['TaxonAuthor']) ? 'NO REFERENCE' : $row['TaxonAuthor'] ?>
	</b>
<br />

	<i>Location relative to the calibrated node:</i>
<? if ($row['FossilLocationRelativeToNode'] == null) { ?>
	<b>???</b>
<? } else { 
	// describe the relative location of this fossil
        $query = "SELECT * FROM  `L_FossilRelativeLocation` 
		  WHERE 
			RelLocationID = '". mysql_real_escape_string($row['FossilLocationRelativeToNode']) ."'";

	$result = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$matching_location = mysql_fetch_assoc($result);
	mysql_free_result($result); ?>

	<b><?= $matching_location['RelLocation'] ?></b>
<? } ?>
<br />
<br />
<font class="small_blue">[<a href="#" onclick="toggleFossilDetails(this); return false;">show fossil details</a>]</font>
<div class="fossil-details" style="margin-bottom: -1em;">
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Locality:</i> <b>
				<?=empty($row['LocalityName']) ? 'NO NAME' : $row['LocalityName'] ?>
			     </b> <br />
                           <?php if (!empty($row['Stratum'])) { ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Stratum:</i> <b><?=$row['Stratum']?></b><br />
			   <?php } ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Geological age:</i> <b>
				<? $ageLabel = empty($row['Age']) ? '' : $row['Age'].', ';
				   $ageLabel .= empty($row['Epoch']) ? '' : $row['Epoch'].', ';
				   $ageLabel .= empty($row['Period']) ? '' : $row['Period'].', ';
				   $ageLabel .= empty($row['System']) ? '' : $row['System'];
				?>
				<?= $ageLabel ?></b><br />
                             <!-- &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Minimum age:</i> <b><?=$row['MinAge']?> Ma</b> <i>Maximum age:</i> <b><?=$row['MaxAge']?> Ma</b><br /> -->
                           <?php if($row['PBDBCollectionNum']>0) { ?>
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[<a href="http://fossilworks.org/?a=collectionSearch&collection_no=<?=$row['PBDBCollectionNum']?>" target="_new">View locality in Paleobiology Database</a>]</font>
			   <?php } ?>
			<!-- TODO: show all calibrated nodes that link to this fossil?
                             &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font class="small_blue">[all nodes with this fossil]</font>
			-->
</div>

<?php if($row['PBDBTaxonNum']>0) {?>
<br />
<br />
<font class="small_blue">
	More information in 
	<a href="http://fossilworks.org/?a=taxonInfo&taxon_no=<?=$row['PBDBTaxonNum']?>" target="_blank">
		Fossilworks
	</a>
	&nbsp; 
	<a href="http://paleobiodb.org/cgi-bin/bridge.pl?a=basicTaxonInfo&taxon_no=<?=$row['PBDBTaxonNum']?>" target="_blank">
		PaleoBioDB
	</a>
</font>
<?php } ?>
</blockquote></td><td width="10%">&nbsp;</td></tr>

<? } ?>

<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><p></p></td><td width="10%">&nbsp;</td></tr>
  
<? // show the primary phylogenetic justification, if any
   if ($primaryLinkedFossil) { ?>
<tr>
	<td width="10%">&nbsp;</td>
	<td align="left" valign="top">
	    <i class="small_orange">phylogenetic justification</i>
	    <br/>
	    <?= $primaryLinkedFossil['PhyJustification'] ?>
	</td>
	<td width="10%">&nbsp;</td>
</tr>
<? } ?>

<? // if there's a tree image mapped to this calibration, show it
   if (isset($tree_image_info) && $tree_image_info['image']) { 
	$usingDefaultCaption = $tree_image_info['caption'] && strpos($tree_image_info['caption'], 'Tree for calibration ') === 0;
	?>
<tr><td width="10%">&nbsp;</td><td align="left" valign="top"><i class="small_orange">tree image (click image for full size)</i></td><td width="10%">&nbsp;</td></tr>
<tr>
	<td width="10%"></td>
	<td align="left" valign="top">
	    <a href="/publication_image.php?id=<?= $treeImageID ?>" target="_blank" title="Click to see full-size image in a new window">
		<img src="/publication_image.php?id=<?= $treeImageID ?>" 
		     style="background-color: #eee; margin-top: 12px; max-width: 744px;"
		     alt="tree image" />
	    </a>
	<? if ($tree_image_info['caption'] && !($usingDefaultCaption)) { ?>
		<div class="image-caption">
			<?= $tree_image_info['caption'] ?>
		</div>
	<? } ?>
	</td>
	<td width="10%"></td>
</tr>
<? } ?>

</table>

<?php 
//open and print page footer template
require('footer.php');
?>
