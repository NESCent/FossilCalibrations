<?php 
/*
 * This is a faceless script that tries to add or update calibration records. It expects to
 * find a number of dependent records (publications, fossils, etc) already in place.
 */

// open and load site variables
require('../Site.conf');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// check nonce (one-time key) to make sure this is not an accidental re-submit
if ($_SESSION['nonce'] != $_POST['nonce']) {
    echo 'This form has already been submitted!';  
    echo '<a href="/protected/edit_calibration.php?id='. $_POST['CalibrationID'] .'">return to editor</a><br/><br/>';
    return;
} else {
    // clear the session nonce and keep going
    $_SESSION['nonce'] = null;
}

echo '<pre>'.print_r($_POST, true).'</pre>';

$addOrEdit = $_POST['addOrEdit']; // should be 'ADD' or 'EDIT'


/*
 * Update the calibration and all related records. NOTE that we start with
 * dependencies in mind, so any record whose ID is stored elsewhere must
 * come first, and we'll "work our way up" to the main calibration record.
 */

/* Add or update the main publication record
 */
$mainPubID = $_POST['PubID'];
if ($_POST['newOrExistingPublication'] == 'NEW') {
	// add main publication record
	$query="INSERT INTO publications SET
			 ShortName = '". mysql_real_escape_string($_POST['ShortForm']) ."'
			,FullReference = '". mysql_real_escape_string($_POST['FullCite']) ."'
			,DOI = '". mysql_real_escape_string($_POST['DOI']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$mainPubID = mysql_insert_id();
}

/* Add or update the fossil species record (in table fossiltaxa)
 */
$fossilSpeciesName = '???';
// What does this really mean? We might have matched+entered an NCBI taxon name, but still need to
// create a matching fossiltaxa record. Check for an existing (matching) record in fossiltaxa first! then 
// whether or not to create or update a record here.
if ($_POST['newOrExistingFossilSpecies'] == 'NEW') {
	// add species record for this fossil
	$query="INSERT INTO fossiltaxa SET
			 TaxonName = '". mysql_real_escape_string($_POST['NewSpeciesName']) ."'
			,CommonName = '". mysql_real_escape_string($_POST['NewSpeciesCommonName']) ."'
			,TaxonAuthor = '". mysql_real_escape_string($_POST['NewSpeciesAuthor']) ."'
		        ,PBDBTaxonNum = '". mysql_real_escape_string($_POST['NewSpeciesPBDBTaxonNum']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossiltaxaID = mysql_insert_id();
	$fossilSpeciesName = $_POST['NewSpeciesName'];
} else if ($_POST['newOrExistingFossilSpecies'] == 'EXISTING') {
	// apply any updates to existing fossiltaxa record (or build one now)
	$fossiltaxaID = $_POST['ExistingFossilSpeciesID'];
		// NOTE that this refers to the ID of any existing _fossiltaxa_ record, regardless of whether
		// or not this taxon name is known within the system.
	if ($fossiltaxaID == 'ADD TO FOSSILTAXA') {
		// create a new fossiltaxa record
		$query="INSERT INTO fossiltaxa SET
				 TaxonName = '". mysql_real_escape_string($_POST['ExistingSpeciesName']) ."'
				,CommonName = '". mysql_real_escape_string($_POST['ExistingSpeciesCommonName']) ."'
				,TaxonAuthor = '". mysql_real_escape_string($_POST['ExistingSpeciesAuthor']) ."'
				,PBDBTaxonNum = '". mysql_real_escape_string($_POST['ExistingSpeciesPBDBTaxonNum']) ."'";
	} else {
		// update the existing fossiltaxa record
		$query="UPDATE fossiltaxa  
			SET
				 TaxonName = '". mysql_real_escape_string($_POST['ExistingSpeciesName']) ."'
				,CommonName = '". mysql_real_escape_string($_POST['ExistingSpeciesCommonName']) ."'
				,TaxonAuthor = '". mysql_real_escape_string($_POST['ExistingSpeciesAuthor']) ."'
				,PBDBTaxonNum = '". mysql_real_escape_string($_POST['ExistingSpeciesPBDBTaxonNum']) ."'
			WHERE TaxonID = '". mysql_real_escape_string($fossiltaxaID) ."'
		";
	}
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossilSpeciesName = $_POST['ExistingSpeciesName'];
}


/* Add or update the fossil collection record?
 */
