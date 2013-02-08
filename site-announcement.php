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
$showMessage = false;
if ($current_announcement['body']) {
  $showMessage = true;
  if (isset($_SESSION['most_recent_hidden_announcement'])) {
    if ($current_announcement['body'] == $_SESSION['most_recent_hidden_announcement'])  {
      $showMessage = false;
    }
  }
}
?>
		<div id="site_announcement" class="announcement" style="display: <?= $showMessage ? 'block' : 'none' ?>;">
		  <div id="announcement_title_display" style="font-weight: bold; margin-bottom: 4px;"><?= $current_announcement['title'] ?></div>
		  <div id="announcement_body_display"><?= $current_announcement['body'] ?></div>
		</div>
