<?php 
// open and load site variables
require('../config.php');

$skipHeaderSearch = true;
// open and print header template
require('header.php');

// read view options from query-string (or set to defaults)
$lineage = isset($_GET['lineage']) ? $_GET['lineage'] : 'full';  // full | sparse
$members = isset($_GET['members']) ? $_GET['members'] : 'sparse';  // full | sparse 
$levels = isset($_GET['levels']) ? $_GET['levels'] : '2';  // 1 | 2 | 3 | 4 | 5 | all

// connect to mySQL server and select the Fossil Calibration database
// NOTE that to use stored procedures and functions in MySQL, the newer mysqli API is recommended.
///$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
///mysql_select_db('FossilCalibration') or die ('Unable to select database!');
$mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');

// fetch the multitree ID (if any) for the specified node (source+ID, eg "NCBI:4321"
// NOTE that we'll query on the multitree ID, but it never appears to the user or in the URL
$defaultNodeSpec = 'NCBI:1';
// Note that we might be coming here from the Home or Search pages, so a valid
// clade/taxon name could be in any of several fields. We'll check each in turn
$incomingCladeName = '';
if (isset($_GET['node'])) {
	// we have an explicit target node (probably browsing the tree already)
	$nodeValues = $_GET['node'];
} else {
	// try a series of possible incoming fields, searching for a valid clade name
	$incomingFields = array('SimpleSearch', 'FilterByClade', 'TaxonA', 'TaxonB');
	foreach ($incomingFields as $testField) {  
		if (!isset($_GET[ $testField ])) {
			// no such incoming field
			continue;
		}
		$testValue = $_GET[ $testField ];
		if (empty($testValue)) {
			// skip empty fields
			continue;
		}
		if (isset($_GET['BlockedFilters']) && in_array($testField, $_GET['BlockedFilters'])) {
			// this field was inactive in the search page
			continue;
		}
		if (isset($_GET['HiddenFilters']) && in_array($testField, $_GET['HiddenFilters'])) {
			// this field was inactive in the search page
			continue;
		}
		$multitree_id = nameToMultitreeID($_GET[ $testField ]);
		if ($multitree_id) {
			$nodeValues = 'mID:'.$multitree_id;
			$incomingCladeName = $_GET[ $testField ];
			break;
		}
	}
}
if (empty($nodeValues) || !strpos($nodeValues, ':')) {
	// if invalid node-spec was submitted, default to (NCBI root node "Life")
	$nodeValues = $defaultNodeSpec;
}
list($nodeSource, $nodeSourceID) = explode(':', $nodeValues);

// check that nodeSourceID is an integer.
if(!preg_match('/^-?\d+$/', $nodeSourceID)) {
  // Invalid input in nodeSourceID
  $nodeSourceID = '';
}

if (empty($nodeSource) || empty($nodeSourceID)) {
	// once again, use default if fields are missing
	$nodeValues = $defaultNodeSpec;
	list($nodeSource, $nodeSourceID) = explode(':', $nodeValues);
}

// escape values
$nodeSource = mysqli_real_escape_string($mysqli, $nodeSource);
$nodeSourceID = mysqli_real_escape_string($mysqli, $nodeSourceID);

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

$sql = 'SELECT * FROM TEMP_ancestors_info 
	WHERE source_tree = "NCBI"
	  AND multitree_node_id IN (SELECT multitree_node_id FROM calibration_browsing_tree)';
$ancestors_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//var_dump($ancestors_info_results);

// gather all results into an array 
$ancestors = array();
while ($row = mysqli_fetch_array($ancestors_info_results)) {
	$ancestors[]=$row;
}

// Grab target node information from tip of ancestors array.
switch(count($ancestors)) {
	case 0:
		break;
	case 1:
		$targetNodeInfo = $ancestors[ 0 ];
		break;
	default:  // multiple (typical) ancestors
		$targetNodeInfo = $ancestors[ count($ancestors) - 1 ];
		break;
}

