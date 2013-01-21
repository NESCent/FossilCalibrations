<?php 
// Fetch the current site announcement (if any) and see if this user has already hidden it
$sql = 'SELECT announcement_title AS title, announcement_body AS body FROM site_status LIMIT 1';
if (isset($mysqli)) {
	// this page uses newer mysqli API
	$announcement_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
	$current_announcement = mysqli_fetch_assoc($announcement_results);
} else {
	// this page uses legacy mysql API
	$announcement_results = mysql_query($sql) or die ('Error  in query: '.$sql.'|'. mysql_error());
	$current_announcement = mysql_fetch_assoc($announcement_results);
}
if ($current_announcement['body']) {
  $showMessage = true;
  if (isset($_SESSION['most_recent_hidden_announcement'])) {
    if ($current_announcement['body'] == $_SESSION['most_recent_hidden_announcement'])  {
      $showMessage = false;
    }
  }

  if ($showMessage) { ?>
		<div class="announcement">
		  <strong><?= $current_announcement['title'] ?></strong>
		<?= $current_announcement['body'] ?>
<!--
		  <ul>
		    <li>
			Kristin's <strong>FCD logo</strong> in the site header. (Re)load any page to see three variations.
		    </li>
		    <li>
			Site "<strong>favicon</strong>" based on the black-and-white logo.
		    </li>
		    <li>
			Password-protected <strong>administrative features</strong>, with a single (shared) account. 
		    </li>
		  </ul>
-->
		</div>
<? } 
} ?>
