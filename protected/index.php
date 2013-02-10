<?php
// open and load site variables
require('../Site.conf');
require('../FCD-helpers.php');

// this page requires admin login
requireRoleOrLogin('ADMIN');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// gather some key stats for the FCD site
$query="SELECT COUNT(*) AS count FROM calibrations";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$values = mysql_fetch_assoc($result);
$submittedCalibrations = $values['count'];
mysql_free_result($result);

$query="SELECT COUNT(*) AS count FROM calibrations WHERE PublicationStatus = 4";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$values = mysql_fetch_assoc($result);
$publishedCalibrations = $values['count'];
mysql_free_result($result);

$query="SELECT * FROM site_status";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$site_status = mysql_fetch_assoc($result);
mysql_free_result($result);

?>
<div class="left-column">
	<div class="link-menu" style="">
		<!-- <a class="selected">Admin Dashboard</a> -->
		<a href="/protected/manage_calibrations.php">Manage Calibrations</a>
		<a href="/protected/manage_publications.php">Manage Publications</a>
		<!-- <a Xhref="#">Manage Fossils</a> -->
		<a href="/protected/edit_site_announcement.php">Edit Site Announcement</a>
	</div>
</div>

<div class="center-column" style="padding-right: 0;">

<h1>
Admin Dashboard
</h1>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Pending Calibrations and Other Tasks
</h3>
<p>Here's a paragraph.</p>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Site Statistics
</h3>
<table border="0" cellspacing="5">
 <tr>
  <td align="right" valign="top">
   Submitted calibrations
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= $submittedCalibrations ?> 
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   Published calibrations
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= $publishedCalibrations ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   Last multitree update
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= date("M d, Y", strtotime($site_status['last_build_time'])) ?>
   <? if ($site_status['needs_build'] == 1) { ?>
        <b style="color: #c33;">&mdash; needs update!</b>
   <? } ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   Last NCBI update
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= date("M d, Y", strtotime($site_status['last_NCBI_update_time'])) ?>
  </td>
 </tr>
</table>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Site Maintenance
</h3>
<table border="0" cellspacing="5">
 <tr>
  <td align="right" valign="top">
   &nbsp;
  </td>
  <td valign="top">
   <input type="button" value="Refresh auto-complete lists" />
        &nbsp; <i>requires ~45 minutes</i>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   &nbsp;
  </td>
  <td valign="top">
   <input type="button" value="Rebuild searchable multitree" />
        &nbsp; <i>requires ~10 minutes</i>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   &nbsp;
  </td>
  <td valign="top">
   <input type="button" value="Upload and import NCBI taxonomy" />
  </td>
 </tr>
</table>

</div>

<?php 
//open and print page footer template
require('../footer.php');
?>
