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

// count the publications in different NON-PUBLISHED states
$query="SELECT COUNT(*) AS count FROM publications WHERE PublicationStatus = 1"; // Private Draft
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$values = mysql_fetch_assoc($result);
$pubs_PrivateDraft = $values['count'];
mysql_free_result($result);

$query="SELECT COUNT(*) AS count FROM publications WHERE PublicationStatus = 2"; // Under Revision
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$values = mysql_fetch_assoc($result);
$pubs_UnderRevision = $values['count'];
mysql_free_result($result);

$query="SELECT COUNT(*) AS count FROM publications WHERE PublicationStatus = 3"; // Ready for Publication
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$values = mysql_fetch_assoc($result);
$pubs_ReadyForPublication = $values['count'];
mysql_free_result($result);


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

/* Get current status values for all site maintenance tasks/tables
 *  values should be 'needs update', 'updating now', 'up to date'
$autoCompleteStatus = $site_status['autocomplete_status'];
$multitreeStatus = $site_status['multitree_status'];
$NCBIStatus = $site_status['NCBI_status'];
 */


?>
<script type="text/javascript">
   var autoCompleteStatus = 'ready';
   $(document).ready(function() {
      // bind site maintenance buttons
      $('#update-auto-complete').unbind('click').click(function() {
         var $button = $(this);
         var $indicator = $('#update-auto-complete-status');
      });
   });
</script>

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
Pending Publications
</h3>
<table border="0" cellspacing="5">
 <tr>
  <td align="right" valign="top">
   <a href="/protected/manage_publications.php?PublicationStatus=1">Private Draft</a>
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= $pubs_PrivateDraft ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   <a href="/protected/manage_publications.php?PublicationStatus=2">Under Revision</a> 
  </td>
  <td valign="top" style="font-weight: bold;">
    <?= $pubs_UnderRevision ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   <a href="/protected/manage_publications.php?PublicationStatus=3">Ready for Publication</a> 
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= $pubs_ReadyForPublication ?>
  </td>
 </tr>
</table>

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
   <?= date("M d, Y", strtotime($site_status['last_multitree_update'])) ?>
   <? /*if ($site_status['needs_build'] == 1) { ?>
        <b style="color: #c33;">&mdash; needs update!</b>
   <? } */ ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   Last NCBI update
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= date("M d, Y", strtotime($site_status['last_NCBI_update'])) ?>
  </td>
 </tr>
</table>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Site Maintenance
</h3>
<table border="0" cellspacing="5">
 <tr>
  <td align="right" valign="top">
   <input type="button" id="update-auto-complete" value="Update auto-complete lists" />
  </td>
  <td valign="top">
   <div id="update-auto-complete-status">
      <img align="absmiddle" src="/images/status-red.png" title="ready" alt="ready" />
      &nbsp; <i>Needs update (requires ~10 minutes)</i>
   </div>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   <input type="button" id="update-multitree" value="Update searchable multitree" />
  </td>
  <td valign="top">
   <div id="update-multitree-status">
      <img align="absmiddle" src="/images/status-yellow.png" title="ready" alt="ready" />
      &nbsp; <i>Updating now, ~6 minutes remaining</i>
   </div>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   <input type="button" id="update-NCBI" value="Upload and import NCBI taxonomy" />
  </td>
  <td valign="top">
    <div id="update-NCBI-status">
       <img align="absmiddle" src="/images/status-green.png" title="ready" alt="ready" />
       &nbsp; <i>Last update: <?= date("M d, Y", strtotime($site_status['last_NCBI_update'])) ?> </i>
   </div>
  </td>
 </tr>
</table>

</div>

<?php 
//open and print page footer template
require('../footer.php');
?>