/*
 * NOTE that if we've come here from a simple search of an un-interesting
 * taxon, we were just shifted to the nearest interesting ancestor!
 * Make sure to update vars so we get the right descendants below..
 *
 * TODO: If the un-interesting taxon was marked to always appear, stay there!?
 */
$shiftedToInterestingAncestor = false;
if ($nodeMultitreeID != $targetNodeInfo['multitree_node_id']) {
	$nodeMultitreeID = $targetNodeInfo['multitree_node_id'];
	$shiftedToInterestingAncestor = true;
}

/*
 * Fetch information on the current node's nearest "interesting" descendants in the NCBI tree. This means
 * the nearest descendant nodes that include either:
 * 	- one or more directly-related calibrations, or
 *  	- two or more children with calibrations in their clades
 *      - special marking as an always-visible NCBI taxon
 *
 * Rather than trying to calculate this on-the-fly, use the "pre-baked" table 'calibration_browsing_tree'
 */

$sql = '
CREATE TEMPORARY TABLE TEMP_descendants ENGINE=memory AS (
  SELECT DISTINCT *, 
	 multitree_node_id AS node_id, 
	 parent_multitree_node_id AS parent_node_id, 
	 0 AS depth
  FROM calibration_browsing_tree WHERE parent_multitree_node_id = '. $nodeMultitreeID .' 
  ORDER BY is_immediate_NCBI_child
)';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish

$sql = 'SELECT * FROM TEMP_descendants';
$descendants_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());

// gather all results into an array
$descendants = array();
while ($row = mysqli_fetch_array($descendants_results)) {
	$descendants[]=$row;
}

$sql = 'CALL getFullNodeInfo("TEMP_descendants", "TEMP_descendants_info" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'SELECT * FROM TEMP_descendants_info';
$descendants_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());

