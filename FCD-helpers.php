<?php
//require('Site.conf');

/* 
 * Role and permission checks, including role-specific convenience functions.
 */

function requireRoleOrLogin( $requiredRole ) {
   global $SITEINFO;
   // bounce to login page IF user lacks the required role for the calling page
   if (!userHasRole( $requiredRole )) {
      $_SESSION['REDIRECT_ON_LOGIN'] = $_SERVER["REQUEST_URI"];
      header("HTTP/1.1 401 Unauthorized");
      header("Location: https://" . $SITEINFO['secure_hostname_and_port'] . "/login.php");
      exit();
   }
}

function userHasRole( $requiredRole ) {
   $userHasRequiredRole = false;
   switch( $requiredRole ) {
      case 'ADMIN':
         if (isset($_SESSION['IS_ADMIN_USER']) && $_SESSION['IS_ADMIN_USER'] == true) {
            $userHasRequiredRole = true;
         } 
         break;

      case 'REVIEWER':
         if (isset($_SESSION['IS_REVIEWER']) && $_SESSION['IS_REVIEWER'] == true) {
            $userHasRequiredRole = true;
         }
         break;

      default:
         die('Please specify ADMIN or REVIEWER roles! (unknown role '.$requiredRole.')');
   }
   return $userHasRequiredRole;
}

function userIsAdmin() {
   return userHasRole( 'ADMIN' );
}

function userIsReviewer() {
   return userHasRole( 'REVIEWER' );
}

function userIsLoggedIn() {
   return userHasRole( 'ADMIN' ) || userHasRole( 'REVIEWER' );
}

/*
 * Cross-platform stub for calling asynchronous operations (command-line stuff)
 */
function execInBackground($cmd) { 
    if (substr(php_uname(), 0, 7) == "Windows"){ 
        pclose(popen("start /B ". $cmd, "r"));  
    } 
    else { 
        exec($cmd . " > /dev/null &");   
    } 
} 

function runSQLScript( $relativePathToScript ) {
   global $SITEINFO;

   // execInBackground("/opt/lampp/bin/mysql --host='127.0.0.1' --user='zzzzzzzz' --password='xxxxxxxxxx' --database='FossilCalibration' --execute='source /opt/lampp/htdocs/fossil-calibration/protected/SQL_TEST.sql'");

   $mysql = $SITEINFO['mysql_exec'];
   $host = $SITEINFO['servername'];
   $dbuser = $SITEINFO['UserName'];
   $dbpass = $SITEINFO['password'];
   $docroot = $SITEINFO['docroot'];
   execInBackground( "$mysql --host='$host' --user='$dbuser' --password='$dbpass' --database='FossilCalibration' --execute='source $docroot$relativePathToScript'" );
}

/* Return a desired property from any array-like objects, or a default if not found.
 * This should generally Do the Right Thing, whether we're working with a new object, 
 * editing a complete existing object, or one that's partially complete.
 */
function testForProp( $data, $property, $default ) {
	if (!is_array($data)) return $default;
	return $data[$property];
}

?>
