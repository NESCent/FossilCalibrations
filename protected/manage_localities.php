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

//retrieve localities
if($_GET) {
   $query="SELECT * FROM localities WHERE ". mysql_real_escape_string($key[0]) ."=". mysql_real_escape_string($value[0]) ." ORDER BY LocalityName, Stratum";
   $locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
   $query='SELECT * FROM localities ORDER BY LocalityName, Stratum';
   $locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}

?>

<div style="float: right; margin: 12px 0;">
<!-- TODO: Should we allow adding a locality here? or only through Edit Calibration page? 
	<button style="float: right;" onclick="window.location = '/protected/edit_locality.php';">Add a new locality</button>
-->
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>
<h1 style="margin: 0.5em 0;">Manage Localities</h1>

<table width="100%" border="0">
  <tr class="manage-headers">
    <td width="5%"  align="center" valign="middle"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle"><strong>locality name</strong></td>
    <td width="37%" align="center" valign="middle"><strong>stratum</strong></td>
    <td width="37%" align="center" valign="middle"><strong>linked fossils</strong></td>
    <td width="10%" align="center" valign="middle"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($locality_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';

	// How many fossils link to this locality?
        $query="SELECT * FROM fossils
		WHERE LocalityID=". $row['LocalityID'];
	$linkedFossils=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedFossilCount = mysql_num_rows($linkedFossils);
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['LocalityID']?></td>
    <td align="right"><?=$row['LocalityName']?></td>
    <td align="right"><?=$row['Stratum']?></td>
    <td align="center">
	<? if ($linkedFossilCount == 0) {
	    ?>&mdash;<?
	   } else {
	      $nthFossil = 0;
	      while ($linkedFossil = mysql_fetch_array($linkedFossils)) {
		  $nthFossil++; ?>
	<a target="_blank" href="/protected/edit_fossil.php?id=<?= $linkedFossil['FossilID'] ?>"
	   ><?= $linkedFossil['CollectionAcro'] ?> <?= $linkedFossil['CollectionNumber'] ?></a><?
	      	  if ($nthFossil < $linkedFossilCount) { ?>, <? };
	      } 
	   }
	   mysql_free_result($linkedFossils);
	?>
    </td>
    <td style="white-space: nowrap;"><a href="/protected/edit_locality.php?id=<?=$row['LocalityID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_locality.php?id=<?=$row['LocalityID']?>" onclick="return promptForLocalityDeletion(this);">delete</a></td>
	<? /* TODO: check for depedencies, calibrations that are bound to this locality? Un-link them first? */ ?>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<script type="text/javascript">

function promptForLocalityDeletion(clicked) {
	var $deleteLink = $(clicked);
	var $linkedFossils = $deleteLink.closest('tr').find('a[href*=edit_fossil]');
	if ($linkedFossils.length > 0) {
		var singleOrPlural = ($linkedFossils.length === 1) ? 'fossil' : 'fossils';
		alert('This locality is linked to '+ $linkedFossils.length +' '+ singleOrPlural +'! Please review these using the links at left, and un-link the locality from each before deleting it.');
		return false;
	}
	return confirm('Are you sure you want to delete this locality? This action cannot be undone!');
}

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