$fossilCollectionAcronym = $_POST['CollectionAcro'];
if ($_POST['newOrExistingCollectionAcronym'] == 'NEW') {
	// add locality record for this fossil
	$query="INSERT INTO L_CollectionAcro SET
			 Acronym = '". mysql_real_escape_string($_POST['NewAcro']) ."'
			,CollectionName = '". mysql_real_escape_string($_POST['NewInst']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	// NOTE this special case! We need to new acronym, NOT the new insert ID (AcroID field)
	$fossilCollectionAcronym = $_POST['NewAcro'];
}

/* Add or update the fossil locality record?
 */
$fossilLocalityID = $_POST['Locality'];
if ($_POST['newOrExistingLocality'] == 'NEW') {
	// add locality record for this fossil
	$query="INSERT INTO localities SET
			 LocalityName = '". mysql_real_escape_string($_POST['LocalityName']) ."'
			,Stratum = '". mysql_real_escape_string($_POST['Stratum']) ."'
			,MinAge = '". mysql_real_escape_string($_POST['StratumMinAge']) ."'
			,MaxAge = '". mysql_real_escape_string($_POST['StratumMaxAge']) ."'
			,GeolTime = '". mysql_real_escape_string($_POST['GeolTime']) ."'
			,Country = '". mysql_real_escape_string($_POST['Country']) ."'
			,LocalityNotes = '". mysql_real_escape_string($_POST['LocalityNotes']) ."'
		        ,PBDBCollectionNum = '". mysql_real_escape_string($_POST['PBDBNum']) ."'";  // TODO: use $_POST['CollectionNum'] instead?
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossilLocalityID = mysql_insert_id();
}

/* Add or update the fossil publication record
 */
$fossilPubID = $_POST['FossilPub'];
if ($_POST['newOrExistingFossilPublication'] == 'NEW') {
	// add fossil publication record
	$query="INSERT INTO publications SET
			 ShortName = '". mysql_real_escape_string($_POST['FossShortForm']) ."'
			,FullReference = '". mysql_real_escape_string($_POST['FossFullCite']) ."'
			,DOI = '". mysql_real_escape_string($_POST['FossDOI']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossilPubID = mysql_insert_id();
}

/* Add or update the phylogeny publication record
 */
$phyloPubID = $_POST['PhyPub'];
if ($_POST['newOrExistingPhylogenyPublication'] == 'REUSE_FOSSIL_PUB') {
	// special case! allow re-use of the fossil publication here
	$phyloPubID = $fossilPubID;
} else if ($_POST['newOrExistingPhylogenyPublication'] == 'NEW') {
	// add phylogeny publication record
	$query="INSERT INTO publications SET
			 ShortName = '". mysql_real_escape_string($_POST['PhyloShortForm']) ."'
			,FullReference = '". mysql_real_escape_string($_POST['PhyloFullCite']) ."'
			,DOI = '". mysql_real_escape_string($_POST['PhyloDOI']) ."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$phyloPubID = mysql_insert_id();
}

/* Add or update the fossil record (in table fossils)
 */
$query="SELECT * FROM fossils WHERE FossilID = (SELECT FossilID FROM Link_CalibrationFossil WHERE CalibrationID = '". mysql_real_escape_string($_POST['CalibrationID']) ."')";
$fossil_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
// list all new OR updated values (NOTE that we store the fossil species by its scientific name, not an ID!)
// TODO: use $_POST['PBDBNum'] instead of $_POST['CollectionNum'] below?
$newValues = "
		 Species = '". mysql_real_escape_string($fossilSpeciesName) ."'
		,CollectionAcro = '". mysql_real_escape_string($fossilCollectionAcronym) ."'
		,CollectionNumber = '". mysql_real_escape_string($_POST['CollectionNum']) ."' 
		,LocalityID = '". mysql_real_escape_string($fossilLocalityID) ."'
		,FossilPub = '". mysql_real_escape_string($fossilPubID) ."'
		,MinAge = '". mysql_real_escape_string($_POST['FossilMinAge']) ."'
		,MinAgeType = '". mysql_real_escape_string($_POST['MinAgeType']) ."'
		,MaxAge = '". mysql_real_escape_string($_POST['FossilMaxAge']) ."'
		,MaxAgeType = '". mysql_real_escape_string($_POST['MaxAgeType']) ."'
		,PhyJustificationType = '". mysql_real_escape_string($_POST['PhyJustType']) ."'
		,PhyJustification = '". mysql_real_escape_string($_POST['PhyJustification']) ."'
		,PhyloPub = '". mysql_real_escape_string($phyloPubID) ."'
