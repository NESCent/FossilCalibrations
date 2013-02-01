<?php 
/*
 * Using a matched name via autocomplete (could be a scientific name, common name, synonym, etc), find and return either
 * 	A) the target fossiltaxa record already assigned for this calibration
 * 	B) another existing fossiltaxa record in the database?
 *	C) taxon names (partial result) for a matching NCBI or FCD node
 * 	   NOTE that there's more here than I expected. NCBI_names of class 'authority' have this information, albeit cluttered with the taxon name.

 SELECT * FROM NCBI_names WHERE taxonid = (SELECT taxonid FROM NCBI_names WHERE uniquename LIKE 'Felis domesticus' OR name LIKE 'Felis domesticus');
 */

// open and load site variables
require('Site.conf');

// Quick test for non-empty string
function isNullOrEmptyString($str){
    return (!isset($str) || ($str == null) || trim($str)==='');
}


// prepare an associative array for fossiltaxa values
$taxon_properties = array(
	'properName' => '',
	'commonName' => '',
	'author' => '',
	'pbdbTaxonNumber' => '',
	'TOTAL_MATCHING_TAXA' => '',
	'SOURCE_TABLE' => '',
	'AUTHOR_SOURCE_TABLE' => ''
);

// check for a valid (non-empty) query
if (!isset($_GET["autocomplete_match"])) {
	echo "{'ERROR': 'autocomplete_match not submitted!'}";
	return;
}
$q = strtolower($_GET["autocomplete_match"]);
if (!$q || trim($q) == "") {
	// if string is empty, don't bother checking; just return no matches
	echo json_encode($taxon_properties);
	return;
}

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// check existing fossiltaxa and matching NCBI/FCD nodes, and return the best match
// TODO: filter results for admin/reviewer vs. visitor?
$bestMatchFound = false;
$authorshipFound = false;
$CalibrationID = $_GET['calibration_ID']; 
if (is_numeric($CalibrationID) && $CalibrationID > 0) {
	/* look for a fossiltaxa record already assigned to this calibration
	$query="SELECT fossiltaxa.* FROM fossiltaxa 
		INNER JOIN fossils ON fossils.Species = fossiltaxa.TaxonName
		LEFT OUTER JOIN Link_CalibrationFossil AS link ON link.FossilID = fossils.FossilID
		INNER JOIN calibrations ON calibrations.CalibrationID = link.CalibrationID AND calibrations.CalibrationID = '". mysql_real_escape_string($CalibrationID) ."'";
	*/

	// look for an existing fossiltaxa record matching this name
	$query="SELECT * FROM fossiltaxa 
		WHERE TaxonName LIKE '". mysql_real_escape_string($q) ."' OR CommonName LIKE '". mysql_real_escape_string($q) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

	if (mysql_num_rows($result) > 0) {
		$row = mysql_fetch_assoc($result);

		$bestMatchFound = true;
		$taxon_properties['properName'] = $row['TaxonName'];
		$taxon_properties['commonName'] = $row['CommonName'];
		$taxon_properties['author'] = $row['TaxonAuthor'];
		$taxon_properties['pbdbTaxonNumber'] = $row['PBDBTaxonNum'];
		$taxon_properties['TOTAL_MATCHING_TAXA'] = mysql_num_rows($result);
		$taxon_properties['SOURCE_TABLE'] = "fossiltaxa";
		$taxon_properties['AUTHOR_SOURCE_TABLE'] = "fossiltaxa";
	}
	if (!isNullOrEmptyString($taxon_properties['author'])) {
		$authorshipFound = true;
	}

}
if  (!$bestMatchFound || !$authorshipFound) {
	/* fall back to any 'taxa' record that matches fossil's species name (worth a try, if only for fossil species and authorship info)
	$query="SELECT taxa.* FROM taxa 
		INNER JOIN fossils ON fossils.Species = taxa.TaxonName
		LEFT OUTER JOIN Link_CalibrationFossil AS link ON link.FossilID = fossils.FossilID
		INNER JOIN calibrations ON calibrations.CalibrationID = link.CalibrationID AND calibrations.CalibrationID = '". mysql_real_escape_string($CalibrationID) ."'";
	*/

	// look for an existing taxa record matching this name
	$query="SELECT * FROM taxa 
		WHERE TaxonName LIKE '". mysql_real_escape_string($q) ."' OR CommonName LIKE '". mysql_real_escape_string($q) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

	if (mysql_num_rows($result) > 0) {
		$row = mysql_fetch_assoc($result);
		// update authorship, in any case
		$taxon_properties['author'] = $row['TaxonAuthor'];
		$taxon_properties['AUTHOR_SOURCE_TABLE'] = "taxa";

		if (!$bestMatchFound) {
			$taxon_properties['properName'] = $row['TaxonName'];
			$taxon_properties['commonName'] = $row['CommonName'];
			$taxon_properties['pbdbTaxonNumber'] = '';  // this is not included in table 'taxa'
			$taxon_properties['TOTAL_MATCHING_TAXA'] = mysql_num_rows($result);
			$taxon_properties['SOURCE_TABLE'] = "taxa";
			$bestMatchFound = true;
		}
		if (!isNullOrEmptyString($taxon_properties['author'])) {
			$authorshipFound = true;
		}
	}
}
if  (!$bestMatchFound || !$authorshipFound) {
	// pull all NCBI names for the node whose name is an exact match
	// (NOTE that while the NCBI_names table does include authorship info, it's only for about 15% of taxa)
	$query="SELECT * FROM NCBI_names 
		WHERE taxonid = (SELECT taxonid FROM NCBI_names WHERE uniquename LIKE '". mysql_real_escape_string($q) ."' OR name LIKE '". mysql_real_escape_string($q) ."')";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

	if (mysql_num_rows($result) > 0) {
		// walk the matching names, sorting them by type (class), eg, 'synonym', 'authorship', 'common name'
		$scientificName = "";
		$commonName = "";
		$genbankCommonName = ""; 	// use 'genbank common name' if found
		$author = "";
		while ($matchingName = mysql_fetch_assoc($result)) {
			$nameType = $matchingName['class'];
			switch( $nameType ) {
				case 'scientific name':
					$scientificName = $matchingName['name']; // OR uniquename??
					break;
				case 'common name':
					// NOTE that there might be several of these; just send the last one found
					$commonName = $matchingName['name']; // OR uniquename??
					break;
				case 'genbank common name':
					// this will trump other common names
					$genbankCommonName = $matchingName['name']; // OR uniquename??
					break;
				case 'authority':
					// this includes the taxon name, but send it as-is for now
					$author = $matchingName['name']; // OR uniquename??
					break;
			}
		}

		// update authorship, in any case
		$taxon_properties['author'] = $author;
		$taxon_properties['AUTHOR_SOURCE_TABLE'] = "NCBI_names";

		if  (!$bestMatchFound) {
			$taxon_properties['properName'] = $scientificName;
			$taxon_properties['commonName'] = !isNullOrEmptyString($genbankCommonName) ? $genbankCommonName : $commonName;
			$taxon_properties['pbdbTaxonNumber'] = "";  // sorry, not available here
			$taxon_properties['TOTAL_MATCHING_TAXA'] = "NOT DETERMINED (". mysql_num_rows($result) ." matching NCBI names found)";
			$taxon_properties['SOURCE_TABLE'] = "NCBI_names";
		}
	}
}

// return our best guess for all fields, or empty values (to clear the UI) if no match was found
echo json_encode($taxon_properties);
?>


