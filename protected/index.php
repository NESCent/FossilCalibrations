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
 *  values should be 'Needs update', 'Updating now', or 'Up to date'
 */
$autoCompleteStatus = $site_status['autocomplete_status'];
$calibrationsByCladeStatus = $site_status['cladeCalibration_status'];
$multitreeStatus = $site_status['multitree_status'];
$NCBIStatus = $site_status['NCBI_status'];

?>
<script type="text/javascript">
   var autoCompleteStatus = '<?= $autoCompleteStatus ?>';
   var calibrationsByCladeStatus = '<?= $calibrationsByCladeStatus ?>';
   var multitreeStatus = '<?= $multitreeStatus ?>';
   var NCBIStatus = '<?= $NCBIStatus ?>';

   var statusCheckTimeout = null;
   var checkStatusEveryNSeconds = 10;

   $(document).ready(function() {
      // bind site maintenance buttons
      $('#rebuild-all-calibration-trees').unbind('click').click(function() {
         // AJAX call to start operation, returns all status vars
         $.ajax({
            type: 'POST',
            url: '/protected/remote_operation.php',
            data: {'operation': 'REBUILD_ALL_CALIBRATION_TREES'},
            success: function(data) {
               console.log('success! from initial call to REBUILD_ALL_CALIBRATION_TREES');
               checkRemoteUpdateStatus();
            },
            dataType: 'json',
            async: true
         });
      });
      $('#update-multitree').unbind('click').click(function() {
         // AJAX call to start operation, returns all status vars
         $.ajax({
            type: 'POST',
            url: '/protected/remote_operation.php',
            data: {'operation': 'UPDATE_MULTITREE'},
            success: function(data) {
               console.log('success! from initial call to UPDATE_MULTITREE');
               checkRemoteUpdateStatus();
            },
            dataType: 'json',
            async: true
         });
      });
      $('#update-calibrations-by-clade').unbind('click').click(function() {
         // AJAX call to start operation, returns all status vars
         $.ajax({
            type: 'POST',
            url: '/protected/remote_operation.php',
            data: {'operation': 'UPDATE_CALIBRATIONS_BY_CLADE'},
            success: function(data) {
               console.log('success! from initial call to UPDATE_CALIBRATIONS_BY_CLADE');
               checkRemoteUpdateStatus();
            },
            dataType: 'json',
            async: true
         });
      });
      $('#update-autocomplete').unbind('click').click(function() {
         // AJAX call to start operation, returns all status vars
         $.ajax({
            type: 'POST',
            url: '/protected/remote_operation.php',
            data: {'operation': 'UPDATE_AUTOCOMPLETE'},
            success: function(data) {
               console.log('success! from initial call to UPDATE_AUTOCOMPLETE');
               checkRemoteUpdateStatus();
            },
            dataType: 'json',
            async: true
         });
      });
      $('#update-NCBI').unbind('click').click(function() {
         alert('This feature is not currently available through the web.'); // TODO
      });

      // initialize display of all indicators
      updateStatusIndicators();
      // begin ongoing polling of server status
      checkRemoteUpdateStatus();
   });

   function checkRemoteUpdateStatus() {
      // clear any pending check and (re)start right now
      if (statusCheckTimeout) {
        clearTimeout(statusCheckTimeout);
        statusCheckTimeout = null;
      }
      blurStatusIndicators();
      $.ajax({
         type: 'POST',
         url: '/protected/remote_operation.php',
         data: {'operation': 'CHECK_UPDATE_STATUS'},
         success: function(data) {
             // console.log('success! from call to CHECK_UPDATE_STATUS');
             autoCompleteStatus = data.autocomplete_status;
             multitreeStatus = data.multitree_status;
             calibrationsByCladeStatus = data.cladeCalibration_status;
             NCBIStatus = data.NCBI_status;
             updateStatusIndicators();
             /* NOTE that we daisy-chain timeouts, instead of using
              * setInterval, to avoid pileups if the server is slow to respond.
              */
             statusCheckTimeout = setTimeout(
                checkRemoteUpdateStatus,
                (checkStatusEveryNSeconds * 1000)     // ping every n sec
             );
         },
         dataType: 'json',
         async: true
      });
   }

   function blurStatusIndicators() {
      $('#update-autocomplete-status img, #update-calibrations-by-clade-status img, #update-multitree-status img').attr('src', '/images/status-black.png');
      $('#update-autocomplete-status i, #update-calibrations-by-clade-status i, #update-multitree-status i').html('...');
   }

   function updateStatusIndicators() {
      updateAutocompleteStatus();
      updateCalibrationsByCladeStatus();
      updateMultitreeStatus();
   }

   function updateAutocompleteStatus( ) {
      switch(autoCompleteStatus) {
         case 'Needs update': 
            indicatorImgPath = '/images/status-red.png';
            msg = "Needs update (requires ~40 minutes)";
            isDisabled = false;
            break;

         case 'Updating now': 
            indicatorImgPath = '/images/status-yellow.png';
            msg = "Updating now";  // TODO: add live ", ~n minutes remaining" ?
            isDisabled = true;
            break;

         case 'Up to date':
            indicatorImgPath = '/images/status-green.png';
            msg = "Up to date";
            isDisabled = false;
            break;

         default:
            console.log('ERROR: unexpected value for autoCompleteStatus:'+ autoCompleteStatus +' <'+ typeof(autoCompleteStatus) +'>');
            return;
      }
      var $button = $('#update-autocomplete');
      if (isDisabled) {
         $button.attr('disabled','disabled');
      } else {
         $button.removeAttr('disabled');
      }
      var $indicator = $('#update-autocomplete-status');
      $indicator.find('img').attr({
         'src': indicatorImgPath,
         'alt': autoCompleteStatus,
         'title': autoCompleteStatus
      });
      $indicator.find('i').html( msg );
   }

   function updateCalibrationsByCladeStatus( ) {
      switch(calibrationsByCladeStatus) {
         case 'Needs update': 
            indicatorImgPath = '/images/status-red.png';
            // this operation depends on an up-to-date multitree...
	    if (multitreeStatus === 'Up to date') {
               isDisabled = false;
               msg = "Needs update (requires ~35 minutes)";
            } else {
               isDisabled = true;
               msg = "Needs update (but update the multitree first!)";
            }
            break;

         case 'Updating now': 
            indicatorImgPath = '/images/status-yellow.png';
            msg = "Updating now";  // TODO: add live ", ~n minutes remaining" ?
            isDisabled = true;
            break;

         case 'Up to date':
            indicatorImgPath = '/images/status-green.png';
            msg = "Up to date";
            isDisabled = false;
            break;

         default:
            console.log('ERROR: unexpected value for calibrationsByCladeStatus:'+ calibrationsByCladeStatus +' <'+ typeof(calibrationsByCladeStatus) +'>');
            return;
      }
      var $button = $('#update-calibrations-by-clade');
      if (isDisabled) {
         $button.attr('disabled','disabled');
      } else {
         $button.removeAttr('disabled');
      }
      var $indicator = $('#update-calibrations-by-clade-status');
      $indicator.find('img').attr({
         'src': indicatorImgPath,
         'alt': calibrationsByCladeStatus,
         'title': calibrationsByCladeStatus
      });
      $indicator.find('i').html( msg );
   }

   function updateMultitreeStatus( ) {
      switch(multitreeStatus) {
         case 'Needs update': 
            indicatorImgPath = '/images/status-red.png';
            msg = "Needs update (requires ~10 minutes)";
            isDisabled = false;
            break;

         case 'Updating now': 
            indicatorImgPath = '/images/status-yellow.png';
            msg = "Updating now";  // TODO: add live ", ~n minutes remaining" ?
            isDisabled = true;
            break;

         case 'Up to date':
            indicatorImgPath = '/images/status-green.png';
            msg = "Up to date";
            isDisabled = false;
            break;

         default:
            console.log('ERROR: unexpected value for multitreeStatus:'+ autoCompleteStatus +' <'+ typeof(multitreeStatus) +'>');
            return;
      }
      var $button = $('#update-multitree');
      if (isDisabled) {
         $button.attr('disabled','disabled');
      } else {
         $button.removeAttr('disabled');
      }
      var $indicator = $('#update-multitree-status');
      $indicator.find('img').attr({
         'src': indicatorImgPath,
         'alt': multitreeStatus,
         'title': multitreeStatus
      });
      $indicator.find('i').html( msg );
   }
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
   Last calibrations-by-clade update
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= date("M d, Y - h:m a", strtotime($site_status['last_cladeCalibration_update'])) ?>
   <? /*if ($site_status['needs_build'] == 1) { ?>
        <b style="color: #c33;">&mdash; needs update!</b>
   <? } */ ?>
  </td>
 </tr>
 <tr>
  <td align="right" valign="top">
   Last multitree update
  </td>
  <td valign="top" style="font-weight: bold;">
   <?= date("M d, Y - h:m a", strtotime($site_status['last_multitree_update'])) ?>
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
   <?= date("M d, Y - h:m a", strtotime($site_status['last_NCBI_update'])) ?>
  </td>
 </tr>