";
$addingFossilCalibrationLink = false;
if (mysql_num_rows($fossil_result)==0) {
	// add a new fossil record, and (later, after we have a known-good calibration ID) a link entry to this calibration
	$query="INSERT INTO fossils
		SET $newValues";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$fossilID = mysql_insert_id();

	$addingFossilCalibrationLink = true;
} else {
	// update the existing fossil record
	$fossil_data = mysql_fetch_assoc($fossil_result);
	$fossilID = $fossil_data['FossilID'];
	$query="UPDATE fossils 
		SET $newValues
		WHERE FossilID = '". mysql_real_escape_string($fossilID) ."'
	";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}
mysql_free_result($fossil_result);


/* Add or update the main calibration record
 */
// list all new OR updated values
$newValues = "
		 CalibrationID = '". mysql_real_escape_string($_POST['CalibrationID']) ."'
		,NodeName = '". mysql_real_escape_string($_POST['NodeName']) ."'
		,HigherTaxon = '". mysql_real_escape_string($_POST['HigherTaxon']) ."'
		,MinAge = '". mysql_real_escape_string($_POST['MinAge']) ."'
		,MinAgeExplanation = '". mysql_real_escape_string($_POST['MinAgeJust']) ."'
		,MaxAge = '". mysql_real_escape_string($_POST['MaxAge']) ."'
		,MaxAgeExplanation = '". mysql_real_escape_string($_POST['MaxAgeJust']) ."'
		,NodePub = '". mysql_real_escape_string($mainPubID) ."'
		,PublicationStatus = '". mysql_real_escape_string($_POST['PublicationStatus']) ."'
		,CalibrationQuality = '". mysql_real_escape_string($_POST['CalibrationQuality']) ."'
		,AdminComments = '". mysql_real_escape_string($_POST['AdminComments']) ."'
";
$query="INSERT INTO calibrations
	SET $newValues
	ON DUPLICATE KEY UPDATE $newValues";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$calibrationID = mysql_insert_id();
if ($calibrationID == 0) {
	// this calibration already exists, keep the original ID
	$calibrationID = $_POST['CalibrationID'];
}

// now that we have a known-good calibration ID, we might need to add a link to its fossil
if ($addingFossilCalibrationLink) {
	$query="INSERT INTO Link_CalibrationFossil SET 
		 FossilID = '". mysql_real_escape_string($fossilID) ."'
		,CalibrationID = '". mysql_real_escape_string($calibrationID) ."'
		";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}
	

/*
 * Add or update tip taxa for this node
 */

// clobber all existing pair-calibration links for this calibration (leave defined tips in place)
$query="DELETE FROM Link_CalibrationPair WHERE 
        CalibrationID = '". mysql_real_escape_string($calibrationID) ."'";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// process any pair variables found
$nthPair = 1;
while (isset($_POST["Pair{$nthPair}TaxonA"])) {
	$taxonA = $_POST["Pair{$nthPair}TaxonA"];
	$taxonB = $_POST["Pair{$nthPair}TaxonB"];
	// SKIP any pair with missing/empty names??
	if (!empty($taxonA) && !empty($taxonB)) {
		$query= 'SELECT * FROM Link_Tips WHERE (TaxonA=\''.$taxonA.'\' AND TaxonB=\''.$taxonB.'\') OR (TaxonA=\''.$taxonB.'\' && TaxonB=\''.$taxonA.'\')';
		$pair_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		if( mysql_num_rows($pair_result)==0 ) {
			// add this new pair and assign to the current calibration
			$query= 'INSERT INTO Link_Tips (TaxonA,TaxonB) VALUES (\''.mysql_real_escape_string($taxonA).'\', \''.mysql_real_escape_string($taxonB).'\')';
			$newpairs=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
			$pairID=mysql_insert_id();
		} else {
			// this pair already exists, probably entered with another calibration
			$row=mysql_fetch_assoc($pair_result);
			$pairID=$row['PairID'];
		}
		$query='INSERT INTO Link_CalibrationPair (CalibrationID,TipPairsID) VALUES (\''.mysql_real_escape_string($calibrationID).'\',\''.$pairID.'\')';
		$newcladepair=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	}
	$nthPair++;
}

// NOTE that we're careful to return to a new calibration with its new assigned ID
echo '<a href="/protected/edit_calibration.php?id='. $calibrationID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_calibration.php?id='. $calibrationID .'&result=success');
exit();
?>
