<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
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

<table width="100%" border="0">
  <tr>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>short form</strong></td>
    <td width="55%" align="center" valign="middle" bgcolor="#999999"><strong>full citation</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>doi/url</strong></td>
  </tr>

<?php
while ($row = mysql_fetch_array($publication_list)) {
?>
  
  <tr align="left" valign="top">
    <td align="center"><?=$row['PublicationID']?></td>
    <td><?=$row['ShortName']?></td>
    <td><?=$row['FullReference']?></td>
    <td><a href="http://dx.doi.org/<?=$row['DOI']?>" target="_new"><?=$row['DOI']?></a></td>
  </tr>

<?php } ?>  
  
</table>

	

<?php 
//open and print page footer template
require('Footer.txt');
?>
