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

///echo '<pre>'.print_r($_POST, true).'</pre>';

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


/* 
 * Add (or update) any fossils associated with this calibration. Un-link
 * "deleted" fossils previously entered, but don't destroy them in case they're
 * being used by another calibration, or might be in the future.
 */

/* gather all position-markers in the form as submitted (these are used to
 * bundle form values), and add or update each fossil..
 */
$fossil_positions = $_POST['fossil_positions'];
$preserveFossilLinkIDs = Array(-1);  // adding a bogus value to avoid empty-list error in MySQL!
$newFossilsToLink = Array();
// stash some ordered values so that we can store them in Link_CalibrationFossil later
$finalFossilIDs = Array();
$finalFossilSpeciesNames = Array();
$finalFossilPhyloPubIDs = Array();

///print_r($fossil_positions);
foreach($fossil_positions as $pos) {

   /* Add or update the fossil species record (in table fossiltaxa)
    */
   $fossilSpeciesName = '???';
   // What does this really mean? We might have matched+entered an NCBI taxon
   // name, but still need to create a matching fossiltaxa record. Check for an
   // existing (matching) record in fossiltaxa first! then whether or not to
   // create or update a record here.


   switch( $_POST["newOrExistingFossilSpecies-$pos"] ) {
      case 'ASSIGNED':
         $fossilLocalityID = $_POST["PreviouslyAssignedSpeciesName-$pos"];
         break;

      case 'EXISTING':
         // apply any updates to existing fossiltaxa record (or build one now)
         $fossiltaxaID = $_POST["ExistingFossilSpeciesID-$pos"];
            // NOTE that this refers to the ID of any existing _fossiltaxa_ record, regardless of whether
            // or not this taxon name is known within the system.
         if ($fossiltaxaID == 'ADD TO FOSSILTAXA') {
            // create a new fossiltaxa record
            $query="INSERT INTO fossiltaxa SET
                   TaxonName = '". mysql_real_escape_string($_POST["ExistingSpeciesName-$pos"]) ."'
                  ,CommonName = '". mysql_real_escape_string($_POST["ExistingSpeciesCommonName-$pos"]) ."'
                  ,TaxonAuthor = '". mysql_real_escape_string($_POST["ExistingSpeciesAuthor-$pos"]) ."'
                  ,PBDBTaxonNum = '". mysql_real_escape_string($_POST["ExistingSpeciesPBDBTaxonNum-$pos"]) ."'";
         } else {
            // update the existing fossiltaxa record
            $query="UPDATE fossiltaxa  
               SET
                   TaxonName = '". mysql_real_escape_string($_POST["ExistingSpeciesName-$pos"]) ."'
                  ,CommonName = '". mysql_real_escape_string($_POST["ExistingSpeciesCommonName-$pos"]) ."'
                  ,TaxonAuthor = '". mysql_real_escape_string($_POST["ExistingSpeciesAuthor-$pos"]) ."'
                  ,PBDBTaxonNum = '". mysql_real_escape_string($_POST["ExistingSpeciesPBDBTaxonNum-$pos"]) ."'
               WHERE TaxonID = '". mysql_real_escape_string($fossiltaxaID) ."'
            ";
         }
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         $fossilSpeciesName = $_POST["ExistingSpeciesName-$pos"];
         break;

      case 'NEW':
         // add species record for this fossil
         $query="INSERT INTO fossiltaxa SET
                TaxonName = '". mysql_real_escape_string($_POST["NewSpeciesName-$pos"]) ."'
               ,CommonName = '". mysql_real_escape_string($_POST["NewSpeciesCommonName-$pos"]) ."'
               ,TaxonAuthor = '". mysql_real_escape_string($_POST["NewSpeciesAuthor-$pos"]) ."'
                    ,PBDBTaxonNum = '". mysql_real_escape_string($_POST["NewSpeciesPBDBTaxonNum-$pos"]) ."'";
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         ///$fossiltaxaID = mysql_insert_id();
         $fossilSpeciesName = $_POST["NewSpeciesName-$pos"];
         break;
   }

   /* Add or update the fossil collection record?
    */
   $fossilCollectionAcronym = $_POST["CollectionAcro-$pos"];
   if ($_POST["newOrExistingCollectionAcronym-$pos"] == 'NEW') {
      // add locality record for this fossil
      $query="INSERT INTO L_CollectionAcro SET
             Acronym = '". mysql_real_escape_string($_POST["NewAcro-$pos"]) ."'
            ,CollectionName = '". mysql_real_escape_string($_POST["NewInst-$pos"]) ."'";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      // NOTE this special case! We need to new acronym, NOT the new insert ID (AcroID field)
      $fossilCollectionAcronym = $_POST["NewAcro-$pos"];
   }

   /* Add or update the fossil locality record?
    */
   switch( $_POST["newOrExistingLocality-$pos"] ) {
      case 'ASSIGNED':
         $fossilLocalityID = $_POST["PreviouslyAssignedLocality-$pos"];
         break;

      case 'EXISTING':
         $fossilLocalityID = $_POST["Locality-$pos"];
         break;

      case 'NEW':
         // add locality record for this fossil
         $query="INSERT INTO localities SET
                LocalityName = '". mysql_real_escape_string($_POST["LocalityName-$pos"]) ."'
               ,Stratum = '". mysql_real_escape_string($_POST["Stratum-$pos"]) ."'
               ,MinAge = '". mysql_real_escape_string($_POST["StratumMinAge-$pos"]) ."'
               ,MaxAge = '". mysql_real_escape_string($_POST["StratumMaxAge-$pos"]) ."'
               ,GeolTime = '". mysql_real_escape_string($_POST["GeolTime-$pos"]) ."'
               ,Country = '". mysql_real_escape_string($_POST["Country-$pos"]) ."'
               ,LocalityNotes = '". mysql_real_escape_string($_POST["LocalityNotes-$pos"]) ."'
                    ,PBDBCollectionNum = '". mysql_real_escape_string($_POST["PBDBNum-$pos"]) ."'";  // TODO: use $_POST["CollectionNum-$pos"] instead?
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         $fossilLocalityID = mysql_insert_id();
         break;
   }

   /* Add or update the fossil publication record
    */
   switch( $_POST["newOrExistingFossilPublication-$pos"] ) {
      case 'ASSIGNED':
         $fossilPubID = $_POST["PreviouslyAssignedFossilPub-$pos"];
         break;

      case 'EXISTING':
         $fossilPubID = $_POST["FossilPub-$pos"];
         break;

      case 'NEW':
         // add fossil publication record
         $query="INSERT INTO publications SET
                ShortName = '". mysql_real_escape_string($_POST["FossShortForm-$pos"]) ."'
               ,FullReference = '". mysql_real_escape_string($_POST["FossFullCite-$pos"]) ."'
               ,DOI = '". mysql_real_escape_string($_POST["FossDOI-$pos"]) ."'";
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         $fossilPubID = mysql_insert_id();
         break;
   }

   /* Add or update the phylogeny publication record
    */
   switch( $_POST["newOrExistingPhylogenyPublication-$pos"] ) {
      case 'ASSIGNED':
         $phyloPubID = $_POST["PreviouslyAssignedPhyloPub-$pos"]; // TODO
         break;

      case 'REUSE_FOSSIL_PUB':
         // special case! allow re-use of the fossil publication here
         $phyloPubID = $fossilPubID;
         break;

      case 'EXISTING':
         $phyloPubID = $_POST["PhyPub-$pos"];
         break;

      case 'NEW':
         // add phylogeny publication record
         $query="INSERT INTO publications SET
                ShortName = '". mysql_real_escape_string($_POST["PhyloShortForm-$pos"]) ."'
               ,FullReference = '". mysql_real_escape_string($_POST["PhyloFullCite-$pos"]) ."'
               ,DOI = '". mysql_real_escape_string($_POST["PhyloDOI-$pos"]) ."'";
         $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
         $phyloPubID = mysql_insert_id();
         break;
   }

   /* Add or update the fossil record (in table fossils). NOTE that this will alter
    * values that may be shared with other calibrations. Perhaps it needs a warning?
    */ 
   $fossilID = $_POST["fossilID-$pos"];
   $fossilCalibrationLinkID = $_POST["fossilCalibrationLinkID-$pos"];
   if (is_numeric($fossilCalibrationLinkID)) {
      $preserveFossilLinkIDs[] = $fossilCalibrationLinkID;
   }
   $query="SELECT * FROM fossils 
      WHERE FossilID = '". mysql_real_escape_string($fossilID) ."'";
   $fossil_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
   // list all new OR updated values (NOTE that we store the fossil species by its scientific name, not an ID!)
   // TODO: use $_POST['PBDBNum'] instead of $_POST['CollectionNum'] below?
   $newValues = "
          CollectionAcro = '". mysql_real_escape_string($fossilCollectionAcronym) ."'
         ,CollectionNumber = '". mysql_real_escape_string($_POST["CollectionNum-$pos"]) ."' 
         ,LocalityID = '". mysql_real_escape_string($fossilLocalityID) ."'
         ,FossilPub = '". mysql_real_escape_string($fossilPubID) ."'
   ";
   if (mysql_num_rows($fossil_result)==0) {
      // add a new fossil record, and (later, after we have a known-good calibration ID) a link entry to this calibration
      $query="INSERT INTO fossils
         SET $newValues";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $fossilID = mysql_insert_id();

      // store its ID for later linking
      $newFossilsToLink[] = $fossilID;

   } else {
      // update the existing fossil record
      $fossil_data = mysql_fetch_assoc($fossil_result);
      $query="UPDATE fossils 
         SET $newValues
         WHERE FossilID = '". mysql_real_escape_string($fossilID) ."'
      ";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
   
      // Is the existing fossil already linked to this calibration?
      $query="SELECT * FROM Link_CalibrationFossil 
	      WHERE
	         CalibrationID = '". mysql_real_escape_string($_POST['CalibrationID']) ."'
              AND 
                 FossilID = '". mysql_real_escape_string($fossilID) ."'";
      $link_result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      if (mysql_num_rows($link_result)==0) {
         // fossil is NOT linked yet... store its ID for later linking
         $newFossilsToLink[] = $fossilID;
      } else {
         // fossil is already linked (eg, deleted then re-added in the UI!)... preserve this link!
	 $row=mysql_fetch_assoc($link_result);
         $preserveFossilLinkIDs[] = $row['FCLinkID'];
      }
      mysql_free_result($link_result);

   }
   mysql_free_result($fossil_result);

   // stash final values for later
   $finalFossilIDs[$pos] = $fossilID;
   $finalFossilSpeciesNames[$pos] = $fossilSpeciesName;
   $finalFossilPhyloPubIDs[$pos] = $phyloPubID;
}

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


