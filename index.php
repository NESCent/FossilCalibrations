<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

?>
<div style="position: absolute; top: 0; right: 0; width: 50%; height: 40px; background-color: #27292B;">&nbsp;</div>

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

 <div style="height: 160px; text-align: center; padding-top: 60px;">
		<div id="simple-search" style="float: none; text-align: center; margin: 3px auto 10px;">
			<input type="submit" style="float: right;" value="Go" />
			<input type="text" style="width: 80%;" value="Search by author, clade, publication, species,etc." />
		</div>
		<p>
			<a href="/Browse.php">Browse calibrations</a> &nbsp;|&nbsp; <a href="#">Advanced search</a> &nbsp;|&nbsp; <a href="#">Example searches</a>
		</p>
 </div>

<!--<h2 class="results-heading" style="clear: both; border-top: none;">Recently added calibrations</h2>-->
<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">
<a style="float: right; Xtext-decoration: none; font-size: 0.8em; font-weight: normal;" href="/advanced-search.php">Show more recent additions</a>
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
	) AS J
	JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID
	LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
	ORDER BY DateCreated DESC
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
