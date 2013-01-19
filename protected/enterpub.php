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

//Enter publication
$query='INSERT INTO publications (ShortName, FullReference, DOI) VALUES (\''.$_POST['ShortForm'].'\', \''.$_POST['FullCite'].'\', \''.$_POST['DOI'].'\')';
$enter_pub=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve publication info
$query='SELECT * FROM publications WHERE FullReference=\''.$_POST['FullCite'].'\' ORDER BY ShortName';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


?>
<h1>The following information was entered in the database<br />Close this window without refreshing</h1>

<table width="100%" border="0">
   <tr>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>short form</strong></td>
    <td width="55%" align="center" valign="middle" bgcolor="#999999"><strong>full citation</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>doi</strong></td>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>date entered</strong></td>
  </tr>

<?php
mysql_data_seek($publication_list, mysql_num_rows($publication_list)-1);
$row = mysql_fetch_array($publication_list);
?>
  
  <tr align="left" valign="top">
    <td><?=$row['PublicationID']?></td>
    <td><?=$row['ShortName']?></td>
    <td><?=$row['FullReference']?></td>
    <td><?=$row['DOI']?></td>
    <td><?=$row['DateCreated']?></td>
  </tr>

  
</table>

	

<?php 
//open and print page footer template
require('footer.php');
?>
