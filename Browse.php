<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');

// read view options from query-string (or set to defaults)
$lineage = isset($_GET['lineage']) ? $_GET['lineage'] : 'full';  // full | sparse
$members = isset($_GET['members']) ? $_GET['members'] : 'full';  // full | sparse 
$levels = isset($_GET['levels']) ? $_GET['levels'] : '1';  // 1 | 2 | 3 | 4 | 5 | all

// fetch the multitree ID (if any) for the specified node (source+ID, eg "NCBI:4321"
// NOTE that we'll query on the multitree ID, but it never appears to the user or in the URL
$defaultNodeSpec = 'NCBI:1';
if (isset($_GET['node'])) {
	$nodeValues = $_GET['node'];
} else {
	// if no node-spec was submitted, default to (NCBI root node "Life")
	$nodeValues = $defaultNodeSpec;
}
if (empty($nodeValues) || !strpos($nodeValues, ':')) {
	// if invalid node-spec was submitted, default to (NCBI root node "Life")
	$nodeValues = $defaultNodeSpec;
}
list($nodeSource, $nodeSourceID) = explode(':', $nodeValues);
if (empty($nodeSource) || empty($nodeSourceID)) {
	// once again, use default if fields are missing
	$nodeValues = $defaultNodeSpec;
	list($nodeSource, $nodeSourceID) = explode(':', $nodeValues);
}

// connect to mySQL server and select the Fossil Calibration database
// NOTE that to use stored procedures and functions in MySQL, the newer mysqli API is recommended.
///$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
///mysql_select_db('FossilCalibration') or die ('Unable to select database!');
$mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');

// convert this to a multitree node
$sql = 'SELECT getMultitreeNodeID( "'. $nodeSource .'", '. $nodeSourceID .') AS mID';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
$row = mysqli_fetch_array($results);
$nodeMultitreeID = $row['mID'];
//var_dump($row);


/*
 * fetch information on the current node's ancestor path (NCBI only)
 */
$sql = 'CALL getAllAncestors('. $nodeMultitreeID .', "TEMP_ancestors", "NCBI" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'CALL getFullNodeInfo("TEMP_ancestors", "TEMP_ancestors_info" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'SELECT * FROM TEMP_ancestors_info WHERE source_tree = "NCBI"';
$ancestors_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//var_dump($ancestors_info_results);

// gather all results into an array 
$ancestors = array();
while ($row = mysqli_fetch_array($ancestors_info_results)) {
	$ancestors[]=$row;
}

// grab target node information from tip of ancestors array
if (count($ancestors) > 0) {
	$targetNodeInfo = $ancestors[ count($ancestors) - 1 ];
} else {
	$targetNodeInfo = $ancestors[ 0 ];
}


/*
 * fetch information on the current node's descendants in the NCBI tree. Limit
 * this to one or a few levels, lest we choke on nodes close to the root.
 */
$sql = 'CALL getCladeFromNode('. $nodeMultitreeID .', "TEMP_descendants", "NCBI", '. (($levels == 'all') ? 'NULL' : $levels) .' )';

//mysqli_free_result($results);
///set_time_limit( 0 ); // kill the SQL time limit!?
$results = mysqli_query($mysqli, $sql, MYSQLI_STORE_RESULT) or die ('Error in sql: '.$sql.'|'. mysql_error());
//while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_free_result($results);

$sql = 'CALL getFullNodeInfo("TEMP_descendants", "TEMP_descendants_info" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'SELECT * FROM TEMP_descendants_info WHERE source_tree = "NCBI" ORDER BY parent_multitree_node_id ASC';
$descendants_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
//var_dump($descendants_info_results);

// gather all results into an array
$descendants = array();
while ($row = mysqli_fetch_array($descendants_info_results)) {
	$descendants[]=$row;
}
?>

<div class="right-column">
<?php require('site-announcement.php'); ?>
	<div id="site-news">

		<h3 class="contentheading" style="margin-top: 32px; line-height: 1.25em;">Raising the Standard in Fossil Calibration
		</h3>
		<p>
			The Fossil Calibration Database is a curated
			collection of well-justified calibrations, including many published in the
			journal <a href="#">Palaeontologia Electronica</a>. We also promote best practices for
			<a href="#">justifying fossil calibrations</a> and <a href="#">citing calibrations</a> 
			properly.
		</p>

	</div>

</div>

<div class="center-column" style="padding-left: 0;">

<?php
    $calibrationsInThisTaxon = getDirectCalibrationsInCladeRoot($nodeMultitreeID);
?>
<h3 class="contentheading">Lineage <a id="lineage-toggle" href="#" title="Click to use abbreviated lineaage">(full)</a></h3>
<div class="ancestor-path">
	<? $nthAncestor = 0;
	   foreach ($ancestors as $row) {
		if ($row['multitree_node_id'] == $nodeMultitreeID) continue; // don't include the target node!
	 /* ?><br/><pre><? var_dump($row); ?></pre><? */
		$nthAncestor++;
		// show each ancestor as a breadcrumb/link in chain of ancestry ?>
		
		<? if ($nthAncestor > 1) { ?><span class="path-divider">&raquo;</span><? } ?>
		<a href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
		<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->

	<?  } ?>
</div><!-- end of .ancestor-path -->

<p><h1><!-- Browsing the tree, at node -->
<?= htmlspecialchars($targetNodeInfo['uniquename']) ?> <span style="color: #ccc;">(<?= $nodeSource ?>:<?= $nodeSourceID ?>, mID:<?= $nodeMultitreeID ?>)</span></h1></p>

<h3 class="contentheading">Directly related calibrations</h3>
<p>
	<? switch(count($calibrationsInThisTaxon)) {
		case 0: ?>
	There are no calibrations directly related to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>.
			<? break;

		case 1: ?>
	There is 1 calibration directly related to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>:
			<? break;

		default: ?>
	There are <?= count($calibrationsInThisTaxon) ?> calibrations directly related to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>:
			<? break;
	} ?>
</p>

<? if (count($calibrationsInThisTaxon) > 0) { ?>
<div class="featured-calibrations">
<?php 
// list all calibration in this taxon
$featuredPos = 0;

// connect to mySQL server and select the Fossil Calibration database
$query='SELECT DISTINCT C . *, img.image, img.caption AS image_caption
	FROM (
		SELECT CF.CalibrationID, V . *
		FROM View_Fossils V
		JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
	) AS J
	JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID 
				 AND C.CalibrationID IN ('. implode(", ", $calibrationsInThisTaxon) .')
	LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
	ORDER BY DateCreated DESC';
$calibration_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysql_error());	


// mysql_num_rows($calibration_list) 
while ($row = mysqli_fetch_array($calibration_list)) {
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
		<div class="placeholder" style="background-color: #fff;">
		&nbsp;
		</div>
	</div>
<? } ?>

</div><!-- END of .featured-calibrations -->
<? } ?>

