<?php
//require('Site.conf');

/* 
 * Role and permission checks, including role-specific convenience functions.
 */

function requireRoleOrLogin( $requiredRole ) {
   global $SITEINFO;
   // bounce to login page IF user lacks the required role for the calling page
   if (!userHasRole( $requiredRole )) {
      $_SESSION['REDIRECT_ON_LOGIN'] = $_SERVER["REQUEST_URI"];
      header("HTTP/1.1 401 Unauthorized");
      header("Location: https://" . $SITEINFO['secure_hostname_and_port'] . "/login.php");
      exit();
   }
}

function userHasRole( $requiredRole ) {
   $userHasRequiredRole = false;
   switch( $requiredRole ) {
      case 'ADMIN':
         if (isset($_SESSION['IS_ADMIN_USER']) && $_SESSION['IS_ADMIN_USER'] == true) {
            $userHasRequiredRole = true;
         } 
         break;

      case 'REVIEWER':
         if (isset($_SESSION['IS_REVIEWER']) && $_SESSION['IS_REVIEWER'] == true) {
            $userHasRequiredRole = true;
         }
         break;

      default:
         die('Please specify ADMIN or REVIEWER roles! (unknown role '.$requiredRole.')');
   }
   return $userHasRequiredRole;
}

function userIsAdmin() {
   return userHasRole( 'ADMIN' );
}

function userIsReviewer() {
   return userHasRole( 'REVIEWER' );
}

function userIsLoggedIn() {
   return userHasRole( 'ADMIN' ) || userHasRole( 'REVIEWER' );
}

/*
 * Cross-platform stub for calling asynchronous operations (command-line stuff)
 */
function execInBackground($cmd) { 
    if (substr(php_uname(), 0, 7) == "Windows"){ 
        pclose(popen("start /B ". $cmd, "r"));  
    } 
    else { 
        exec($cmd . " > /dev/null &");   
    } 
} 

function runSQLScript( $relativePathToScript ) {
   global $SITEINFO;

   // execInBackground("/opt/lampp/bin/mysql --host='127.0.0.1' --user='zzzzzzzz' --password='xxxxxxxxxx' --database='FossilCalibration' --execute='source /opt/lampp/htdocs/fossil-calibration/protected/SQL_TEST.sql'");

   $mysql = $SITEINFO['mysql_exec'];
   $host = $SITEINFO['servername'];
   $dbuser = $SITEINFO['UserName'];
   $dbpass = $SITEINFO['password'];
   $docroot = $SITEINFO['docroot'];
   execInBackground( "$mysql --host='$host' --user='$dbuser' --password='$dbpass' --database='FossilCalibration' --execute='source $docroot$relativePathToScript'" );
}

/* Return a desired property from any array-like objects, or a default if not found.
 * This should generally Do the Right Thing, whether we're working with a new object, 
 * editing a complete existing object, or one that's partially complete.
 */
function testForProp( $data, $property, $default ) {
	if (!is_array($data)) return $default;
	if (!array_key_exists($property, $data)) return $default;
	return $data[$property];
}

/* High-level functions for search and data reporting
 */
function nameToSourceNodeInfo( $taxonName ) {
	// check list of names against this query
	// show un-published names only to logged-in admins/reviewers
	// returns a simple object with 'source' and 'taxonid' properties
	// 
	// TODO: Handle ambiguous names and homonyms? should we be taking IDs in to start with?
	global $mysqli;

	$query="SELECT taxonid, 'NCBI' AS source
		FROM NCBI_names
		WHERE name LIKE '". mysql_real_escape_string($taxonName) ."'
		OR uniquename LIKE '". mysql_real_escape_string($taxonName) ."'
	    LIMIT 1;";
	$match_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	$node_data = mysqli_fetch_assoc($match_list);

	if (!$node_data) {
	    // fall back to FCD names *if* no NCBI node was found
	    $query="SELECT FCD_names.node_id, CONCAT('FCD-',FCD_nodes.tree_id) AS source
		FROM FCD_names
		JOIN FCD_nodes ON FCD_nodes.node_id = FCD_names.node_id
		WHERE FCD_names.name LIKE '". mysql_real_escape_string($taxonName) ."'".
		// non-admin users should only see *Published* publication names
		((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? "" :  
		    " AND FCD_names.is_public_name = 1"
		)
		." LIMIT 1;";
	    $match_list=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	    $node_data = mysqli_fetch_assoc($match_list);
	}

?><div class="search-details">nameToSourceNodeInfo('<?= $taxonName ?>') return this node_data:<br/><? print_r($node_data) ?></div><?

	if (!$node_data) return null;

	return $node_data;
}
function nameToMultitreeID( $taxonName ) {
	// check list of names against this query
	// show un-published names only to logged-in admins/reviewers
	// 
	// TODO: Handle ambiguous names and homonyms? should we be taking IDs in to start with?
	global $mysqli;

	$node_data = nameToSourceNodeInfo( $taxonName );

	if (!$node_data) return null;

	// call stored *function* to retrieve the multitree ID
	$query="SELECT getMultitreeNodeID( '". $node_data['source'] ."', '". $node_data['taxonid'] ."' )";

	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));

	while(mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		$result = mysqli_store_result($mysqli);
	}
	$row = mysqli_fetch_row($result);
