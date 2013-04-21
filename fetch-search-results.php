<?php 
/*
 * Return markup with a series of search results (fossil calibrations), based on the POSTed query
 *
 * TODO: Support different response types: JSON? others?
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

$responseType = $search['ResponseType']; // HTML | JSON | ??

/* TODO: page or limit results, eg, 
 *	$search['ResultsRange'] = "1-10"
 *	$search['ResultsRange'] = "21-40"
 */

$searchResults = array();

/* TODO: If the requested sort doesn't make sense given the search type(s), apply 
 * some simple rules to override it.
 */
$forcedSort = null;	

// connect to mySQL server and select the Fossil Calibration database (using newer 'msqli' interface)
$mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');



/*
 * Building top-level search logic here for now, possibly move this into stored procedure later..?
 */
$showDefaultSearch = true; // TODO: improve logic for this as more filters are implemented

// apply each included search type in turn, then weigh/consolidate its results?


// simple text search; compare to misc titles, text data, and taxa(?)
// TODO: if a name resolves to a taxon, should it become an implicit tip-taxa or clade search?
if (!empty($search['SimpleSearch'])) {
	// break text into tokens (split on commas or whitespace, but respected quoted phrases)
	// see http://fr2.php.net/manual/en/function.preg-split.php#92632
	$search_expression = $search['SimpleSearch'];  // eg,  "apple bear \"Tom Cruise\" or 'Mickey Mouse' another word";
	$searchTerms = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/", $search_expression, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
	// trim leading and trailing whitespace
	// remove empty tokens
?><div class="search-details">SIMPLE SEARCH TERMS:<br/>
	<pre><? print_r( $searchTerms ); ?></pre></div><?
}

// tip-taxon search, using one or two taxa...
if (filterIsActive('FilterByTipTaxa')) {
	if (empty($search['FilterByTipTaxa']['TaxonA']) && empty($search['FilterByTipTaxa']['TaxonB'])) {
		// no taxa specified, bail out now
		
	} else if (!empty($search['FilterByTipTaxa']['TaxonA']) && !empty($search['FilterByTipTaxa']['TaxonB'])) {
?><div class="search-details">2 TAXA SUBMITTED</div><?
		// both taxa were specified... 
		$showDefaultSearch = false; // TODO

		/* 
		 * Check for associated calibrations ("direct hits" and "near misses") based on related multitree IDs
		 */

		// resolve taxon multitree IDs
		$multitree_id_A = nameToMultitreeID($search['FilterByTipTaxa']['TaxonA']);
		$multitree_id_B = nameToMultitreeID($search['FilterByTipTaxa']['TaxonB']);
/*
?><h3>A: <?= $multitree_id_A ?></h3><?
?><h3>B: <?= $multitree_id_A ?></h3><?
*/

		// check MRCA (common ancestor)
		$multitree_id_MRCA = getMultitreeIDForMRCA( $multitree_id_A, $multitree_id_B );
?><div class="search-details">MRCA: <?= $multitree_id_MRCA ?></div><?
		addAssociatedCalibrations( $searchResults, Array($multitree_id_MRCA), Array('relationship' => 'MRCA', 'relevance' => 1.0) );

		// check director ancestors of A or B (includes the tip taxa)
		$multitree_id_ancestors_A = getAllMultitreeAncestors( $multitree_id_A );
?><div class="search-details">ANCESTORS-A: <?= implode(", ", $multitree_id_ancestors_A) ?></div><?
		addAssociatedCalibrations( $searchResults, $multitree_id_ancestors_A, Array('relationship' => 'ANCESTOR-A', 'relevance' => 0.5) );

		$multitree_id_ancestors_B = getAllMultitreeAncestors( $multitree_id_B );
?><div class="search-details">ANCESTORS-B: <?= implode(", ", $multitree_id_ancestors_B) ?></div><?
		addAssociatedCalibrations( $searchResults, $multitree_id_ancestors_B, Array('relationship' => 'ANCESTOR-B', 'relevance' => 0.5) );

		// TODO: check all within clade of MRCA
		// addAssociatedCalibrations( $searchResults, $multitree_id_clade_members, Array('relationship' => 'MRCA-CLADE', 'relevance' => 0.25) );

		// TODO: check all neighbors of MRCA
		// addAssociatedCalibrations( $searchResults, $multitree_id_mrca_neighbors, Array('relationship' => 'MRCA-NEIGHBOR', 'relevance' => 0.1) );

		// TODO: check all neighbors of direct ancestors of A or B
		// addAssociatedCalibrations( $searchResults, $multitree_id_ancestor_neighbors, Array('relationship' => 'ANCESTOR-NEIGHBOR', 'relevance' => 0.1) );
	} else {
?><div class="search-details">1 TAXON SUBMITTED</div><?
		// just one taxon was specified
		$showDefaultSearch = false;
		$specifiedTaxon = empty($search['FilterByTipTaxa']['TaxonA']) ? 'B' : 'A'; 

		/* 
		 * Check for associated calibrations ("direct hits" and "near misses") based on related multitree IDs
		 */

		// resolve taxon multitree ID
		$multitree_id = nameToMultitreeID($search['FilterByTipTaxa']['Taxon'.$specifiedTaxon]);

		// check its direct ancestors (includes the tip taxon)
		$multitree_id_ancestors = getAllMultitreeAncestors( $multitree_id );
?><div class="search-details">ANCESTORS-<?= $specifiedTaxon ?>: <?= implode(", ", $multitree_id_ancestors) ?></div><?
		addAssociatedCalibrations( $searchResults, $multitree_id_ancestors, Array('relationship' => ('ANCESTOR-'.$specifiedTaxon), 'relevance' => 1.0) );

		// TODO: check all neighbors of direct ancestors
		// addAssociatedCalibrations( $searchResults, $multitree_id_ancestor_neighbors, Array('relationship' => 'ANCESTOR_NEIGHBOR', 'relevance' => 0.2) );
	}
}

