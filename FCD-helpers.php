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

?>
