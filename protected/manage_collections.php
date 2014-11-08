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

//retrieve fossil collections (just the acronym and institution name)
if($_GET) {
   $query="SELECT * FROM L_CollectionAcro WHERE ". mysql_real_escape_string($key[0]) ."=". mysql_real_escape_string($value[0]) ." ORDER BY Acronym";
   $collection_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
   $query='SELECT * FROM `L_CollectionAcro` ORDER BY Acronym';
   $collection_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}

?>

<div style="float: right; margin: 12px 0;">
<!-- TODO: Should we allow adding a collection here? or only through the Edit Calibration page? 
	<button style="float: right;" onclick="window.location = '/protected/edit_collection.php';">Add a new fossil collection</button>
-->
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>
<h1 style="margin: 0.5em 0;">Manage Collections</h1>

<table width="100%" border="0">
  <tr class="manage-headers">
    <td width="5%"  align="center" valign="middle"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle"><strong>acronym</strong></td>
    <td width="37%" align="center" valign="middle"><strong>collection name</strong></td>
    <td width="37%" align="center" valign="middle"><strong>linked fossils</strong></td>
    <td width="10%" align="center" valign="middle"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($collection_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';

	// How many fossils link to this collection?
        $query="SELECT *
		  FROM fossils
		  WHERE CollectionAcro='". $row['Acronym'] ."'";
	$linkedFossils=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$linkedFossilCount = mysql_num_rows($linkedFossils);
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['AcroID']?></td>
    <td align="right"><?=$row['Acronym']?></td>
    <td align="right"><?=$row['CollectionName']?></td>
    <td align="center">
	<? if ($linkedFossilCount == 0) {
	    ?>&mdash;<?
	   } else {
	      $nthFossil = 0;
	      while ($linkedFossil = mysql_fetch_array($linkedFossils)) {
		  $nthFossil++; ?>
	<a target="_blank" href="/protected/edit_fossil.php?id=<?= $linkedFossil['FossilID'] ?>"
	   ><?= empty($linkedFossil['CollectionNumber']) ? 'NO NUMBER' : $linkedFossil['CollectionNumber'] ?></a><?
	      	  if ($nthFossil < $linkedFossilCount) { ?>, <? };
	      } 
	   }
	   mysql_free_result($linkedFossils);
	?>
    </td>
    <td style="white-space: nowrap;"><a href="/protected/edit_collection.php?id=<?=$row['AcroID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_collection.php?id=<?=$row['AcroID']?>" onclick="return promptForCollectionDeletion(this);">delete</a></td>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<script type="text/javascript">

function promptForCollectionDeletion(clicked) {
console.log(clicked);
	var $deleteLink = $(clicked);
	var $linkedFossils = $deleteLink.closest('tr').find('a[href*=edit_fossil]');
	if ($linkedFossils.length > 0) {
		var singleOrPlural = ($linkedFossils.length === 1) ? 'fossil' : 'fossils';
		alert('This collection is linked to '+ $linkedFossils.length +' '+ singleOrPlural +'! Please review these using the links at left, and re-assign each fossil before deleting this collection.');
		return false;
	}
	return confirm('Are you sure you want to delete this collection? This action cannot be undone!');
}

</script>

<?php 
//open and print page footer template
require('../footer.php');
?>
