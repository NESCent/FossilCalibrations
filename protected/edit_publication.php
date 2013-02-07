<?php 
// open and load site variables
require('../Site.conf');

// secure this page
///require('../secure-page.php');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// stash a nonce (one-time key) to make sure we don't re-submit this form accidentally
$_SESSION['nonce'] = $nonce = md5('salt'.microtime());

// test up front for publication ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$PublicationID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$PublicationID = 0;
	$addOrEdit = 'ADD';
}

$publication_data = null;
$featured_image_data = null;

if ($addOrEdit == 'EDIT') {
	// retrieve the main publication for this publication, if any
	$query="SELECT * FROM publications WHERE PublicationID = '".mysql_real_escape_string($PublicationID)."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$publication_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve its featured image, if any
	$query="SELECT * FROM publication_images WHERE PublicationID = '".mysql_real_escape_string($PublicationID)."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$featured_image_data = mysql_fetch_assoc($result);
	mysql_free_result($result);
}

// Return a desired property from any of the data objects above, or a default if not found.
// This should generally Do the Right Thing, whether we're add a new object, a complete one, 
// or one that's partially complete.
function testForProp( $data, $property, $default ) {
	if (!is_array($data)) return $default;
	return $data[$property];
}

/*
 * Query for controlled lists of misc values
 */

// list of all publication-status values
$query='SELECT * FROM L_PublicationStatus';
$pubstatus_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// Build a complete add/edit form in one page
?>
<script type="text/javascript">
	$(document).ready(function() {
		// TODO
	});
</script>

<form action="update_publication.php" method="post" id="edit-publication" enctype="multipart/form-data">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />
<input type="hidden" name="addOrEdit" value="<?= $addOrEdit; ?>" />
<input type="hidden" id="PublicationID" name="PublicationID" value="<?= $PublicationID; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_publications.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Publication" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new publication" : "Edit an existing publication (id: ".$PublicationID.")" ?> </h1>

<table id="xxxxxx" width="100%" border="0">
  <tr>
    <td width="20%" align="right" valign="top"><b>publication status</b></td>
    <td width="40%" valign="top">&nbsp;
	<select name="PublicationStatus">
	  <?php
		$currentStatus = testForProp($publication_data, 'PublicationStatus', '1');  // default is Private Draft
		while ($row = mysql_fetch_array($pubstatus_list)) {
			$thisStatus = $row['PubStatusID'];
			if ($currentStatus == $thisStatus) {
				echo '<option value="'.$row['PubStatusID'].'" selected="selected">'.$row['PubStatus'].'</option>';
			} else {
				echo '<option value="'.$row['PubStatusID'].'">'.$row['PubStatus'].'</option>';
			}			
		}
	  ?>
	</select>
    </td>
    <td rowspan="5" width="40%" valign="top" align="left" style="padding-left: 10px;">
	<b>featured image</b> &nbsp;&nbsp; <i>(JPEG, ~120px high, ~180px wide)</i>
	<br/>
	<input style="float: right;" type="submit" name="requestedAction" value="delete image" />
	<input type="file" name="FeaturedImage"/>
	<div id="publication-image-preview" style="text-align: center; background-color: #eee; color: #999; margin: 8px 0 0; padding: 4px; height: 120px;">
	    <? if ($featured_image_data == null) { ?>
		<br/>
		<br/>
		<br/>
		<b>no featured image</b>
	    <? } else { 
		$imgCaption = mysql_real_escape_string(testForProp($featured_image_data, 'caption', ''));
	    ?>
		<img src="/publication_image.php?id=<?= $PublicationID ?>" alt="<?= $imgCaption ?>" title="<?= $imgCaption ?>" style="height: 120px;" />
	    <? } ?>
	</div>
    </td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>authors, date</b></td>
    <td>&nbsp; <input type="text" name="ShortForm" id="ShortForm" style="width: 200px;" value="<?= testForProp($publication_data, 'ShortName', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>full citation</b></td>
    <td>&nbsp; <textarea name="FullCite" id="FullCite" rows="3" style="width: 95%; height: 3.5em;" ><?= testForProp($publication_data, 'FullReference', '') ?></textarea></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>doi</b></td>
    <td>&nbsp; <input type="text" name="DOI" id="DOI" style="width: 200px;" value="<?= testForProp($publication_data, 'DOI', '') ?>" ></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>image caption (ALT text)</b></td>
    <td>&nbsp; <input type="text" name="ImageCaption" id="ImageCaption" style="width: 95%;" value="<?= testForProp($featured_image_data, 'caption', '') ?>" ></td>
  </tr>
</table>

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_publications.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" name="requestedAction" value="Save Publication" />
</div>

</form>

<?php 
//open and print page footer template
require('../footer.php');
?>
