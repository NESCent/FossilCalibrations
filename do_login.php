<?
/* A simple login script, for admin or reviewer access
 */

// open and load site variables
require('Site.conf');

$username = $_POST['fcd_username'];
$password = $_POST['fcd_password'];

if($username == $SITEINFO['AdminUserName'] && $password == $SITEINFO['AdminUserPassword']) {
    $_SESSION['IS_ADMIN_USER'] = true;
    $_SESSION['IS_REVIEWER'] = true;

} else if($username == $SITEINFO['ReviewerName'] && $password == $SITEINFO['ReviewerPassword']) {
    $_SESSION['IS_ADMIN_USER'] = false;
    $_SESSION['IS_REVIEWER'] = true;

} else {
    // any bad credentials will remove all privileges
    $_SESSION['IS_ADMIN_USER'] = false;
    $_SESSION['IS_REVIEWER'] = false;
}

// either way, bounce to the intended destination URL (or home page by default)

$redirectURL = "http://" . $SITEINFO['hostname_and_port'] . "/index.php";
if (isset($_SESSION['REDIRECT_ON_LOGIN']) && !empty($_SESSION['REDIRECT_ON_LOGIN'])) {
   $redirectURL = $_SESSION['REDIRECT_ON_LOGIN'];
}

header("Location: ". $redirectURL );
exit();
?>
