<?php 
// open and load site variables
require('../Site.conf');

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
$query="SELECT * FROM publications WHERE $key[0]=$value[0] ORDER BY ShortName";
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
$query='SELECT * FROM publications ORDER BY ShortName';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	
}

?>

<div style="margin: 12px 0;">
	<button style="float: right;" onclick="window.location = '/protected/edit_publication.php';">Add a new publication</button>
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<table width="100%" border="0">
  <tr>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>short form</strong></td>
    <td width="55%" align="center" valign="middle" bgcolor="#999999"><strong>full citation</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>doi/url</strong></td>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($publication_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['PublicationID']?></td>
    <td><?=$row['ShortName']?></td>
    <td><?=$row['FullReference']?></td>
    <td><a href="http://dx.doi.org/<?=$row['DOI']?>" target="_new"><?=$row['DOI']?></a></td>
    <td style="white-space: nowrap;"><a href="/protected/edit_publication.php?id=<?=$row['PublicationID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_publication.php?id=<?=$row['PublicationID']?>" onclick="return confirm('Are you sure you want to delete this publication? This action cannot be undone!');">delete</a></td>
	<? /* TODO: check for depedencies, calibrations that are still bound to this publication? Or delete them too? */ ?>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<button style="float: right;" onclick="window.location = '/protected/edit_publication.php';">Add a new publication</button>
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<?php 
//open and print page footer template
require('../footer.php');
?>
