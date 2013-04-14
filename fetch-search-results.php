<?php 
/*
 * Return markup with a series of search results (fossil calibrations), based on the POSTed query
 *
 * TODO: allow different response types: HTML, JSON?
 */

// open and load site variables
require('Site.conf');

// build search object from GET vars or other inputs (eg, a saved-query ID)
include('build-search-query.php'); 

// Quick test for non-empty string
function isNullOrEmptyString($str){
    return (!isset($str) || ($str == null) || trim($str)==='');
}

// test for active filter (by name)
function filterIsActive( $fullFilterName ) {
	global $search;
	if (in_array($fullFilterName, $search['HiddenFilters'])) return false;
	if (in_array($fullFilterName, $search['BlockedFilters'])) return false;
	return true;
}

// TODO: move this to shared functions?
function nameToMultitreeID( $taxonName ) {
	// check list of names against this query
	// show un-published names only to logged-in admins/reviewers
	// 
	// TODO: Handle ambiguous names and homonyms? should we be taking IDs in to start with?
	global $mysqli;

	$query="SELECT taxonid, 'NCBI' AS source
		FROM NCBI_names
		WHERE name LIKE '". mysql_real_escape_string($taxonName) ."'
	    LIMIT 1;";
	$match_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	$node_data = mysqli_fetch_assoc($match_list);

	if (!$node_data) {
	    // fall back to FCD names *if* no NCBI node was found
	    $query="SELECT taxonid, 'FCD' AS source
		FROM FCD_names
		WHERE name LIKE '". mysql_real_escape_string($taxonName) ."'".
		// non-admin users should only see *Published* publication names
		((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? "" :  
		    " AND is_public_name = 1"
		)
		." LIMIT 1;";
	    $match_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	    $node_data = mysqli_fetch_assoc($match_list);
	}

	if (!$node_data) return null;

	// call stored *function* to retrieve the multitree ID
	$query="SELECT getMultitreeNodeID( '". $node_data['source'] ."', '". $node_data['taxonid'] ."' )";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));

	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}
	$row = mysqli_fetch_row($result);
	return $row[0];
}

// TODO: move this to shared functions?
function getMultitreeIDForMRCA( $multitree_id_A, $multitree_id_B ) {
	global $mysqli;

	$query="CALL getMostRecentCommonAncestor( '". mysql_real_escape_string($multitree_id_A) ."', '". mysql_real_escape_string($multitree_id_B) ."', 'temp_MRCA', 'ALL TREES' );";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}

	// this should have populated a temporary table
	$query="SELECT * FROM temp_MRCA;";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}
	$mrca_data = mysqli_fetch_assoc($result);
	return $mrca_data['node_id'];
}

// TODO: move this to shared functions?
function getAllMultitreeAncestors( $multitree_node_id ) {
	global $mysqli;
	$ancestorIDs = Array();

	$query="CALL getAllAncestors ( '". mysql_real_escape_string($multitree_node_id) ."', 'temp_ancestors', 'ALL TREES' );";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}

	// this should have populated a temporary table
	$query="SELECT * FROM temp_ancestors;";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}
	while($row=mysqli_fetch_assoc($result)) {
		/*
		?><h3><?= print_r($row) ?></h3><?
		*/
		$ancestorIDs[] = $row['node_id'];
	}

	return $ancestorIDs;
}

//
//	$match_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
//	$node_data = mysqli_fetch_assoc($match_list);


$responseType = $search['ResponseType']; // HTML | JSON | ??

/* TODO: page or limit results, eg, 
 *	$search['ResultsRange'] = "1-10"
 *	$search['ResultsRange'] = "21-40"
 */

$sortedResults = array();

/* TODO: If the requested sort doesn't make sense given the search type(s), apply 
 * some simple rules to override it.
 */
$forcedSort = null;	

// connect to mySQL server and select the Fossil Calibration database (using newer 'msqli' interface)
$mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');



/*
 * Building top-level search logic here for now, possibly move this into stored procedure later..?
 */

// apply each included search type in turn, then weigh/consolidate its results?

