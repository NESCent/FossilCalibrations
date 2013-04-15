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

// tip-taxon search, using one or two taxa...
$tip_taxa_list=null;
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


