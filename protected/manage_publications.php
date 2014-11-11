<?php 
/* 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */
// open and load site variables
require('../../config.php');
require('../FCD-helpers.php');

// secure this page
requireRoleOrLogin('ADMIN');

// open and print header template
require('../header.php');
?>

<?php
// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$key=array_keys($_GET);
$value=array_values($_GET);

//retrieve publications
if($_GET) {
   $query="SELECT * FROM publications WHERE ". mysql_real_escape_string($key[0]) ."=". mysql_real_escape_string($value[0]) ." ORDER BY ShortName";
   $publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
   $query='SELECT * FROM publications ORDER BY ShortName';
   $publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}

// list of all publication-status values
$query='SELECT * FROM L_PublicationStatus';
$pubstatus_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pubstatus_values = Array();
while ($row = mysql_fetch_array($pubstatus_list)) {
	$pubstatus_values[ $row['PubStatusID'] ] = $row['PubStatus'];
}

?>

<div style="float: right; margin: 12px 0;">
	<button onclick="window.location = '/protected/edit_publication.php';">Add a new publication</button>
	&nbsp;
	&nbsp;
	&nbsp;
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>
<h1 style="margin: 0.5em 0;">Manage Publications</h1>

<table width="100%" border="0">
  <tr class="manage-headers">
    <td width="5%"  align="center" valign="middle"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle"><strong>short form</strong></td>
    <td width="37%" align="center" valign="middle"><strong style="float: left;">&nbsp;full citation</strong><strong style="float:right;">doi/url&nbsp;</strong></td>
    <td width="15%" align="center" valign="middle"><strong>linked&nbsp;to&nbsp;calibrations&hellip;</strong></td>
    <td width="18%" align="center" valign="middle"><strong>status</strong></td>
    <td width="10%" align="center" valign="middle"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($publication_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';

	// How many calibrations link to this publication...
	// ...as the calibration's main publication (one per calibration)
        $query="SELECT * FROM calibrations
		  WHERE NodePub='". $row['PublicationID'] ."';";
	$linkedAsMainPub = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedAsMainPubCount = mysql_num_rows($linkedAsMainPub);
	// ...as a linked fossil's main/fossil publication (one per linked fossil)
        $query="SELECT * FROM calibrations
		  WHERE CalibrationID IN
                    (SELECT CalibrationID FROM Link_CalibrationFossil WHERE FossilID IN
                      (SELECT FossilID FROM fossils WHERE FossilPub='". $row['PublicationID'] ."'));";
	$linkedAsFossilPub = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedAsFossilPubCount = mysql_num_rows($linkedAsFossilPub);
	// ...as a linked fossil's phylogeny publication (multiple per linked fossil)
        $query="SELECT * FROM calibrations
                  WHERE CalibrationID IN
                    (SELECT CalibrationID FROM Link_CalibrationFossil WHERE FCLinkID IN
                      (SELECT LinkedFossilID FROM Link_PhyloPublication_LinkedFossil WHERE PhyloPublicationID='". $row['PublicationID'] ."'));";
	$linkedAsPhyloPub = mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedAsPhyloPubCount = mysql_num_rows($linkedAsPhyloPub);
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['PublicationID']?></td>
    <td>
        <?= empty($row['ShortName']) ? '&mdash;' : $row['ShortName'] ?>
    </td>
    <td>
        <?= empty($row['FullReference']) ? '&mdash;' : $row['FullReference'] ?>
      <? if (!empty($row['DOI'])) { ?>
	<a style="display: block; margin-top: 4px; text-align: right;" href="<?= formatDOIForHyperlink($row['DOI']) ?>" target="_new"><?=$row['DOI']?></a>
      <? } else { ?>
	<span style="display: block; margin-top: 4px; text-align: right">&mdash;</span>
      <? } ?>
    </td>
    <td>
	<? if ($linkedAsMainPubCount + $linkedAsFossilPubCount + $linkedAsPhyloPubCount == 0) {
	    ?>&mdash;<?
	   } else { 
	        if ($linkedAsMainPubCount > 0) { ?>
		<div>
			<h4 style="margin: 2px 0;">...as&nbsp;main&nbsp;publication</h4>
	      <? $nthCalibration = 0;
		 while ($linkedCalibration = mysql_fetch_array($linkedAsMainPub)) {
		    $nthCalibration++; ?>
			<a target="_blank" href="/Show_Calibration.php?CalibrationID=<?= $linkedCalibration['CalibrationID'] ?>"
			><?= $linkedCalibration['NodeName'] ?></a><?
	      	  if ($nthCalibration < $linkedAsMainPubCount) { ?>, <? };
	      } ?>
		</div>
		<? } 
	        if ($linkedAsFossilPubCount > 0) { ?>
		<div>
			<h4 style="margin: 2px 0;">...as&nbsp;fossil&nbsp;publication</h4>
	      <? $nthCalibration = 0;
		 while ($linkedCalibration = mysql_fetch_array($linkedAsFossilPub)) {
		    $nthCalibration++; ?>
			<a target="_blank" href="/Show_Calibration.php?CalibrationID=<?= $linkedCalibration['CalibrationID'] ?>"
			><?= $linkedCalibration['NodeName'] ?></a><?
	      	  if ($nthCalibration < $linkedAsFossilPubCount) { ?>, <? };
	      } ?>
		</div>
		<? } 
	        if ($linkedAsPhyloPubCount > 0) { ?>
		<div>
			<h4 style="margin: 2px 0;">...as&nbsp;phylogeny&nbsp;publication</h4>
	      <? $nthCalibration = 0;
		 while ($linkedCalibration = mysql_fetch_array($linkedAsPhyloPub)) {
		    $nthCalibration++; ?>
			<a target="_blank" href="/Show_Calibration.php?CalibrationID=<?= $linkedCalibration['CalibrationID'] ?>"
			><?= $linkedCalibration['NodeName'] ?></a><?
	      	  if ($nthCalibration < $linkedAsPhyloPubCount) { ?>, <? };
	      } ?>
		</div>
		<? } 
	   } ?>
    </td>
    <td align="right"><? if (is_numeric($row['PublicationStatus'])) {
         echo '<i>'.$pubstatus_values[ $row['PublicationStatus'] ].'</i>';
	   } else {
         echo '<i>?</i>';
	   }
  ?></td>
    <td style="white-space: nowrap;"><a href="/protected/edit_publication.php?id=<?=$row['PublicationID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_publication.php?id=<?=$row['PublicationID']?>" onclick="return promptForPublicationDeletion(this);">delete</a></td>
	<? /* TODO: check for depedencies, calibrations that are still bound to this publication? Or delete them too? */ ?>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<button style="float: right;" onclick="window.location = '/protected/edit_publication.php';">Add a new publication</button>
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<script type="text/javascript">

function promptForPublicationDeletion(clicked) {
	var $deleteLink = $(clicked);
	var $linkedCalibrations = $deleteLink.closest('tr').find('a[href*=Show_Calibration]');
	if ($linkedCalibrations.length > 0) {
		var singleOrPlural = ($linkedCalibrations.length === 1) ? 'calibration' : 'calibrations';
		alert('This publication is linked to '+ $linkedCalibrations.length +' '+ singleOrPlural +'! Please review these using the links at left, and un-link the publication from each before deleting it.');
		return false;
	}
	return confirm('Are you sure you want to delete this fossil? This action cannot be undone!');
}

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