// gather all results into an array
$descendants_info = array();
while ($row = mysqli_fetch_array($descendants_info_results)) {
	$descendants_info[]=$row;
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
    $calibrationsInCustomChildNodes = getCalibrationsInCustomChildNodes($nodeMultitreeID);
?>
<!--
<h3 class="contentheading">Lineage 
	<a id="lineage-toggle" href="#" title="">&nbsp;</a>
</h3>
-->
<div id="browse-header" style="">
    <form id="browse-form" action="Browse.php">
	<div class="title-and-alt-nav">
		<strong>Browse the NCBI taxonomy</strong> &mdash; you can also <a id="adv-search-link" href="/search.php">search for calibrations in the database</a>
	</div>
	<input id="simple-search-input" name="SimpleSearch" type="text" style="width: 420px; padding: 2px;" placeholder="Enter a starting clade or taxon" value="<?= htmlspecialchars($incomingCladeName) ?>"/>
	<input type="submit" style="" value="Browse" />
    </form id="simple-search-form">
</div>
<div class="ancestor-path">
	<strong>Lineage</strong>: 
<?
    if (count($ancestors) < 2) { 
	// disregard the target node, which is always here
?>
	<i>This node has no ancestors.</i>
<?
    } else {
	 $nthAncestor = 0;
	 foreach ($ancestors as $row) {
	 /* ?><br/><pre><? var_dump($row); ?></pre><? */
		$nthAncestor++;
		// show each ancestor as a breadcrumb/link in chain of ancestry ?>
		<? if ($nthAncestor > 1) { ?><span class="path-divider">&raquo;</span><? } ?>
		<? if ($row['multitree_node_id'] == $nodeMultitreeID) { 
			// don't link to the target node (we're already there) ?>
			<?= htmlspecialchars($row['uniquename']) ?>
		<? } else { 
			// all proper ancestors should be links ?>
			<a title="Browse to ancestor clade" href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
		<? } ?>
		<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->

      <? }
    } ?>
</div><!-- end of .ancestor-path -->

<? if ($shiftedToInterestingAncestor) { ?>
	<p style="color: #c44; font-style: italic;">There were no calibrations found in your requested clade<?= isset($_GET[ 'SimpleSearch' ]) ? ' <strong>'.$_GET['SimpleSearch'].'</strong>' : '' ?>. This is the nearest enclosing clade with calibrations.</p>
<? } ?>

<p>
<h1><!-- Browsing the tree, at node -->
<?= htmlspecialchars($targetNodeInfo['uniquename']) ?> <span style="display: none;">(<?= $nodeSource ?>:<?= $nodeSourceID ?>, mID:<?= $nodeMultitreeID ?>)</span></h1></p>

<!--
<h3 class="contentheading">Directly related calibrations</h3>
-->
<p style="margin-bottom: 6px;">
	<? switch(count($calibrationsInThisTaxon)) {
		case 0: ?>
	<i>There are no calibrations directly attached to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>.</i>
			<? break;

		case 1: ?>
	There is 1 calibration directly attached to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>:
			<? break;

		default: ?>
	There are <?= count($calibrationsInThisTaxon) ?> calibrations directly attached to <strong><?= htmlspecialchars($targetNodeInfo['uniquename']) ?></strong>:
			<? break;
	} ?>
</p>

<? if (count($calibrationsInThisTaxon) > 0) { ?>
<div class="listed-calibrations">
<?php 

// loop through and escape each value in $calibrationsInThisTaxon
foreach ($calibrationsInThisTaxon as &$untrustedVal) {  
	// NOTE that we grab each result by REFERENCE, so we can modify it in place
	$untrustedVal = mysqli_real_escape_string($mysqli, $untrustedVal);
}

// list all calibration in this taxon
$featuredPos = 0;

// fetch all related calibrations
// TODO: Simplify this query if all we need is node name and display URL!
$query='SELECT DISTINCT TRIM(C.NodeName) AS NodeName, TRIM(C.ShortName) AS ShortName, C.*, img.image, img.caption AS image_caption, ht.DisplayOrder AS ht_display_order
	FROM (
		SELECT CF.CalibrationID, V . *
		FROM View_Fossils V
		JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
	) AS J
	JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID 
				 AND C.CalibrationID IN ('. implode(", ", $calibrationsInThisTaxon) .')
	LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
	LEFT JOIN L_HigherTaxa ht ON ht.HigherTaxon = C.HigherTaxon
	ORDER BY ht_display_order, NodeName, ShortName';
$calibration_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysql_error());	


// mysql_num_rows($calibration_list) 
while ($row = mysqli_fetch_array($calibration_list)) {
	$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $row['CalibrationID'];
	 ?>
	<a href="<?= $calibrationDisplayURL ?>" class="matches-<?= $row['CalibrationID'] ?>">
		<?= $row['NodeName'] ?>
		<span class="citation">&ndash; <?= $row['ShortName'] ?></span>
	</a>
<? } ?>

</div><!-- END of .listed-calibrations -->
<? } ?>

<h3 class="contentheading" style="clear: left; margin-top: 0.8em;">Calibrations within clade members
<!--
	<a id="full-sparse-toggle" href="#" title="">&nbsp;</a>
	&nbsp;&mdash;&nbsp;
	showing 
	&nbsp;
	<a class="nlevel-option <?= ($levels == '1') ? 'selected' : '' ?>" href="/Browse.php?node=<?= $nodeSource ?>:<?= $nodeSourceID ?>" title="Click to show first-level clade members only">1</a>
	&nbsp;
	<a class="nlevel-option <?= ($levels == '2') ? 'selected' : '' ?>" href="/Browse.php?node=<?= $nodeSource ?>:<?= $nodeSourceID ?>" title="Click to show first- and second-level clade members">2</a>
	&nbsp;
	level<?= ($levels != '1') ? 's' : '' ?>