/* Now that we have a known-good calibration ID, we might need to un-link some
 * (deleted) fossils and link some (added) ones for this calibration.
 */
$query="DELETE FROM Link_CalibrationFossil 
   WHERE
      CalibrationID = '". mysql_real_escape_string($calibrationID) ."'
   AND 
      (FCLinkID NOT IN (". implode(",", $preserveFossilLinkIDs) ."))
   ";
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

foreach($newFossilsToLink as $addFossilID) {
	$query="INSERT INTO Link_CalibrationFossil SET 
		 FossilID = '". $addFossilID ."'
		,CalibrationID = '". mysql_real_escape_string($calibrationID) ."'
		";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
}

/* All fossils are now properly linked, so we can update all the "subjective" fossil
 * properties (which can vary by calibration) in Link_CalibrationFossil.
 */
foreach($fossil_positions as $pos) {
   // fetch its "subjective" values and store in the link record
   $fossilID = $finalFossilIDs[$pos];
   $newValues = "
          Species = '". mysql_real_escape_string($finalFossilSpeciesNames[$pos]) ."'
         ,MinAge = '". mysql_real_escape_string($_POST["FossilMinAge-$pos"]) ."'
         ,MinAgeType = '". mysql_real_escape_string($_POST["MinAgeType-$pos"]) ."'
         ,MaxAge = '". mysql_real_escape_string($_POST["FossilMaxAge-$pos"]) ."'
         ,MaxAgeType = '". mysql_real_escape_string($_POST["MaxAgeType-$pos"]) ."'
         ,PhyJustificationType = '". mysql_real_escape_string($_POST["PhyJustType-$pos"]) ."'
         ,PhyJustification = '". mysql_real_escape_string($_POST["PhyJustification-$pos"]) ."'
         ,PhyloPub = '". mysql_real_escape_string($finalFossilPhyloPubIDs[$pos]) ."'
   ";
   $query="UPDATE Link_CalibrationFossil 
      SET $newValues
   WHERE
      CalibrationID = '". mysql_real_escape_string($calibrationID) ."'
   AND 
      FossilID = '". mysql_real_escape_string($fossilID) ."'
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
///echo '<a href="/protected/edit_calibration.php?id='. $calibrationID .'">return to editor</a><br/><br/>';

// bounce back to the edit page? or a simple result page
header('Location: https://'. $_SERVER['HTTP_HOST'] .'/protected/edit_calibration.php?id='. $calibrationID .'&result=success');
exit();
?>
