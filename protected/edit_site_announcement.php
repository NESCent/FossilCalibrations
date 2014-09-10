<?php 
/* 
 * NOTE that this page does not go to great lengths to protect user input,
 * since the user is already a logged-in administrator.
 */
// open and load site variables
require('../Site.conf');
require('../FCD-helpers.php');

// secure this page
requireRoleOrLogin('ADMIN');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// stash a nonce (one-time key) to make sure we don't re-submit this form accidentally
$_SESSION['nonce'] = $nonce = md5('salt'.microtime());

// retrieve the current site announcement (title and body), if any
$query="SELECT announcement_title, announcement_body FROM site_status";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$announcement_data = mysql_fetch_assoc($result);
mysql_free_result($result);

// Build a complete add/edit form in one page
?>
<script type="text/javascript">
	$(document).ready(function() {
		refreshPreview();

		// bind behavior for live preview
		$('#announcement_title, #announcement_body').unbind('keyup').keyup(refreshPreview);
	});
	function refreshPreview() {
		var newTitle = $.trim( $('#announcement_title').val() );
		var newBody = $.trim( $('#announcement_body').val() );
		if (newBody === '') {
			$('#site_announcement').hide();
			$('#no-visible-announcement').show();
		} else {
			$('#announcement_title_display').html( newTitle  );
			$('#announcement_body_display').html( newBody );
			$('#no-visible-announcement').hide();
			$('#site_announcement').show();
		} 
	}
</script>

<form action="update_site_announcement.php" method="post" id="edit-site-announcement">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_publications.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Clear Announcement" />
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Announcement" />
</div>

<h1>Edit site announcement</h1>
<i>NOTE that any saved, non-empty announcement will appear <b>immediately</b> on the site's home page!</i>

<table width="100%" border="0" style="margin-top: 20px;">
  <tr>
    <td width="100" height="30" align="right" valign="top"><b>title</b> (html)</td>
    <td width="*" valign="top">&nbsp; <input type="text" name="announcement_title" id="announcement_title" style="width: 95%;" value="<?= testForProp($announcement_data, 'announcement_title', '') ?>" ></td>
    <td width="250" valign="top" rowspan="2">
	<b>live preview</b>
	<div class="right-column" style="margin-top: 4px;">
	<div id="no-visible-announcement" style="border: 2px dashed #ccc; padding: 5px 8px; background-color: #eee;">
		No announcement will appear (a non-empty body is required).
	</div>
	<?php require('../site-announcement.php'); ?>
	</div>
    </td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>body</b> (html)</td>
    <td valign="top">&nbsp; <textarea name="announcement_body" id="announcement_body" rows="3" style="width: 95%; height: 30em;" ><?= testForProp($announcement_data, 'announcement_body', '') ?></textarea></td>
  </tr>
</table>

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_publications.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Clear Announcement" />
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Announcement" />
</div>

</form>

<?php 
//open and print page footer template
require('../footer.php');
?>