// search within a named clade
if (filterIsActive('FilterByClade')) {
	if (empty($search['FilterByClade'])) {
		// no clade specified, bail out now
	} else {
?><div class="search-details">CLADE SUBMITTED</div><?
		// search within this clade
		$showDefaultSearch = false;

		/* 
		 * Check for associated calibrations ("direct hits" and "near misses") based on related multitree IDs
		 */

		// resolve clade multitree ID
		$multitree_id = nameToMultitreeID($search['FilterByClade']);

		// gather a reasonable number of its clade members (limit depth to stay below this number)
		$maxMembersToSearch = 10000;
		$searchIsLimited = true;

		$memberCount = 0;
		$lastCount = 0;
		$searchDepth = 0;
		while ($memberCount < $maxMembersToSearch) {
			$searchDepth++;

			$query="CALL getCladeFromNode( '$multitree_id', 'clade_member_ids', 'NCBI', '$searchDepth' )";
if ($searchDepth == 1) { ?><div class="search-details"><?= $query ?></div><? }

			$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
			while(mysqli_more_results($mysqli)) {
			     mysqli_next_result($mysqli);
			     mysqli_store_result($mysqli);
			}
			$query='SELECT COUNT(node_id) FROM clade_member_ids';
			$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
			$row=mysqli_fetch_row($result);
			$memberCount = $row[0];

?><div class="search-details">CLADE MEMBERS (search depth=<?= $searchDepth ?>): <?= $memberCount ?> members found</div><?

			if ($memberCount == $lastCount) {	
				// not too many members in this clade
				$searchIsLimited = false;
				break;
			}
			$lastCount = $memberCount;
		}
		$query='SELECT node_id FROM clade_member_ids';
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
		$multitree_id_clade_members = array();
		while($row=mysqli_fetch_assoc($result)) {
			$multitree_id_clade_members[] = $row['node_id'];
		}
		mysqli_free_result($result);

		if ($searchIsLimited) {
			?><p style="color: #a33;">
				For performance reasons, clade searches are limited to <?= number_format($maxMembersToSearch) ?> members. 
				As a result, some calibrations may not appear. For complete results, search again on a smaller clade.
			  </p><?
		}

		// check all clade members (not actually likely to hit, see below)
		addAssociatedCalibrations( $searchResults, $multitree_id_clade_members, Array('relationship' => 'CLADE-MEMBER-1', 'relevance' => 0.5) );

		// check the PARENT node IDs for all clade members (actually more likely to hit)
		$query='SELECT parent_node_id FROM multitree WHERE node_id IN ('. implode(", ", $multitree_id_clade_members) .')';
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
		$multitree_id_clade_members = array();
		while($row=mysqli_fetch_assoc($result)) {
			$multitree_id_clade_members[] = $row['parent_node_id'];
		}
		addAssociatedCalibrations( $searchResults, $multitree_id_clade_members, Array('relationship' => 'CLADE-MEMBER-2', 'relevance' => 0.5) );

		// TODO: check all neighbors of direct ancestors
		// addAssociatedCalibrations( $searchResults, $multitree_id_ancestor_neighbors, Array('relationship' => 'ANCESTOR_NEIGHBOR', 'relevance' => 0.2) );
	}
}