?><div class="search-details">nameToMultitreeID('<?= $taxonName ?>') returns ID:<br/><? print_r($row[0]) ?></div><?
	return $row[0];
}

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
	if ($mrca_data) {
		return $mrca_data['node_id'];
	} else {
		return null;
	}
}

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
		?><h3><? print_r($row) ?></h3><?
		*/
		$ancestorIDs[] = $row['node_id'];
	}

	return $ancestorIDs;
}

function addCalibrations( &$existingArray, $calibrationIDs, $qualifiers ) {
	// add calibration data to the existing array (or embellish it), with the qualifier(s) provided
	global $mysqli;

/* SIMPLER QUERY, in case we want to postpone the heavy one below
		$query="SELECT DISTINCT * FROM calibrations 
			WHERE CalibrationID IN 
			    (SELECT calibration_id FROM FCD_trees WHERE tree_id IN
				(SELECT tree_id FROM FCD_nodes WHERE node_id IN (". implode(",", $targetNodeIDs) .")));";
*/

	if (count($calibrationIDs) == 0) {
		// we got an empty ID list for some reason
		return;
	}

	$query="SELECT DISTINCT C . *, img.image, img.caption AS image_caption
		FROM (
			SELECT CF.CalibrationID, V . *
			FROM View_Fossils V
			JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
		) AS J
		RIGHT JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID
		LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
		WHERE C.CalibrationID IN (". implode(",", $calibrationIDs) .");
	       ";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while (mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		mysqli_store_result($mysqli);
	}

	while($row=mysqli_fetch_assoc($result)) {
		// test to see if this calibration is already in the array; if so, just add another weighted relationship
		$alreadyInArray = false;
		foreach ($existingArray as &$testResult) {  
			// NOTE that we grab each result by REFERENCE, so we can modify it in place
			if ($testResult['CalibrationID'] == $row['CalibrationID']) {
				// it's already here; just add the new relationship+relevance combo
				$alreadyInArray = true;
				$testResult['qualifiers'][ ] = $qualifiers;
				break;
			}
		}

		if (!$alreadyInArray) {
			// it's a whole new calibration; add it with this initial relationship+relevance combo
			$row['qualifiers'] = Array( $qualifiers );
			$existingArray[] = $row;
		}
?><div class="search-details">Adding calibration <?= $row['CalibrationID'] ?> (inner add-op or non-cladistic search)</div><?

	}

	mysqli_free_result($result);
}

function addAssociatedCalibrations( &$existingArray, $multitreeIDs, $qualifiers ) {
	// check these multitree IDs for associated calibrations; if found, 
	// add calibration data to the existing array with the qualifier(s) provided
	global $mysqli;
	$targetNodeIDs = Array();

	// bail on missing/empty IDs (for more legible calling code)
	if ($multitreeIDs == null) return;
	if (count($multitreeIDs) == 0) return;
	if ($multitreeIDs[0] == null) return;
	//if (empty($multitreeIDs[0])) return;

	// TODO: GUARD against visitors seeing unpublished calibrations!

	// test for any UN-pinned FCD nodes; for now, this is a valid test for calibration target nodes!
	$query="SELECT * FROM node_identity WHERE (source_tree != 'NCBI') AND (is_pinned_node = 0) AND multitree_node_id IN (". implode(",", $multitreeIDs) .");";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while (mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		mysqli_store_result($mysqli);
	}
/* ?><h3><? print_r($result) ?></h3><? */
	while ($row=mysqli_fetch_assoc($result)) {
/* ?><div class="search-details">CALIBRATED NODE: <? print_r($row) ?></div><? */
		$targetNodeIDs[] = $row['source_node_id'];
	}

	if (count($targetNodeIDs) > 0) {
		// now fetch the associated calibration for each target node
		$query="SELECT * FROM FCD_trees WHERE tree_id IN
				(SELECT tree_id FROM FCD_nodes WHERE node_id IN (". implode(",", $targetNodeIDs) ."));
		       ";
		$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));

		while($row=mysqli_fetch_assoc($result)) {
			$calibrationIDs[] = $row['calibration_id'];
?><div class="search-details">Adding calibration <?= $row['calibration_id'] ?>, tied to root node <?= $row['root_node_id'] ?></div><?
		}
		if (count($calibrationIDs) > 0) {
			addCalibrations( $existingArray, $calibrationIDs, $qualifiers );
		}
	}

	return;
}

