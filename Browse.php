<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');

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
 * fetch information on the current node's ancestor path (on all trees)
 */
$sql = 'CALL getAllAncestors('. $nodeMultitreeID .', "TEMP_ancestors", "ALL TREES" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'CALL getFullNodeInfo("TEMP_ancestors", "TEMP_ancestors_info" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'SELECT * FROM TEMP_ancestors_info';
$ancestors_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//var_dump($ancestors_info_results);

// gather all results into an array so we can organize by tree
$ancestors = array();
$ancestor_trees = array();
while ($row = mysqli_fetch_array($ancestors_info_results)) {
	$ancestors[]=$row;
	$ancestor_trees[]=$row['source_tree'];
}

// how many trees are used here? force to unique values!
$ancestor_trees = array_unique($ancestor_trees);

// grab target node information from tip of ancestors array
if (count($ancestors) > 0) {
	$targetNodeInfo = $ancestors[ count($ancestors) - 1 ];
} else {
	$targetNodeInfo = $ancestors[ 0 ];
}


/*
 * fetch information on the current node's descendants (on all trees). Limit
 * this to a few levels, lest we choke on nodes close to the root.
 */
$sql = 'CALL getCladeFromNode('. $nodeMultitreeID .', "TEMP_descendants", "ALL TREES", 1 )';

//mysqli_free_result($results);
///set_time_limit( 0 ); // kill the SQL time limit!?
$results = mysqli_query($mysqli, $sql, MYSQLI_STORE_RESULT) or die ('Error in sql: '.$sql.'|'. mysql_error());
//while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_free_result($results);

$sql = 'CALL getFullNodeInfo("TEMP_descendants", "TEMP_descendants_info" )';
$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
//mysqli_store_result($mysqli);

$sql = 'SELECT * FROM TEMP_descendants_info';
$descendants_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
//var_dump($descendants_info_results);

// gather all results into an array so we can organize by tree
$descendants = array();
$descendant_trees = array();
while ($row = mysqli_fetch_array($descendants_info_results)) {
	$descendants[]=$row;
	$descendant_trees[]=$row['source_tree'];
}

// how many trees are used here? force to unique values!
$descendant_trees = array_unique($descendant_trees);

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

<p><h1>Browse multitree at node<br/> 
'<?= htmlspecialchars($targetNodeInfo['uniquename']) ?>' (<?= $nodeSource ?>:<?= $nodeSourceID ?>, mID:<?= $nodeMultitreeID ?>)</h1></p>
<?php
    $calibrationsInThisTaxon = getDirectCalibrationsInCladeRoot($nodeMultitreeID);
?>
<h3>There are <?= count($calibrationsInThisTaxon) ?> calibrations directly related to this taxon</h3>
<?php
if (count($ancestor_trees) > 1) { ?>
<p><em>This node has been pinned across <?= count($ancestor_trees) ?> trees, as shown below.</em></p>
<? }

// clear the big ancestor list
$ancestors = array();

foreach ($ancestor_trees as $tree) { 
	// To avoid duplication, we need to retrieve ancestors for each tree separately
	$sql = 'CALL getAllAncestors('. $nodeMultitreeID .', "TEMP_ancestors", "'.$tree.'" )';
	$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
	while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
	//mysqli_store_result($mysqli);

	$sql = 'CALL getFullNodeInfo("TEMP_ancestors", "TEMP_ancestors_info" )';
	$results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
	while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish
	//mysqli_store_result($mysqli);

	$sql = 'SELECT * FROM TEMP_ancestors_info WHERE source_tree = "'. $tree .'"';
	$ancestors_info_results = mysqli_query($mysqli, $sql) or die ('Error in sql: '.$sql.'|'. mysql_error());
	while(mysqli_more_results($mysqli)) mysqli_next_result($mysqli); // wait for this to finish

	$ancestors = array();
	while ($row = mysqli_fetch_array($ancestors_info_results)) {
		$ancestors[]=$row;
	}

	// Skip this tree if only the target node is in it 
	if (count($ancestors) < 2) continue;
?>
<h3 class="contentheading">Ancestors in tree '<?= $tree ?>'</h3>
<div class="ancestor-path">
	<? $nthAncestor = 0;
	   foreach ($ancestors as $row) {
		if ($row['multitree_node_id'] == $nodeMultitreeID) continue; // don't include the target node!
	 /* ?><br/><pre><? var_dump($row); ?></pre><? */
		$nthAncestor++;
		// show each ancestor as a breadcrumb/link in chain of ancestry ?>
		
		<? if ($nthAncestor > 1) { ?>&raquo;<? } ?>
		<a href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
		<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->

	<?  } ?>
</div><!-- end of .ancestor-path -->
<? }

foreach ($descendant_trees as $tree) { ?>
<h3 class="contentheading">Child nodes in tree '<?= $tree ?>'</h3>
<ul class="child-listing">
<?  foreach ($descendants as $row) {
	if ($row['multitree_node_id'] == $nodeMultitreeID) continue; // else root will appear as its own child
	if ($row['source_tree'] != $tree) continue;
	if ($row['query_depth'] != 1) continue; // show immediate children only!
	// try a more specific count
	$calibrationsInThisClade = getAllCalibrationsInClade($row['multitree_node_id']);
?>
    <li>	
      <? if ($row['is_calibration_target'] == 1) { ?><strong><? } ?>
       <? if (count($calibrationsInThisClade) > 0) { ?><em><? } ?>
	<a href="/Browse.php?node=<?= $row['source_tree'] ?>:<?= $row['source_node_id'] ?>"><?= htmlspecialchars($row['uniquename']) ?><!-- [<?= $row['source_tree'] ?>] --></a>
        <? if (count($calibrationsInThisClade) > 0) { ?> &nbsp; <a href="#TODO" title="Click to see calibrations">(<span class="calibration-count"><?= count($calibrationsInThisClade) ?></span>)</a><? } ?>
       <? if (count($calibrationsInThisClade) > 0) { ?></em><? } ?>
      <? if ($row['is_calibration_target'] == 1) { ?></strong><? } ?>
	<!-- <em>depth=<?= $row['query_depth'] ?></em> -->
	<!-- TODO: provide a default identifier (eg, FCD-42:987) for unnamed nodes in submitted trees -->
    </li>
<?  } ?>
</ul><!-- end of .child-listing -->
<? }
?>


</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('footer.php');
?>
