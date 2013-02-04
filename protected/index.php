<?php

// open and load site variables
require('../Site.conf');

/* Here's a self-contained Basic Auth login, in case .htaccess + .htpasswd is
 * not appropriate for some reason. Note that this login must still be forced
 * into HTTPS to avoid sending credentials in the clear.

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Fossil Calibration Database (admin area)"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Please <a href="/">contact us</a> to request permission in this area.';
    exit;
} else {
    $expectdUsername = 'username';
    $expectedPassword =  'secret';

    if($_SERVER['PHP_AUTH_USER'] != $expectdUsername || 
        $_SERVER['PHP_AUTH_PW'] != $expectedPassword) {
        echo 'Invalid username/password';
        exit;
    }

    //Add a cookie, set a flag on session or something
    $SESSION['blah'] = true;

    //display the page
}
*/

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');
?>

<div class="left-column">
	<div class="link-menu" style="">
		<a class="selected">Admin Dashboard</a>
		<a Xhref="#">Manage Calibrations</a>
		<a href="/Show_Publications.php">Manage Publications</a>
		<a Xhref="#">Manage Fossils</a>
		<a Xhref="#">Site Announcement</a>
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
