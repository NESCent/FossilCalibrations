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

//retrieve calibrations
if($_GET) {
	$query="SELECT * FROM calibrations WHERE $key[0]=$value[0] ORDER BY NodeName";
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
} else {
	$query='SELECT * FROM calibrations ORDER BY NodeName';
	$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}
	
// list of all publication-status values
$query='SELECT * FROM L_PublicationStatus';
$pubstatus_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pubstatus_values = Array();
while ($row = mysql_fetch_array($pubstatus_list)) {
	$pubstatus_values[ $row['PubStatusID'] ] = $row['PubStatus'];
}

?>

<div style="margin: 12px 0;">
	<button style="float: right;" onclick="window.location = '/protected/edit_calibration.php';">Add a new calibration</button>
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>

<table width="100%" border="0">
  <tr>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>node name</strong></td>
    <td width="55%" align="center" valign="middle" bgcolor="#999999"><strong>comments</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>status</strong></td>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>actions</strong></td>
  </tr>

<?php
$nthRow = 0;
while ($row = mysql_fetch_array($calibration_list)) {
	$nthRow++;
	$oddOrEven = ($nthRow % 2) ? 'odd' : 'even';
?>
  
  <tr align="left" valign="top" class="<?= $oddOrEven ?>">
    <td align="center"><?=$row['CalibrationID']?></td>
    <td><?=$row['NodeName']?></td>
    <td><?=$row['AdminComments']?></td>
    <td align="right"><? if (is_numeric($row['PublicationStatus'])) {
		echo '<i>'.$pubstatus_values[ $row['PublicationStatus'] ].'</i>&nbsp; ';
	   } else {
		echo '<i>?</i>&nbsp; ';
	   }
  ?></td>
    <td style="white-space: nowrap;"><a href="/protected/edit_calibration.php?id=<?=$row['CalibrationID']?>">edit</a>
	&nbsp;
	&nbsp;
	&nbsp;
        <a style="color: #f88;" href="/protected/delete_calibration.php?id=<?=$row['CalibrationID']?>" onclick="return confirm('Are you sure you want to delete this calibration? This action cannot be undone!');">delete</a></td>
	<? /* TODO: check for depedencies, nodes that are still bound to this calibration, etc? Or delete them too? */ ?>
  </tr>

<?php } ?>  
  
</table>

<div style="margin: 12px 0 6px;">
	<button style="float: right;" onclick="window.location = '/protected/edit_calibration.php';">Add a new calibration</button>
	<a href="/protected/index.php">&laquo; Back to admin dashboard</a>
</div>
	

<?php 
//open and print page footer template
require('../footer.php');
?>
