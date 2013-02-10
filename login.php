<?php
// open and load site variables
require('Site.conf');

// not logged in... force user to HTTPS for this page
if($_SERVER["HTTPS"] != "on") {
  header("HTTP/1.1 301 Moved Permanently");
  header("Location: https://" . $SITEINFO['secure_hostname_and_port'] . $_SERVER["REQUEST_URI"]);
  exit();
}

// open and print header template
require('header.php');
?>

<div class="center-column" style="padding-right: 0;">

   <h3>
   You must login to continue
   </h3>
   <form action="/do_login.php" method="POST">
     <table border="0">
      <tr>
       <td>
	<label for="fcd_username"><b>username</b></label>
       </td>
       <td>
	<input type="text" id="fcd_username" name="fcd_username" value="" />
       </td>
      <tr>
      <tr>
       <td>
	<label for="fcd_password"><b>password</b></label>
       </td>
       <td>
	<input type="password" id="fcd_password" name="fcd_password" value="" />
       </td>
      <tr>
      <tr>
       <td colspan="2" style="padding-top: 8px;">
	<input type="submit" value="Log in" />
       </td>
       <td>
      </tr>
     </table>
   </form>

</div>

<?php 
//open and print page footer template
require('footer.php');
?>
