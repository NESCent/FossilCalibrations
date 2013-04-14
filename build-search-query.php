<?
/* Build a standard PHP object from the first available source of search properties:
 *   > DO NOTHING if the expected object is already defined and non-empty
 *   > LATER: build from DB record (stored query) if $savedSearchID is provided
 *   > try to build from $_GET vars, if found
 *
 * NOTE: We should be able to include this mutiple times, with NO side effects
 * since it only builds once. This makes it easy to build an accurate search UI,
 * to embed the results panel in the main search page, and also call separately
 * for results via AJAX.
 */
if (!isset($search) || ($search == null)) {
    // try to build the expected search object (skip if it's already defined)

    // build default search (show all calibrations, most recently added first)
    $search = Array(
	'SimpleSearch' => '',	
	    // TODO: allow for a SERIES of tags/tokens here?
	'FilterByTipTaxa' => Array(
	    'TaxonA' => '', 
	    'TaxonB' => ''
	),
	'FilterByClade' => '',  
	    // a single name (different from single tip taxon?)
	'FilterByAge' => Array(
	    'MinAge' => '', 
	    'MaxAge' => ''
	),
	'FilterByGeologicalTime' => '',  
	    // allow one age only?
	'HiddenFilters' => Array(
	    'FilterByTipTaxa',
	    'FilterByClade',
	    'FilterByAge',
	    'FilterByGeologicalTime'
	),
	    // to preserve values that were entered, then hidden 
	'BlockedFilters' => Array(),
	    // to preserve values that were entered, but blocked by rules
	'SortResultsBy' => 'DATE_ADDED_DESC'
    ); 

    // apply submitted ($_GET) variables, if found
    if (isset($_GET['SimpleSearch'])) {
    	$search['SimpleSearch'] = $_GET['SimpleSearch'];
	if (isset($_GET['TaxonA'])) {
	    $search['FilterByTipTaxa']['TaxonA'] = $_GET['TaxonA'];
	}
	if (isset($_GET['TaxonB'])) {
	    $search['FilterByTipTaxa']['TaxonB'] = $_GET['TaxonB'];
	}
	if (isset($_GET['FilterByClade'])) {
	    $search['FilterByClade'] = $_GET['FilterByClade'];
	}
	if (isset($_GET['MinAge'])) {
	    $search['FilterByAge']['MinAge'] = $_GET['MinAge'];
	}
	if (isset($_GET['MaxAge'])) {
	    $search['FilterByAge']['MaxAge'] = $_GET['MaxAge'];
	}
	if (isset($_GET['FilterByGeologicalTime'])) {
	    $search['FilterByGeologicalTime'] = $_GET['FilterByGeologicalTime'];
	}
	if (isset($_GET['HiddenFilters'])) {
	    $search['HiddenFilters'] = $_GET['HiddenFilters'];
	} else {
	    $search['HiddenFilters'] = Array();
	}
	if (isset($_GET['BlockedFilters'])) {
	    $search['BlockedFilters'] = $_GET['BlockedFilters'];
	} else {
	    $search['BlockedFilters'] = Array();
	}
	if (isset($_GET['SortResultsBy'])) {
	    $search['SortResultsBy'] = $_GET['SortResultsBy'];
	}
    }

    // add diagnostic info to page
    ?>
    <a href="#" onclick="$('#search-details').toggle();" style="color: #c33; background-color: #ffd; padding: 2px 4px;; font-size: 8px; position: absolute; left: 0; top: 0;">show search object</a>

    <div id="search-details" style="display: none; background-color: #ffd; overflow: hidden; padding: 0 5px; font-size: 50%;">
	<pre id="request-details" style="color: #c33; width: 48%; float: left;">======== GET (form) values ========
<? print_r($_GET); ?>
	</pre>
	<pre id="search-object-details" style="color: #33c; width: 48%; float: left;">======== $search object ========
<? print_r($search); ?>
	</pre>
    </div>
    <?
}
?>