// tip-taxon search, using one or two taxa...
$tip_taxa_list=null;
if (filterIsActive('FilterByTipTaxa')) {
	if (empty($search['FilterByTipTaxa']['TaxonA']) && empty($search['FilterByTipTaxa']['TaxonB'])) {
		// no taxa specified, bail out now
	} else if (!empty($search['FilterByTipTaxa']['TaxonA']) && !empty($search['FilterByTipTaxa']['TaxonB'])) {
		// both taxa were specified... 

		// resolve taxon multitree IDs
		$multitree_id_A = nameToMultitreeID($search['FilterByTipTaxa']['TaxonA']);
		$multitree_id_B = nameToMultitreeID($search['FilterByTipTaxa']['TaxonB']);
		/*
		?><h3>A: <?= $multitree_id_A ?></h3><?
		?><h3>B: <?= $multitree_id_A ?></h3><?
		*/

		// fetch multitree IDs for MRCA and all ancestors
		$multitree_id_MRCA = getMultitreeIDForMRCA( $multitree_id_A, $multitree_id_B );
		$multitree_id_ancestors_A = getAllMultitreeAncestors( $multitree_id_A );
		$multitree_id_ancestors_B = getAllMultitreeAncestors( $multitree_id_B );

		// check MRCA (common ancestor)

		// check director ancestors of A or B

		// check all within clade of MRCA

		// check all neighbors of MRCA

		// check all neighbors of direct ancestors of A or B
	} else {
?><h3>1 TAXON SUBMITTED</h3><?
		// just one taxon was specified
		$multitree_id = nameToMultitreeID($search['FilterByTipTaxa']['TaxonA'] . $search['FilterByTipTaxa']['TaxonB']);
		$multitree_id_ancestors = getAllMultitreeAncestors( $multitree_id );

		// check its direct ancestors

		// check all neighbors of direct ancestors

	}
}

// IF no searches applied yet, return the n results most recently added
$query='SELECT DISTINCT C . *, img.image, img.caption AS image_caption
	FROM (
		SELECT CF.CalibrationID, V . *
		FROM View_Fossils V
		JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
	) AS J
	JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID
	LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
	ORDER BY DateCreated DESC
	LIMIT 10';
$recently_added_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	


// TODO: sort/sift from all the results lists above
while($row=mysqli_fetch_assoc($recently_added_list)) {
	$sortedResults[] = $row;
}


// return these results in the requested format
if ($responseType == 'JSON') {
	echo json_encode($sortedResults);
	return;
}

// still here? then build HTML markup for the results
if (count($sortedResults) == 0) {
	?><p style="font-style: italic;">No matching calibrations found. To see more results, simplify your search by removing text above or hiding filters.</p><?
} else {
	foreach($sortedResults as $result) 
	{ 
		// print hidden diagnostic info
		?>
		<pre class="search-details" style="color: green;">
		<?= print_r($result) ?>
		</pre>
		<?

		$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $result['CalibrationID'];

		/* TODO: Preset these "qualifiers" in consolidated results */
		$relationship = null; 
		$relevance = null;
		$minAge = floatval($result['MinAge']);
		$maxAge = floatval($result['MaxAge']);
		// PHP's floats are imprecise, so we should define what constitutes equality here
		$epsilon = 0.0001;
?>
<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			<? if ($relationship) { ?>
				\/
			<? } else { ?>
				&nbsp;	
			<? } ?>
			</td>
			<td width="*">
			<? if ($relevance) { ?>
				99% match
			<? } else { ?>
				&nbsp;	
			<? } ?>
			</td>
			<td width="100">
			<? if(abs($minAge-$maxAge) < $epsilon) { ?>
				<?= $minAge ?> Ma
			<? } else if ($minAge && $maxAge) { ?>
				<?= $minAge ?>&ndash;<?= $maxAge ?> Ma
			<? } else if ($minAge) { ?>
				&gt; <?= $minAge ?> Ma
			<? } else if ($maxAge){ ?>
				&lt; <?= $maxAge ?> Ma
			<? } else { ?>
				&nbsp;	
			<? } ?>
			</td>
			<td width="120">
				Added <?= date("M d, Y", strtotime($result['DateCreated'])) ?>
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name"><?= $result['NodeName'] ?></span>
		<span class="citation">&ndash; from <?= $result['ShortName'] ?></span>
	</a>
	<br/>
	<? // if there's an image mapped to this publication, show it
	   if ($result['image']) { ?>
	<div class="optional-thumbnail" style="height: 60px;">
	    <a href="<?= $calibrationDisplayURL ?>">
		<img src="/publication_image.php?id=<?= $result['PublicationID'] ?>" style="height: 60px;"
		alt="<?= $result['image_caption'] ?>" title="<?= $result['image_caption'] ?>"
		/></a>
	</div>
	<? } ?>
	<div class="details">
		<?= $result['FullReference'] ?>
		&nbsp;
		<a class="more" style="display: block; text-align: right;" href="<?= $calibrationDisplayURL ?>">more &raquo;</a>
	</div>
</div>
    <? }
}

if (count($sortedResults) > 10)  // TODO?
{ ?>
<div style="text-align: right; border-top: 1px solid #ddd; font-size: 0.9em; padding-top: 2px;">
	<a href="#">Show more results like this</a>
</div>
<? }

return;
?>