// filtering results by minimum and/or maximum age
if (filterIsActive('FilterByAge')) {
	if (empty($search['FilterByAge']['MinAge']) && empty($search['FilterByAge']['MaxAge'])) {
		// no ages specified, bail out now
		
	} else if (!empty($search['FilterByAge']['MinAge']) && !empty($search['FilterByAge']['MaxAge'])) {
?><div class="search-details">MIN AND MAX AGES SUBMITTED</div><?
		// search within this clade
		$showDefaultSearch = false;

		/* 
		 * Check for calibrations within the specified age ranage. NOTE that we should check
		 * both age bounds, as a sanity check in case only one was entered ("blank" ranges will appear as 0).
		 */
		$matching_calibration_ids = array();
		$query="SELECT CalibrationID FROM calibrations WHERE 
			       MinAge >= '". $search['FilterByAge']['MinAge'] ."' AND MaxAge >= '". $search['FilterByAge']['MinAge'] ."'
			   AND MinAge <= '". $search['FilterByAge']['MaxAge'] ."' AND MaxAge <= '". $search['FilterByAge']['MaxAge'] ."'
		";
?><div class="search-details"><?= $query ?></div><?
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	

		// TODO: sort/sift from all the results lists above
		while($row=mysqli_fetch_assoc($result)) {
			$matching_calibration_ids[] = $row['CalibrationID'];
		}
		if (count($matching_calibration_ids) > 0) {
			addCalibrations( $searchResults, $matching_calibration_ids, Array('relationship' => 'MATCHES-AGE', 'relevance' => 1.0) );
		}

	} else {
		// just one age was specified
		$showDefaultSearch = false;
		$specifiedAge = empty($search['FilterByAge']['MinAge']) ? 'MaxAge' : 'MinAge'; 
?><div class="search-details">1 AGE SUBMITTED (<?= $specifiedAge ?>)</div><?

		/* 
		 * Check for calibrations that are newer (or older) than the age specified. NOTE that we should check
		 * both age bounds, as a sanity check in case only one was entered ("blank" ranges will appear as 0).
		 */
		$matching_calibration_ids = array();
		if ($specifiedAge == 'MinAge') {
			$query="SELECT CalibrationID FROM calibrations WHERE MinAge >= '". $search['FilterByAge']['MinAge'] ."' AND MaxAge >= '". $search['FilterByAge']['MinAge'] ."'";
		} else {
			$query="SELECT CalibrationID FROM calibrations WHERE MinAge <= '". $search['FilterByAge']['MaxAge'] ."' AND MaxAge <= '". $search['FilterByAge']['MaxAge'] ."'";
		}
?><div class="search-details"><?= $query ?></div><?
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	

		// TODO: sort/sift from all the results lists above
		while($row=mysqli_fetch_assoc($result)) {
			$matching_calibration_ids[] = $row['CalibrationID'];
		}
		if (count($matching_calibration_ids) > 0) {
			addCalibrations( $searchResults, $matching_calibration_ids, Array('relationship' => 'MATCHES-AGE', 'relevance' => 1.0) );
		}
	}
}


