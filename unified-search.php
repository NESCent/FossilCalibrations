<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('Header.txt');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

?>
<div class="left-column" style="">
	<!-- faceted search tools -->
	<div id="faceted-search">
		<h3 style="margin-top: 2px;">Recommended views</h3>
		<div style="text-align: center;">
			<select>
				<option>Recently added calibrations</option>
				<option>Calibrations in clade Mammalia</option>
				<option>Calibrations in clade Aves</option>
				<option>Advanced (using filters below)</option>
			</select>
		</div>

		<h3>Advanced search options</h3>
		<dl>
			<dt>Ancestor of <a class="term" href="#">extant (living) species</a></dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 60px; text-align: right;">Species&nbsp;A&nbsp;</td>
    <td><input type="text" name="TaxonA" id="TaxonA" style="width: 92%;"></td>
  </tr>
  <tr>
    <td style="text-align: right;">Species&nbsp;B&nbsp;</td>
    <td><input type="text" name="TaxonB" id="TaxonB" style="width: 92%;"> </td>
  </tr>
  <tr>
    <td style="text-align: right; position: relative; top: -4px; font-size: 0.8em;">(optional)</td>
    <td>&nbsp;</td>
  </tr>
</table>
			</dd>

			<dt>By any <a class="term" href="#">clade</a></dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 60px; text-align: right;">Clade&nbsp;</td>
    <td><input type="text" name="Clade" id="Clade" style="width: 92%;"></td>
<!--
    <td>
	<input type="submit" name="Submit1" id="Submit1" value="Show all within clade"
	       Xonclick="return testForTipTaxon( TODO );">
    </td>
-->
  </tr>
</table>
			</dd>

			<dt>By age (in <a class="term" href="#">Ma</a>)</dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 145px; text-align: right;">Minimum (youngest)&nbsp;</td>
    <td><input type="text" name="TaxonA" id="TaxonA" style="width: 80%;"></td>
  </tr>
  <tr>
    <td style="text-align: right;">Maximum (oldest)&nbsp;</td>
    <td><input type="text" name="TaxonB" id="TaxonB" style="width: 80%;"> </td>
  </tr>
</table>
			</dd>

			<dt>By <a class="term" href="#">geological period</a></dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 145px; text-align: right;">Minimum (youngest)&nbsp;</td>
    <td><input type="text" name="TaxonA" id="TaxonA" style="width: 80%;"></td>
  </tr>
  <tr>
    <td style="text-align: right;">Maximum (oldest)&nbsp;</td>
    <td><input type="text" name="TaxonB" id="TaxonB" style="width: 80%;"> </td>
  </tr>
</table>
			</dd>
			<dt style="height: 0px;">&nbsp;</dt>
<!--
			<dd><input type="submit" value="Update"/></dd>
-->
		</dl>
		<div style="text-align: center; margin: 4px;"><input type="submit" value="Update Results"/></div>
	</div>
	
	<!-- TODO: About Us, Contact Us (links in header?) -->
</div>

<div class="right-column">
	<!-- news -->
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

		<h3 class="contentheading" style="margin-top: 0;">Best Practices</h3>
		<ol class="best-practices">
			<li><a href="#">Cite the <em>original</em> calibration source.</a></li>
			<li><a href="#">Brush and floss daily.</a></li>
			<li><a href="#">Never let them see you sweat.</a></li>
			<li><a href="#">Radiological dating is for amateurs.</a></li>
			<li><a href="#">Never perform with children, animals, or fire.</a></li>
		</div>

		<h3 class="contentheading" style="margin-top: 0;">Collection Statistics</h3>
		<div style="padding-left: 6px;">
			312 submitted calibrations
			<br/>
			298 published calibrations
			<br/>
			96 submitting researchers 
		</div>
	</div>

</div>

<div class="center-column" style="">

<select style="float: right; margin-top: 12px; margin-bottom: -24px;">
	<option>Group by relevance</option>
	<option>Group by relationship</option>
	<option selected="selected">Sort by date added</option>
	<option>Sort by calibrated age</option>
</select>
<h2 class="results-heading" style="clear: both; border-top: none;">Recently added calibrations</h2>
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

<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			\/
			</td>
			<td width="*">
			99% match
			</td>
			<td width="100">
			9&ndash;12 Ma
			</td>
			<td width="120">
			Added Jan 3, 2013
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name">Archosauria</span>
		<span class="citation">&ndash; from Imis, R. 2012.</span>
	</a>
	<br/>
	<div class="optional-thumbnail"><img src="images/archosauria.jpeg" /></div>
	<div class="details">
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result...
		&nbsp;
		<a class="more" href="#">more</a>
	</div>
</div>

<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			\/
			</td>
			<td width="*">
			99% match
			</td>
			<td width="100">
			9&ndash;12 Ma
			</td>
			<td width="120">
			Added Jan 3, 2013
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name">Salviniales</span>
		<span class="citation">&ndash; from Hermsen, E. &amp; Gandolfo, A. 2011.</span>
	</a>
	<br/>
	<!--<div class="optional-thumbnail">{image}</div>-->
	<div class="details">
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result...
		&nbsp;
		<a class="more" href="#">more</a>
	</div>
</div>

<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			\/
			</td>
			<td width="*">
			99% match
			</td>
			<td width="100">
			9&ndash;12 Ma
			</td>
			<td width="120">
			Added Jan 3, 2013
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name">Carnivora</span>
		<span class="citation">&ndash; from Polly, P.D. 2010.</span>
	</a>
	<br/>
	<!--<div class="optional-thumbnail">{image}</div>-->
	<div class="details">
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result.
		Here are some fascinating details about the calibration in this result...
		&nbsp;
		<a class="more" href="#">more</a>
	</div>
</div>

<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			\/
			</td>
			<td width="*">
			99% match
			</td>
			<td width="100">
			9&ndash;12 Ma
			</td>
			<td width="120">
			Added Jan 3, 2013
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name">Insecta</span>
		<span class="citation">&ndash; Ware, J. 2011.</span>
	</a>
	<br/>
	<div class="optional-thumbnail"><img src="images/insecta.jpeg" /></div>
	<div class="details">
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result...
		&nbsp;
		<a class="more" href="#">more</a>
	</div>
</div>

</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('Footer.txt');
?>
