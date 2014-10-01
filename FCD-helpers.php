<?php
//require('../config.php');

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
	// Handle ambiguous names and homonyms? should we be taking IDs in to start with?
	//
	// In case of failure (incl. query error), return null instead of error message!
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
		(userIsAdmin() ? "" :  
		    " AND FCD_names.is_public_name = 1"
		)
		." LIMIT 1;";
	    $match_list=mysqli_query($mysqli, $query) or null;  /// WAS die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
            if($match_list) {
	        $node_data = mysqli_fetch_assoc($match_list);
	    }
	}

?><div class="search-details">nameToSourceNodeInfo('<?= $taxonName ?>') return this node_data:<br/><? print_r($node_data) ?></div><?

	if (!$node_data) return null;

	return $node_data;
}
function nameToMultitreeID( $taxonName ) {
	// check list of names against this query
	// show un-published names only to logged-in admins/reviewers
	// 
	// Handle ambiguous names and homonyms? should we be taking IDs in to start with?
	global $mysqli;

	$node_data = nameToSourceNodeInfo( $taxonName );

	if (!$node_data) return null;

	// call stored *function* to retrieve the multitree ID
	$query="SELECT getMultitreeNodeID( '". mysql_real_escape_string($node_data['source']) ."', '". mysql_real_escape_string($node_data['taxonid']) ."' )";

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

	// loop through and escape each value in $calibrationIDs
	foreach ($calibrationIDs as &$untrustedVal) {  
		// NOTE that we grab each result by REFERENCE, so we can modify it in place
		$untrustedVal = mysql_real_escape_string($untrustedVal);
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
	// NOTE that in this case, each value in $calibrationIDs was safely escaped using mysql_real_escape_string
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

	// loop through and escape each value in $multitreeIDs
	foreach ($multitreeIDs as &$untrustedVal) {  
		// NOTE that we grab each result by REFERENCE, so we can modify it in place
		$untrustedVal = mysql_real_escape_string($untrustedVal);
	}

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

	// loop through and escape each value in $targetNodeIDs
	foreach ($targetNodeIDs as &$untrustedVal) {  
		// NOTE that we grab each result by REFERENCE, so we can modify it in place
		$untrustedVal = mysql_real_escape_string($untrustedVal);
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

	$query="SELECT DISTINCT calibration_id FROM calibrations_by_NCBI_clade WHERE clade_root_multitree_id = '". mysql_real_escape_string($clade_root_source_id) ."'".
	// non-admin users should only see *Published* calibrations
	(userIsAdmin() ? "" :  
	    " AND calibration_id IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)"
	);

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

	$query="SELECT DISTINCT calibration_id FROM calibrations_by_NCBI_clade WHERE (clade_root_multitree_id = '". mysql_real_escape_string($clade_root_source_id) ."' AND is_direct_relationship = 1 AND is_custom_child_node != 1)".
	// non-admin users should only see *Published* calibrations
	(userIsAdmin() ? "" :  
	    " AND calibration_id IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)"
	);

?><div class="search-details">QUERY:<br/><?= $query ?></div><?
	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	while($row=mysqli_fetch_assoc($result)) {
		$calibrationIDs[] = $row['calibration_id'];
	}
	return $calibrationIDs;
}

function getCalibrationsInCustomChildNodes($clade_root_source_id) {
	// this one returns only those calibrations of a custom child node under the clade-root node
	global $mysqli;
	$calibrationIDs = array();

	$query="SELECT DISTINCT calibration_id FROM calibrations_by_NCBI_clade WHERE (clade_root_multitree_id = '". mysql_real_escape_string($clade_root_source_id) ."' AND is_custom_child_node = 1)";
	// non-admin users should only see *Published* calibrations
	(userIsAdmin() ? "" :  
	    " AND calibration_id IN (SELECT CalibrationID FROM calibrations WHERE PublicationStatus = 4)"
	);

?><div class="search-details">QUERY:<br/><?= $query ?></div><?
	$result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));	
	while($row=mysqli_fetch_assoc($result)) {
		$calibrationIDs[] = $row['calibration_id'];
	}
	return $calibrationIDs;
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

function formatDOIForHyperlink( $doi ) {
	// Return complete URLs unchanged; modify others as needed.

	// strip all whitespace anywhere in the string
	$doi = preg_replace('/\s+/', '', $doi);

	// strip any leading "DOI:" or "doi:"
	$doi = preg_replace('/doi:/i', '', $doi);

	// if the string starts with a valid scheme, keep it
	$valid_schemes = array('http://', 'https://');
	foreach($valid_schemes as $scheme) {
		if (substr($doi, 0, strlen($scheme)) === $scheme) {
			return $doi;
		}
	}

	// treat anything else as a "naked" DOI and wrap it
	return "http://dx.doi.org/$doi";
}

?>