// filtering results by geological time
if (filterIsActive('FilterByGeologicalTime')) {
	if (empty($search['FilterByGeologicalTime'])) {
		// no time specified, bail out now
	} else {
?><div class="search-details">GEOLOGICAL TIME SUBMITTED</div><?
		// search within this period
		$showDefaultSearch = false;

		/* 
		 * Check for calibrations from the specified time
		 */
		$matching_calibration_ids = array();
		$query="SELECT CalibrationID FROM Link_CalibrationFossil WHERE FossilID IN 
			    (SELECT FossilID FROM fossils WHERE LocalityID IN
				(SELECT LocalityID FROM localities WHERE GeolTime = 
				    (SELECT GeolTimeID FROM geoltime WHERE Age = '". $search['FilterByGeologicalTime'] ."')));";
?><div class="search-details"><?= $query ?></div><?
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	

		// TODO: sort/sift from all the results lists above
		while($row=mysqli_fetch_assoc($result)) {
			$matching_calibration_ids[] = $row['CalibrationID'];
		}
		if (count($matching_calibration_ids) > 0) {
			addCalibrations( $searchResults, $matching_calibration_ids, Array('relationship' => 'MATCHES-GEOTIME', 'relevance' => 1.0) );
		}
	}
}


// IF no search tools were active and loaded, return the n results most recently added
if ($showDefaultSearch) {
?><div class="search-details">SHOWING DEFAULT SEARCH</div><?
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
		$searchResults[] = $row;
	}
}


// return these results in the requested format
if ($responseType == 'JSON') {
	echo json_encode($searchResults);
	return;
}

/* ?><h3>FINAL: <?= count($searchResults) ?> results</h3><? */

// still here? then build HTML markup for the results
if (count($searchResults) == 0) {
	?><p style="font-style: italic;">No matching calibrations found. To see more results, simplify your search by removing text above or hiding filters.</p><?
} else {
	foreach($searchResults as $result) 
	{ 
		// print hidden diagnostic info
		?>
		<pre class="search-details" style="color: green;">
		<? print_r($result) ?>
		</pre>
		<?

		$calibrationDisplayURL = "/Show_Calibration.php?CalibrationID=". $result['CalibrationID'];

		/* TODO: Preset these "qualifiers" in consolidated results */
		$relationship = isset($result['relationship']) ? $result['relationship'] : null; 
		$relevance = isset($result['relevance']) ? $result['relevance'] : null; 
		$minAge = floatval($result['MinAge']);
		$maxAge = floatval($result['MaxAge']);
		// PHP's floats are imprecise, so we should define what constitutes equality here
		$epsilon = 0.0001;
?>
<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="30" title="Cladistic relatioship to entered taxa">
			<? if ($relationship) { ?>
				<?= $relationship ?>
			<? } else { ?>
				&nbsp;	
			<? } ?>
			</td>
			<td width="*" title="Relevance based on all filters used">
			<? if ($relevance) { ?>
				<?= intval($relevance * 100) ?>% match
			<? } else { ?>
				&nbsp;	
			<? } ?>
			</td>
			<td width="100" title="Calibrated age range">
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
			<td width="120" title="Date entered into database">
				Added <?= date("M d, Y", strtotime($result['DateCreated'])) ?>
			</td>
		</tr>
	</table>
	<a class="calibration-link" href="<?= $calibrationDisplayURL ?>>
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

if (count($searchResults) > 10)  // TODO?
{ ?>
<div style="text-align: right; border-top: 1px solid #ddd; font-size: 0.9em; padding-top: 2px;">
	<a href="#">Show more results like this</a>
</div>
<? }

return;
?>