-->
</h3>
<!--
<p>Note that the number of calibrations shown for a node below may not match the total number for its clade members. This is due to differences between phylogeny and the NCBI taxonomy.</p>
-->
<ul class="child-listing" style="display: none;">
<?  if (((count($descendants) == 0) ||
	 (count($descendants) == 1 && $descendants[0]['multitree_node_id'] == $nodeMultitreeID))
        && (count($calibrationsInCustomChildNodes) == 0)) { ?>
	<li class="" style="font-style: italic;">There are no more calibrations within this clade.</li>
<?  } else { /* ?>
	<li class="" style="font-style: italic;">There are <?= count($descendants) ?> descendants in this clade.</li>
	<li class="" style="font-style: italic;">The target's multitree node ID is <?= $nodeMultitreeID ?>.</li>
	<li class="" style="font-style: italic;">The first descendant's multitree node ID is <?= $descendants[0]['multitree_node_id'] ?>.</li>
<? */ }
    if (count($calibrationsInCustomChildNodes) > 0) {

	// loop through and escape each value
	foreach ($calibrationsInCustomChildNodes as &$untrustedVal) {  
		// NOTE that we grab each result by REFERENCE, so we can modify it in place
		$untrustedVal = mysqli_real_escape_string($mysqli, $untrustedVal);
	}

	// fetch details on these calibrations
	$query='SELECT DISTINCT TRIM(C.NodeName) AS NodeName, TRIM(C.ShortName) AS ShortName, C.*, img.image, img.caption AS image_caption, ht.DisplayOrder AS ht_display_order
		FROM (
			SELECT CF.CalibrationID, V . *
			FROM View_Fossils V
			JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
		) AS J
		JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID 
					 AND C.CalibrationID IN ('. implode(", ", $calibrationsInCustomChildNodes) .')
		LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
		LEFT JOIN L_HigherTaxa ht ON ht.HigherTaxon = C.HigherTaxon
		ORDER BY ht_display_order, NodeName, ShortName';
	$calibration_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysql_error());	

	// "wrap" each of these calibrations in a custom node of the same name
	while ($row = mysqli_fetch_array($calibration_list)) {
?>
	    <li class="has-calibrations immediate-child">	
		<span title="This calibrated node is not part of the NCBI taxonomy" style="font-style: italic;"><?= htmlspecialchars($row['NodeName']) ?></span>
		<span class="discreet" style="font-weight: normal;">&mdash; (<span class="calibration-count" style="color: #333;">1</span>)</span> 

		<div class="listed-calibrations">
		     <? // just one calibration in each custom node (for now)
			$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $row['CalibrationID'];
			 ?>
			<a href="<?= $calibrationDisplayURL ?>" class="matches-<?= $row['CalibrationID'] ?>">
				<?= $row['NodeName'] ?>
				<span class="citation">&ndash; <?= $row['ShortName'] ?></span>
			</a>
		</div>
	    </li>
<?	 }
    }


    foreach ($descendants as $row) {
	// $row is a record from calibration_browsing_tree
	if ($row['multitree_node_id'] == $nodeMultitreeID) continue; // else root will appear as its own child

	// fetch additional node information from getFullNodeInfo
	$query="SELECT * FROM TEMP_descendants_info 
                  WHERE source_tree = 'NCBI' AND multitree_node_id = ". mysqli_real_escape_string($mysqli, $row['multitree_node_id']).
	((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? '' :  
	       "  -- AND calibration_id IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)"
	);

	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	$more_info = mysqli_fetch_array($result);

	//if ($row['query_depth'] != 1) continue; // show immediate children only!
	// try a more specific count
	$calibrationsInThisClade = null; // TODO: remove this?
	$calibrationsInThisClade = getAllCalibrationsInClade($more_info['multitree_node_id']); // TODO: remove this?
	$directlyAssociatedCalibrations = getDirectCalibrationsInCladeRoot($row['multitree_node_id']);
	$allCalibrationsDirectlyAssociated = count($directlyAssociatedCalibrations) == count($calibrationsInThisClade);
	$isImmediateChildNode = $row['is_immediate_NCBI_child'];

// diagnostic information for each descendant clade
/* ?>
<!-- 
	<hr />
	<li class="" style="font-style: italic;"><b><?= htmlspecialchars($more_info['uniquename']) ?> - <?= $more_info['multitree_node_id'] ?></b></li>
	<li class="" style="font-style: italic;">$calibrationsInThisClade: <? print_r($calibrationsInThisClade) ?></li>
	<li class="" style="font-style: italic;">$directlyAssociatedCalibrations: <? print_r($directlyAssociatedCalibrations) ?>.</li>
	<li class="" style="font-style: italic;">$allCalibrationsDirectlyAssociated: <?= $allCalibrationsDirectlyAssociated ?>.</li>
	<li class="" style="font-style: italic;">$isImmediateChildNode: <?= $isImmediateChildNode ?></li>
-->
<? */

	// NOTE that a "landmark" taxon might not have any calibrations inside (show it anyway?)
	$calibration_list2 = null;
	if (count($calibrationsInThisClade) > 0) {
		// fetch all related calibrations

		// loop through and escape each value
		foreach ($calibrationsInThisClade as &$untrustedVal) {  
			// NOTE that we grab each result by REFERENCE, so we can modify it in place
			$untrustedVal = mysqli_real_escape_string($mysqli, $untrustedVal);
		}

		// TODO: Simplify this query if all we need is node name and display URL!
		$query='SELECT DISTINCT TRIM(C.NodeName) AS NodeName, TRIM(C.ShortName) AS ShortName, C.*, img.image, img.caption AS image_caption, ht.DisplayOrder AS ht_display_order
			FROM (
				SELECT CF.CalibrationID, V . *
				FROM View_Fossils V
				JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
			) AS J
			RIGHT JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID 
						 AND C.CalibrationID IN ('. implode(", ", $calibrationsInThisClade) .')
			LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
			LEFT JOIN L_HigherTaxa ht ON ht.HigherTaxon = C.HigherTaxon
			WHERE C.CalibrationID IN ('. implode(", ", $calibrationsInThisClade) .')
			ORDER BY ht_display_order, NodeName, ShortName';
		$calibration_list2=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysql_error());	
	}

/*
<pre>$query:
<? print_r($query) ?>
</pre>
*/
?>

    <li class="<?= (count($calibrationsInThisClade) > 0) ? 'has-calibrations' : 'no-calibrations' ?> node-id-<?= $row['multitree_node_id'] ?> parent-id-<?= $row['parent_multitree_node_id'] ?> <?= $isImmediateChildNode ? 'immediate-child' : 'distant-descendant' ?>">	

	<? if (!$isImmediateChildNode) { ?> <span class="discreet">&hellip;</span> <? } ?>
	<? if ($allCalibrationsDirectlyAssociated) {
		// all calibrations are directly associated with this member  ?>
		<span title="There are no clade members with calibrations"><?= htmlspecialchars($more_info['uniquename']) ?></span>
	<? } else { 
		// some descendants have their own calibrations  ?>
		<a title="Browse to clade members with calibrations" class="node-link" href="/Browse.php?node=<?= $more_info['source_tree'] ?>:<?= $more_info['source_node_id'] ?>"><?= htmlspecialchars($more_info['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
	<? } ?>
	<span class="discreet" style="font-weight: normal;">&mdash; (<span class="calibration-count" style="color: #333;"><?= count($calibrationsInThisClade) ?></span>)</span> 
     <? if (count($calibrationsInThisClade) > 0) { ?>
	<a target="_blank" style="font-weight: normal;"
		  href="/search.php?SortResultsBy=DATE_ADDED_DESC&SimpleSearch=&HiddenFilters[]=FilterByTipTaxa&BlockedFilters[]=FilterByTipTaxa&TaxonA=&TaxonB=&FilterByClade=<?= htmlspecialchars($more_info['uniquename']) ?>&HiddenFilters[]=FilterByAge&MinAge=&MaxAge=&HiddenFilters[]=FilterByGeologicalTime&FilterByGeologicalTime=">
		show as search result<?= count($calibrationsInThisClade) == 1 ? '' : 's' ?>
	</a>
     <? } ?>
<!-- START ghosted calibration IDs
	&nbsp; <? foreach($calibrationsInThisClade as $calID) { ?> &nbsp; <a style="font-weight: normal; color: #ccc;" 
		href="/Show_Calibration.php?CalibrationID=<?= $calID ?>"><?= $calID ?></a><? } ?>
END ghosted calibration IDs -->


<? if (isset($calibration_list2)) { ?>
	<div class="listed-calibrations">
	     <? 
		$maxCalibrationsToShow = 10;
		$counter = 0;
		while ($row = mysqli_fetch_array($calibration_list2)) {
			$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $row['CalibrationID'];
		 ?>
<!--
<pre>$row:
<?  // N.B. This is very expensive, and can show binary gibberish! Use sparingly!
    /* print_r($row) */
?>
</pre>
-->
		<a href="<?= $calibrationDisplayURL ?>" class="matches-<?= $row['CalibrationID'] ?> <?= ($counter > $maxCalibrationsToShow) ? "hidden-clutter" : "" ?>">
			<?= $row['NodeName'] ?>
			<span class="citation">&ndash; <?= $row['ShortName'] ?></span>
		</a>
	     <? 	$counter++;
		}
 ?>
	</div>
<? 
	        if ($counter > $maxCalibrationsToShow) {
 ?>
			<a class="show-hidden-clutter" href="#">show all</a>
<?              } 

   }
?>

	<!-- <em>depth=<?= $row['query_depth'] ?></em> -->
	<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->
    </li>
<?  } ?>
</ul><!-- end of .child-listing -->


</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<script type="text/javascript">
	// copy view settings from server
	var lineage = '<?= $lineage ?>';
	var members = '<?= $members ?>';
	var levels = '<?= $levels ?>';

	function updateView() {
		// if something in our view has changed (eg, lineage, levels) update all page elements

		var $optionalAncestors = $('.ancestor-path a:contains(>)'); 
			// TODO: replace this with something smarter?

		switch(lineage) {
			case 'full':
				$('#lineage-toggle').text('(full)');
				$('#lineage-toggle').attr('title', "Click to show abbreviated lineage");

				$optionalAncestors.show();
				$optionalAncestors.prev('.path-divider').show();
				break;
			case 'sparse':
				$('#lineage-toggle').text('(abbreviated)');
				$('#lineage-toggle').attr('title', "Click to show full lineage");

				$optionalAncestors.hide();
				$optionalAncestors.prev('.path-divider').hide();
				break;
			default:
				console.log("ERROR - unexpected value for lineage: "+ lineage);
		}

		switch(members) {
			case 'full':
				$('#full-sparse-toggle').text('(full tree)');
				$('#full-sparse-toggle').attr('title', "Click to show only clade members with calibrations");

				$('li.no-calibrations').show();
				$('ul.child-listing .empty-warning').remove();
				break;
			case 'sparse':
				$('#full-sparse-toggle').text('(sparse tree)');
				$('#full-sparse-toggle').attr('title', "Click to show all clade members");

				//$('li.no-calibrations').hide();
				if ($('li.has-calibrations').length === 0 && $('li.no-calibrations').length > 0) {
					$('ul.child-listing:eq(0)').prepend(
						'<li class="empty-warning" style="font-style: italic;">'
						+ 'There are no calibrations in this part of the tree.</li>'
						// + '<a href="#" onclick="$(\'#full-sparse-toggle\').click(); return false;">'
						// + 'Switch to the full tree view</a> to see nodes in this area.</li>'
					);
				} else {
					$('ul.child-listing .empty-warning').remove();
				}
				break;
			default:
				console.log("ERROR - unexpected value for members: "+ members);
		}

		switch(levels) {
			default:
				console.log("INFO - found this value for levels: "+ levels);
		}
		
		// modify URLs for all "live" hyperlinks to reflect the current view
		$('.ancestor-path a, a.nlevel-option, ul.child-listing li a.node-link').each(function() {
			var $link = $(this);
			var oldHref = $link.attr('href');
			var url = oldHref.split('&')[0];  // remove any fragment and most args (all but the node IDs)
			url += ('&lineage=' + lineage);
			url += ('&members=' + members);
			if ($link.is('.nlevel-option')) {
				url += ('&levels=' + $link.text());
			} else {
				url += ('&levels=' + levels);
			}

			$link.attr('href', url);
		});

		// light up matching calibration links (on hover), wherever they appear
		$('.listed-calibrations a').hover(
			function() {
				var $link = $(this);
				var itsCalibrationID = $link.attr('class').split('matches-')[1];
				var matchSelector = '.listed-calibrations a.matches-'+ itsCalibrationID; 
				$(matchSelector).addClass('matching');
			},
			function() {
				$('.listed-calibrations a').removeClass('matching');
			}
		);

		$('ul.child-listing').show();
	}

	$(document).ready(function() {
/*
		// clean up multi-level tree (move children into sub-lists, indent)
		var $taxa = $('ul.child-listing li');
		$taxa.each(function() {
			var $movingItem = $(this);
			var itsClassNames = $movingItem.attr('class').split(' ');
			var parentMarkerClass = null;
			for (var i = 0; i < itsClassNames.length; i++) {

				//if (itsClassNames[i] === 'node-id-33213') debugger;

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
				var $subList = $parentItem.find('ul:eq(0)');
				if ($subList.length === 0) {
					$parentItem.append('<ul></ul>');
					$subList = $parentItem.find('ul:eq(0)')
				}
				$subList.append($movingItem);
			} else {
				console.log("INFO: expected one parent item, found "+ $parentItem.length +" .. possibly off-screen root?");
				return;
			}
		});
*/
		$('#simple-search-input').autocomplete({
			source: '/autocomplete_species.php',
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			minChars: 3,
			// CLEAR field if no taxon selected
			change: function(event, ui) {
				if (!ui.item) {
					// widget was blurred with invalid value; clear ALL 
					// related (stale) values from the UI!
					$(this).val('');
				} else {
					///console.log("FINAL VALUE (not pinging) > "+ ui.item.value);
				}
			}
		});
		// bounce to Search should forward the chosen taxon, if any
		$('#adv-search-link').unbind('click').click(function() {
			// pass the current search terms to the full search page
			var $simpleSearchField = $('#browse-form [name=SimpleSearch]');
			if ($.trim($simpleSearchField.val()) !== '') {
				// transfer main search field's value to a dedicated clade field for Advanced Search
				$('#browse-form').append('<input type="hidden" name="FilterByClade" value="'+ $simpleSearchField.val() +'" />');
				// force immediate use+display of the clade filter in Advanced Search
				$('#browse-form').append('<input type="hidden" name="HiddenFilters[]" value="FilterByTipTaxa" />');
				$('#browse-form').append('<input type="hidden" name="HiddenFilters[]" value="FilterByAge" />');
				$('#browse-form').append('<input type="hidden" name="HiddenFilters[]" value="FilterByGeologicalTime" />');
				$('#browse-form').append('<input type="hidden" name="BlockedFilters[]" value="FilterByTipTaxa" />');
				// clear the main field (doesn't do a proper clade search in Adv. Search page)
				$simpleSearchField.val('');  
			}
			$('#browse-form').attr('action','/search.php').submit();
			return false;
		});
		
		// view options for browsing UI
		$('#lineage-toggle').unbind('click').click(function() {
			var $clicked = $(this);
			lineage = (lineage === 'sparse') ? 'full' : 'sparse';
			updateView();
			return false;
		});
		$('#full-sparse-toggle').unbind('click').click(function() {
			var $clicked = $(this);
			members = (members === 'sparse') ? 'full' : 'sparse';
			updateView();
			return false;
		});
		$('.nlevel-option').unbind('click').click(function() {
			var $clicked = $(this);
			if ($clicked.is('.selected')) {
				// already selected, don't reload the page
				return false;
			} else {
				return true;
			}
		});
		$('.show-hidden-clutter').unbind('click').click(function() {
			var $clicked = $(this);
			// N.B. we can't simply use .show() here, or the links will be shown 'inline'
			$clicked.closest('li.has-calibrations').find('.listed-calibrations a.hidden-clutter').css('display','inline-block');
			$clicked.hide();
		});

		updateView();
	});

</script>
<?php 
//open and print page footer template
require('footer.php');
?>