</table>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Site Maintenance
</h3>
<table border="0" cellspacing="5">

 <tr>
  <td align="right" valign="top">
   <input type="button" id="rebuild-all-calibration-trees" value="Rebuild all calibration trees" />
  </td>
  <td valign="top">
   <div id="rebuild-all-calibration-trees-status">
      <i><strong>Note:</strong> This is rarely needed!</i>
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
   <input type="button" id="update-calibrations-by-clade" value="Update calibrations-by-clade table" />
  </td>
  <td valign="top">
   <div id="update-calibrations-by-clade-status">
      <img align="absmiddle" src="/images/status-yellow.png" title="ready" alt="ready" />
      &nbsp; <i>Updating now, ~6 minutes remaining</i>
   </div>
  </td>
 </tr>

 <tr>
  <td align="right" valign="top">
   <input type="button" id="update-autocomplete" value="Update auto-complete lists" />
  </td>
  <td valign="top">
   <div id="update-autocomplete-status">
      <img align="absmiddle" src="/images/status-red.png" title="ready" alt="ready" />
      &nbsp; <i>Needs update (requires ~10 minutes)</i>
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
       &nbsp; <i>Last update: <?= date("M d, Y - h:m a", strtotime($site_status['last_NCBI_update'])) ?> </i>
   </div>
  </td>
 </tr>

</table>

</div>

<?php 
//open and print page footer template
require('../footer.php');
?>