<h3 class="contentheading">Clade Members 
	<a id="full-sparse-toggle" href="#" title="Click to show only clade members with calibrations">(full tree)</a>
	&nbsp;
	- showing 
	&nbsp;
	<a class="nlevel-option <?= ($levels == '1') ? 'selected' : '' ?>" href="#TODO" title="Click to show first-level clade members only">1</a>
	&nbsp;
	<a class="nlevel-option <?= ($levels == '2') ? 'selected' : '' ?>" href="#TODO" title="Click to show first- and second-level clade members">2</a>
<!-- 3 levels is pretty slow in a crowded part of the tree...
	&nbsp;
	<a class="nlevel-option <?= ($levels == '3') ? 'selected' : '' ?>" href="#TODO" title="Click to show three levels of clade members">3</a>
	&nbsp;
	<a class="nlevel-option <?= ($levels == '4') ? 'selected' : '' ?>" href="#TODO" title="Click to show four levels of clade members">4</a>
	&nbsp;
	<a class="nlevel-option <?= ($levels == '5') ? 'selected' : '' ?>" href="#TODO" title="Click to show five levels of clade members">5</a>
	&nbsp;
	<a class="nlevel-option <?= ($levels == 'all') ? 'selected' : '' ?>" href="#TODO" title="Click to show all clade members">all</a>
-->
	&nbsp;
	level<?= ($levels != '1') ? 's' : '' ?>
