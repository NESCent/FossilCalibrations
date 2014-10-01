<?php
// open and load site variables
require('../config.php');

// clear session flags (user is now logged out, anonymous)
$_SESSION['IS_ADMIN_USER'] = false;
$_SESSION['IS_REVIEWER'] = false;

// bounce to the intended destination URL (or home page by default)
$redirectURL = "http://" . $SITEINFO['hostname_and_port'] . "/index.php";
if (isset($_SERVER["HTTP_REFERER"]) && !empty($_SERVER["HTTP_REFERER"])) {
   // $redirectURL = $_SERVER["HTTP_REFERER"];
}
header("Location: ". $redirectURL );
exit();
?>
