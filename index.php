<?php 
// open and load site variables
require('../config.php');

$skipHeaderSearch = true;
// open and print header template
require('header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

?>
<script type="text/javascript">
	$(document).ready(function() {
		
		$('#simple-search-input').autocomplete({
			source: '/autocomplete_all_names.php',
		     /* source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
		     */
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
	             /* response: function(event, ui) {
				// another place to manipulate returned matches
				console.log("RESPONSE > "+ ui.content);
			},
			focus: function(event, ui) {
				console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_FossilSpeciesID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_FossilSpeciesID-display').val('');
					$('#AC_FossilSpeciesID').val('');
					//$('#AC_FossilSpeciesID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_FossilSpeciesID-display').val(ui.item.label);
				$('#AC_FossilSpeciesID').val(ui.item.value);
				//$('#AC_FossilSpeciesID-more-info').html(ui.item.FullReference);
				// TODO: AJAX load of taxon metadata below
				// override normal display (would show numeric ID!)
				return false;
			},
		        close: function(event, ui) {
				console.log("CLOSING VALUE > "+ this.value);
			},
		      */
			minChars: 3
		});
		

		$('#adv-search-link').unbind('click').click(function() {
			// pass the current search terms to the full search page
			$('#simple-search-form').submit();
			return false;
		});
		$('#browse-link').unbind('click').click(function() {
			// pass the current search terms to the full search page
			$('#simple-search-form').attr('action','/Browse.php').submit();
			return false;
		});
	});
</script>

<div class="right-column">
<?php require('site-announcement.php'); ?>

<!-- news
	<div id="site-news">
		<h3 class="contentheading" style="margin-top: 0;">Site News</h3>
		<div class="news-item">
			<div class="dateline">
				Jan 1, 2013
			</div>
			<div class="headline">
				<a href="#">This is a Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
		<div class="news-item">
			<div class="dateline">
				Dec 29, 2012
			</div>
			<div class="headline">
				<a href="#">And This is a Second, Longer Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
		<div class="news-item">
			<div class="dateline">
				Nov 28, 2012
			</div>
			<div class="headline">
				<a href="#">Yet Another Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
	</div>
-->

	<div id="site-news">

		<h3 class="contentheading" style="margin-top: 32px; line-height: 1.25em;">Raising the Standard in Fossil Calibration
		</h3>
		<p>
			The Fossil Calibration Database is a curated
			collection of well-justified calibrations, including many published in the
			journal <a href="http://palaeo-electronica.org/" target="_blank">Palaeontologia Electronica</a>. We also promote best practices for
			<a href="#">justifying fossil calibrations</a> and <a href="#">citing calibrations</a> 
			properly.
		</p>

<?php
/*
		<h3 class="contentheading" style="margin-top: 40px;">Collection Statistics</h3>
		<div style="padding-left: 6px;">
			312 submitted calibrations
			<br/>
			298 published calibrations
			<br/>
			96 submitting researchers 
		</div>
*/
?>

	</div>

</div>

<div class="center-column" style="padding-left: 0;">

 <form id="simple-search-form" action="/search.php"
       style="height: 160px; text-align: center; padding-top: 60px;">
		<div id="simple-search" style="float: none; text-align: center; margin: 3px auto 10px;">
			<!--<input type="submit" style="float: right;" value="Go" />-->
			<input type="image" id="" class="search-button" value="Go" src="/images/search-button.png" title="Show search results" 
			       style="float: right; background-color: #27292B; padding: 2px; border-radius: 6px;" />
			<input id="simple-search-input" name="SimpleSearch" type="text" placeholder="Search by author, publication, species, etc." value="" 
			       style="width: 360px; font-size: 120%; padding: 2px;" />
		</div>
		<p>
			<a id="browse-link" href="/Browse.php">Browse calibrations</a> &nbsp;|&nbsp; <a id="adv-search-link" href="/search.php">Advanced search</a> <!-- &nbsp;|&nbsp; <a href="#">Example searches</a> -->
		</p>
 </form>

<!--<h2 class="results-heading" style="clear: both; border-top: none;">Recently added calibrations</h2>-->
<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
<a style="float: right; Xtext-decoration: none; font-size: 0.8em; font-weight: normal;" href="/search.php?SortResultsBy=DATE_ADDED_DESC">Show more recent additions</a>
Recently added calibrations
</h3>
<!--
<div style="text-align: center;">
	<select style="margin: 3px auto;">
		<option>Group by relevance</option>
		<option>Group by phylogenetic relationship</option>
		<option selected="selected">Sort by date added (newest first)</option>
		<option>Sort by date added (oldest first)</option>
	</select>
</div>
-->

<div class="featured-calibrations">
<?php 
// list the three most recent publications (firstc calibration from each)
// TODO: Allow admins to explicitly mark calibrations as feature-worthy?
$featuredPos = 0;

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

$query='SELECT DISTINCT C . *, img.image, img.caption AS image_caption
	FROM (
		SELECT CF.CalibrationID, V . *
		FROM View_Fossils V
		JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
	)
        AS J
	JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID
	LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID'.
	// non-admin users should only see *Published* calibrations
	((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? '' :  
         ' WHERE J.CalibrationID IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)'
	)
     .' ORDER BY DateCreated DESC
	LIMIT 3';
$calibration_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

// mysql_num_rows($calibration_list) 
while ($row = mysql_fetch_array($calibration_list)) {
	$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $row['CalibrationID'];
	 ?>
	<div class="search-result" style="">
		<table class="qualifiers" border="0" Xstyle="width: 120px; float: right;">
			<tr>
				<td width="120">
				<!--Added Dec 28, 2012-->
				Added <?= date("M d, Y", strtotime($row['DateCreated'])) ?>
				</td>
			</tr>
		</table>
		<a class="calibration-link" href="<?= $calibrationDisplayURL ?>">
			<span class="name"><?= $row['NodeName'] ?></span>
			<span class="citation">&ndash; from <?= $row['ShortName'] ?></span>
		</a>
		<? // if there's an image mapped to this publication, show it
		   if ($row['image']) { ?>
		<div class="optional-thumbnail" style="height: 60px;">
		    <a href="<?= $calibrationDisplayURL ?>">
			<img src="/publication_image.php?id=<?= $row['PublicationID'] ?>" style="height: 60px;"
			alt="<?= $row['image_caption'] ?>" title="<?= $row['image_caption'] ?>"
			/></a>
		</div>
		<? } ?>
		<div class="details">
			<?= $row['FullReference'] ?>
			&nbsp;
			<a class="more" style="display: block; text-align: right;" href="<?= $calibrationDisplayURL ?>">more &raquo;</a>
		</div>
	</div>
	<?
	$featuredPos++;
}

// fill any remaining slots with a placeholder
for (;$featuredPos < 3; $featuredPos++) { ?>
	<div class="search-result">
		<div class="placeholder">
		&nbsp;
		</div>
	</div>
<? } ?>

</div><!-- END of .featured-calibrations -->


</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('footer.php');
?>