function getAllCalibrationsInClade($clade_root_source_id) {
	// faster clade tally, using a pre-cooked table 'calibrations_by_NCBI_clade'
	global $mysqli;
	$calibrationIDs = array();

	$query="SELECT DISTINCT calibration_id FROM calibrations_by_NCBI_clade WHERE clade_root_multitree_id = '". $clade_root_source_id ."';";
?><div class="search-details">QUERY:<br/><?= $query ?></div><?
	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	while($row=mysqli_fetch_assoc($result)) {
		$calibrationIDs[] = $row['calibration_id'];
	}
	return $calibrationIDs;
}

function getDirectCalibrationsInCladeRoot($clade_root_source_id) {
	// this one returns only those calibrations directly associated with the clade-root node
	global $mysqli;
	$calibrationIDs = array();

	$query="SELECT DISTINCT calibration_id FROM calibrations_by_NCBI_clade WHERE clade_root_multitree_id = '". $clade_root_source_id ."' AND is_direct_relationship = 1;";
?><div class="search-details">QUERY:<br/><?= $query ?></div><?
	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	while($row=mysqli_fetch_assoc($result)) {
		$calibrationIDs[] = $row['calibration_id'];
	}
	return $calibrationIDs;
}

function SLOW_getCalibrationsInClade($clade_root_source_id) {
	// adapted from search logic, BUT it's very slow (too slow) for browsing UI
	global $mysqli;

	// test all eligible calibrations, backtracking from node IDs (should still be faster than testing every Eukaryote!)
	$test_taxon_ids = array();
	// test ALL nodes in all custom trees
	$query="SELECT node_id, tree_id from FCD_nodes;";
/* ?><div class="search-details">SEARCH FOR ALL CUSTOM-TREE NODES (SOURCE IDS):<br/><?= $query ?></div><? */
	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	while($row=mysqli_fetch_assoc($result)) {
		$test_taxon_ids[] = $row;
	}

	// if any node comes 
	$matching_tree_ids = array();  // once a tree has matched, stop checking it!
	$matching_calibration_ids = array();
	foreach($test_taxon_ids as $taxon_ids) {
		$test_node_id = $taxon_ids['node_id'];
		$test_tree_id = $taxon_ids['tree_id'];
		if (!in_array($test_tree_id, $matching_tree_ids)) {
/* ?><div class="search-details">Testing node <?= $test_node_id ?> in tree <?= $test_tree_id ?></div><? */
			$query="CALL isMemberOfClade('NCBI', '$clade_root_source_id', CONCAT('FCD-', '$test_tree_id'), '$test_node_id', @isInClade);";
/* ?><div class="search-details"><pre><?= $query ?></pre></div><? */
			$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
			while(mysqli_more_results($mysqli)) {
			     mysqli_next_result($mysqli);
			     mysqli_store_result($mysqli);
			}
			$query='SELECT @isInClade';
			$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
			$foundInClade = mysqli_fetch_assoc($result);
			$foundInClade = $foundInClade['@isInClade'];
/* ?><div class="search-details">Result for node <?= $test_node_id ?>: <? print_r($foundInClade); ?></div><? */
			if ($foundInClade) {
				$matching_tree_ids[] = $test_tree_id;
/* ?><div class="search-details">First match on node <?= $test_node_id ?>, tree <?= $test_tree_id ?></div><? */
			}
		}
	}

	if (count($matching_tree_ids) > 0) {
		$query="SELECT calibration_id FROM FCD_trees WHERE tree_id IN (". implode(",", $matching_tree_ids) .");";
		$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
		while($row=mysqli_fetch_assoc($result)) {
			$matching_calibration_ids[] = $row['calibration_id'];
		}
	}

	return $matching_calibration_ids;
}


/* Multi-column (key) sorting for nested associative arrays, eg, search results
 * Adapted from http://nl.php.net/manual/en/function.natsort.php#69346
 *
 * EXAMPLE: $records = columnSort($records, array('name', 'asc', 'addres', 'desc', 'city', 'asc'));
 */

$globalMultisortVar = array();

function columnSort($recs, $cols) {
    global $globalMultisortVar;
    $globalMultisortVar = $cols;
    usort($recs, 'multiStrnatcmp');
    return($recs);
}

function multiStrnatcmp($a, $b) {
    global $globalMultisortVar;
    $cols = $globalMultisortVar;
    $i = 0;
    $result = 0;
    while ($result == 0 && $i < count($cols)) {
        $result = ($cols[$i + 1] == 'desc' ? 
		   strnatcmp($b[$cols[$i]], $a[$cols[$i]]) : 
		   $result = strnatcmp($a[$cols[$i]], $b[$cols[$i]]));
        $i+=2;
    }
    return $result;
}

function getCurrentScheme() {
	if( isset($_SERVER['HTTPS'] )  && $_SERVER['HTTPS'] != 'off' ) 
	{
	    echo 'https';
	}
	else
	{
	    echo 'http';
	}
}

?>
