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
Site Statistics
</h3>
<p>Here's a paragraph.</p>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Pending Calibrations and Other Tasks
</h3>
<p>Here's a paragraph.</p>

<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
Site Maintenance
</h3>
<p>Here's a paragraph.</p>

</div>

<?php 
//open and print page footer template
require('../footer.php');
?>
