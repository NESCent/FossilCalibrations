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

//retrieve fossils
if($_GET) {
   $query="SELECT * FROM fossils WHERE ". mysql_real_escape_string($key[0]) ."=". mysql_real_escape_string($value[0]) ." ORDER BY CollectionAcro, CAST(CollectionNumber AS DECIMAL)";
   $publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
   $query='SELECT * FROM `fossils` ORDER BY CollectionAcro, CAST(CollectionNumber AS DECIMAL)';
   $publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}

?>

<div style="margin: 12px 0;">
<!-- TODO: Should we allow adding a fossil here? or only through Edit Calibration page? 
	<button style="float: right;" onclick="window.location = '/protected/edit_fossil.php';">Add a new fossil</button>
-->
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<table width="100%" border="0">
  <tr>
    <td width="5%"  align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>collection</strong></td>
    <td width="37%" align="center" valign="middle" bgcolor="#999999"><strong>collection number</strong></td>
    <td width="37%" align="center" valign="middle" bgcolor="#999999"><strong>linked calibrations</strong></td>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($publication_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';

	// How many calibrations link to this fossil?
        $query="SELECT Link_CalibrationFossil.*, calibrations.NodeName 
		  FROM Link_CalibrationFossil
		  INNER JOIN calibrations 
		  ON Link_CalibrationFossil.CalibrationID=calibrations.CalibrationID
		  WHERE Link_CalibrationFossil.FossilID=". $row['FossilID'];
	$linkedCalibrations=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedCalibrationCount = mysql_num_rows($linkedCalibrations);
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['FossilID']?></td>
    <td align="right"><?=$row['CollectionAcro']?></td>
    <td align="right"><?=$row['CollectionNumber']?></td>
    <td align="center">
	<? if ($linkedCalibrationCount == 0) {
	    ?>&mdash;<?
	   } else {
	      $nthCalibration = 0;
	      while ($linkedCalibration = mysql_fetch_array($linkedCalibrations)) {
		  $nthCalibration++; ?>
	<a target="_blank" href="/Show_Calibration.php?CalibrationID=<?= $linkedCalibration['CalibrationID'] ?>"
	   ><?= $linkedCalibration['NodeName'] ?></a><?
	      	  if ($nthCalibration < $linkedCalibrationCount) { ?>, <? };
	      } 
	   }
	   mysql_free_result($linkedCalibrations);
	?>
    </td>
    <td style="white-space: nowrap;"><a href="/protected/edit_fossil.php?id=<?=$row['FossilID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_fossil.php?id=<?=$row['FossilID']?>" onclick="return promptForFossilDeletion(this);">delete</a></td>
	<? /* TODO: check for depedencies, calibrations that are bound to this fossil? Un-link them first? */ ?>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<script type="text/javascript">

function promptForFossilDeletion(clicked) {
console.log(clicked);
	var $deleteLink = $(clicked);
	var $linkedCalibrations = $deleteLink.closest('tr').find('a[href*=Show_Calibration]');
	if ($linkedCalibrations.length > 0) {
		var singleOrPlural = ($linkedCalibrations.length === 1) ? 'calibration' : 'calibrations';
		alert('This fossil is linked to '+ $linkedCalibrations.length +' '+ singleOrPlural +'! Please review these using the links at left, and un-link the fossil from each before deleting it.');
		return false;
	}
	return confirm('Are you sure you want to delete this fossil? This action cannot be undone!');
}

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