</h3>
<p>Note that the number of calibrations shown for a node below may not match the total number for its clade members. This is due to differences between phylogeny and the NCBI taxonomy.</p>
<ul class="child-listing">
<?  foreach ($descendants as $row) {
	if ($row['multitree_node_id'] == $nodeMultitreeID) continue; // else root will appear as its own child
	//if ($row['query_depth'] != 1) continue; // show immediate children only!
	// try a more specific count
	$calibrationsInThisClade = getAllCalibrationsInClade($row['multitree_node_id']);
?>
    <li class="<?= (count($calibrationsInThisClade) > 0) ? 'has-calibrations' : 'no-calibrations' ?> node-id-<?= $row['multitree_node_id'] ?> parent-id-<?= $row['parent_multitree_node_id'] ?>">	
	<a href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
        <? if (count($calibrationsInThisClade) > 0) { ?> 
		&nbsp; <a target="_blank" title="Click to see calibrations"
			  href="/search.php?SortResultsBy=DATE_ADDED_DESC&SimpleSearch=&HiddenFilters[]=FilterByTipTaxa&BlockedFilters[]=FilterByTipTaxa&TaxonA=&TaxonB=&FilterByClade=<?= htmlspecialchars($row['uniquename']) ?>&HiddenFilters[]=FilterByAge&MinAge=&MaxAge=&HiddenFilters[]=FilterByGeologicalTime&FilterByGeologicalTime=">(<span class="calibration-count"><?= count($calibrationsInThisClade) ?></span>)</a>
		&nbsp; <? foreach($calibrationsInThisClade as $calID) { ?> &nbsp; <a style="font-weight: normal; color: #ccc;" 
			href="/Show_Calibration.php?CalibrationID=<?= $calID ?>"><?= $calID ?></a><? } ?>
	<? } ?>
	<!-- <em>depth=<?= $row['query_depth'] ?></em> -->
	<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->
    </li>
<?  } ?>
</ul><!-- end of .child-listing -->


</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<script type="text/javascript">
	$(document).ready(function() {
		// clean up multi-level tree (move children into sub-lists, indent)
		var $taxa = $('ul.child-listing li');
		$taxa.each(function() {
			var $movingItem = $(this);
			var itsClassNames = $movingItem.attr('class').split(' ');
			var parentMarkerClass = null;
			for (var i = 0; i < itsClassNames.length; i++) {
				if (itsClassNames[i].indexOf('parent-id-') === 0) {
					parentMarkerClass = itsClassNames[i].replace('parent-id-', 'node-id-');
				}
			}
			if (!parentMarkerClass) {
				console.log("WARNING: no node-id-FOO found for this item!");
				return;
			}
			var $parentItem = $taxa.filter('.'+ parentMarkerClass);
			if ($parentItem.length === 1) {
				// if parent already has a sub-list, add to it; else create one
				var $subList = $parentItem.find('ul');
				if ($subList.length === 0) {
					$subList = $parentItem.append('<ul></ul>');
				}
				$subList.append($movingItem);
			} else {
				console.log("WARNING: expected one parent item, found "+ $parentItem.length +" .. possibly off-screen root?");
				return;
			}
		});
		
		// view options for browsing UI
		$('#lineage-toggle').unbind('click').click(function() {
			var $clicked = $(this);
			var $optionalAncestors = $('.ancestor-path a:contains(>)'); 
				// TODO: replace this with something real!
			if ($clicked.text().indexOf('full') > -1) {
				// switch to sparse tree
				$optionalAncestors.hide();
				$optionalAncestors.prev('.path-divider').hide();
				$clicked.text('(abbreviated)');
				$clicked.attr('title', "Click to show full lineage");
			} else {
				// switch to full tree
				$optionalAncestors.show();
				$optionalAncestors.prev('.path-divider').show();
				$clicked.text('(full)');
				$clicked.attr('title', "Click to show abbreviated lineage");
			}
			return false;
		});
		$('#full-sparse-toggle').unbind('click').click(function() {
			var $clicked = $(this);
			if ($clicked.text().indexOf('full') > -1) {
				// switch to sparse tree
				$('li.no-calibrations').hide();
				$clicked.text('(sparse tree)');
				$clicked.attr('title', "Click to show all clade members");
			} else {
				// switch to full tree
				$('li.no-calibrations').show();
				$clicked.text('(full tree)');
				$clicked.attr('title', "Click to show only clade members with calibrations");
			}
			return false;
		});
		$('.nlevel-option').unbind('click').click(function() {
			var $clicked = $(this);
			if ($clicked.is('.selected')) {
				// already selected, don't reload the page
				return false;
			}
			var url = window.location.href;
			url = url.split('#')[0];  // remove any fragment
			if (url.indexOf('levels=') === -1) {
				url += ('&levels=' + $clicked.text());
			} else {
				url = url.replace(/&levels=(all|\d?)/, '&levels='+ $clicked.text());
			}
			window.location.href = url;
			return false;
		});
	});
</script>
<?php 
//open and print page footer template
require('footer.php');
?>
