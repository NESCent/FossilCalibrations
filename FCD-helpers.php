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

?><div class="search-details">nameToSourceNodeInfo() return this node_data:<br/><? print_r($node_data) ?></div><?

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
	return $mrca_data['node_id'];
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
	// add calibration data to the existing array with the qualifier(s) provided
	global $mysqli;

/* SIMPLER QUERY, in case we want to postpone the heavy one below
		$query="SELECT DISTINCT * FROM calibrations 
			WHERE CalibrationID IN 
			    (SELECT calibration_id FROM FCD_trees WHERE tree_id IN
				(SELECT tree_id FROM FCD_nodes WHERE node_id IN (". implode(",", $targetNodeIDs) .")));";
*/

	$query="SELECT DISTINCT C . *, img.image, img.caption AS image_caption
		FROM (
			SELECT CF.CalibrationID, V . *
			FROM View_Fossils V
			JOIN Link_CalibrationFossil CF ON CF.FossilID = V.FossilID
		) AS J
		JOIN View_Calibrations C ON J.CalibrationID = C.CalibrationID
		LEFT JOIN publication_images img ON img.PublicationID = C.PublicationID
		WHERE C.CalibrationID IN (". implode(",", $calibrationIDs) .");
	       ";
	$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
	while (mysqli_more_results($mysqli)) {
		mysqli_next_result($mysqli);
		mysqli_store_result($mysqli);
	}
	while($row=mysqli_fetch_assoc($result)) {
		foreach($qualifiers as $qName => $qValue) {
			$row[$qName] = $qValue;
		}
		/* ?><div class="search-details"><? print_r($row) ?></div><? */
		$existingArray[] = $row;
	}
	mysqli_free_result($result);
}

function addAssociatedCalibrations( &$existingArray, $multitreeIDs, $qualifiers ) {
	// check these multitree IDs for associated calibrations; if found, 
	// add calibration data to the existing array with the qualifier(s) provided
	global $mysqli;
	$targetNodeIDs = Array();

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
?><div class="search-details">CALIBRATED NODE: <? print_r($row) ?></div><?
		$targetNodeIDs[] = $row['source_node_id'];
	}

	if (count($targetNodeIDs) > 0) {
		// now fetch the associated calibration for each target node


		$query="SELECT calibration_id FROM FCD_trees WHERE tree_id IN
				(SELECT tree_id FROM FCD_nodes WHERE node_id IN (". implode(",", $targetNodeIDs) ."));
		       ";
		$result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));

		while($row=mysqli_fetch_assoc($result)) {
			$calibrationIDs[] = $row['calibration_id'];
		}
		if (count($calibrationIDs) > 0) {
			addCalibrations( $existingArray, $calibrationIDs, $qualifiers );
		}
	}

	return;
}

?>
