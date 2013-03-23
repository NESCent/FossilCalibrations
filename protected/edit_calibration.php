<?php 
// open and load site variables
require('../Site.conf');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// Build a complete add/edit form in one page (using stepwise accordion UI)

// stash a nonce (one-time key) to make sure we don't re-submit this form accidentally
$_SESSION['nonce'] = $nonce = md5('salt'.microtime());

// test up front for calibration ID, then gather (or initialize) all values accordingly
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
	$CalibrationID = $_GET['id'];
	$addOrEdit = 'EDIT';
} else {
	$CalibrationID = 0;
	$addOrEdit = 'ADD';
}

/*
 * If we're editing an existing calibration, load all related records (publications, etc)
 */
$calibration_data = null;
$node_pub_data = null;
$all_fossils = null;
$fossil_data = null;
$fossil_species_data = null;
$locality_data = null;
$collection_data = null;
$fossil_pub_data = null;
$phylo_pub_data = null;
$tip_pair_data = null;

if ($addOrEdit == 'EDIT') {
	// retrieve the main calibration record (or die trying)
	$query="SELECT * FROM calibrations WHERE CalibrationID = '".$CalibrationID."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$calibration_data = mysql_fetch_assoc($result);
	if ($calibration_data == false) die("ERROR: Sorry, there's no calibration with this ID: ".$CalibrationID ." Please double-check this URL.");
	mysql_free_result($result);

	// retrieve the main publication for this calibration, if any
	$query="SELECT * FROM publications WHERE PublicationID = '".$calibration_data['NodePub']."'";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
		// TODO: respond more gracefully to missing pub
	$node_pub_data = mysql_fetch_assoc($result);
	mysql_free_result($result);

	// retrieve fossil record(s) for this node, if any (gather all fossils!)
	$query="SELECT * 
      FROM Link_CalibrationFossil 
      JOIN fossils 
         ON fossils.FossilID = Link_CalibrationFossil.FossilID
      WHERE Link_CalibrationFossil.CalibrationID = '".$CalibrationID."'
      ORDER BY FCLinkID";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

   /* Prepare organized storage for any fossils found. We'll gather related
    * records for each and bundle them here.
    */
   $all_fossils = Array();
   while ($f = mysql_fetch_assoc($result)) {
/*
echo "<pre>";
print_r($f);
echo "</pre>";
*/
      $all_fossils[ ] = Array(
         'fossil_data' => $f,
         'fossil_species_data' => null,
         'locality_data' => null,
         'collection_data' => null,
         'fossil_pub_data' => null,
         'phylo_pub_data' => null
      );
   }
	mysql_free_result($result);

   for ($i = 0; $i < count($all_fossils); $i++) {
      $fossil_data = $all_fossils[$i]['fossil_data'];

      // retrieve any fossil-species record matching this fossil, based on its 'Species' (scientific name)
      $query="SELECT * FROM fossiltaxa WHERE TaxonName = '". $fossil_data['Species'] ."'";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $all_fossils[$i]['fossil_species_data'] = mysql_fetch_assoc($result);
      mysql_free_result($result);

      // retrieve fossil locality
      $query="SELECT * FROM localities WHERE LocalityID = '".$fossil_data['LocalityID']."'";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $all_fossils[$i]['locality_data'] = mysql_fetch_assoc($result);
      mysql_free_result($result);

      // retrieve fossil collection
      $query="SELECT * FROM L_CollectionAcro WHERE Acronym = '".$fossil_data['CollectionAcro']."'";
      // TODO: force uniqueness of Acronym field here!?
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $all_fossils[$i]['collection_data'] = mysql_fetch_assoc($result);
      mysql_free_result($result);

      // retrieve fossil pub
      $query="SELECT * FROM publications WHERE PublicationID = '".$fossil_data['FossilPub']."'";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $all_fossils[$i]['fossil_pub_data'] = mysql_fetch_assoc($result);
      mysql_free_result($result);

      // retrieve phylogeny pub
      $query="SELECT * FROM publications WHERE PublicationID = '".$fossil_data['PhyloPub']."'";
      $result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
      $all_fossils[$i]['phylo_pub_data'] = mysql_fetch_assoc($result);
      mysql_free_result($result);
   }

/*
?><pre><?
print_r($all_fossils);
?></pre><?
*/

	// retrieve explicit (directly entered) tip pairs
	$query="SELECT * 
		FROM Link_CalibrationPair 
		JOIN Link_Tips ON Link_CalibrationPair.TipPairsID = Link_Tips.PairID 
		WHERE CalibrationID = '". $CalibrationID ."'
		ORDER BY PairID";
	$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
	$tip_pair_data = array();
	while($row=mysql_fetch_assoc($result)) {
		$tip_pair_data[] = $row;
	}
	mysql_free_result($result);
}

/*
 * Query for controlled lists of misc values
 */

// list of all publication-status values
$query='SELECT * FROM L_PublicationStatus';
$pubstatus_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// list of all calibration-quality values
$query='SELECT * FROM L_CalibrationQuality';
$calibrationquality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// list of all higher taxa
$query='SELECT * FROM L_HigherTaxa';
$highertaxa_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

// list of all collection acronyms
$query='SELECT * FROM L_CollectionAcro ORDER BY Acronym';
$collectionacro_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of fossils
$query='SELECT FossilID, Species, CollectionAcro, CollectionNumber, LocalityID, LocalityName, Country FROM View_Fossils';
//$fossil_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
// TODO: where is this used?

//Retrieve list of localities
$query='SELECT * FROM View_Localities ORDER BY StratumMinAge';
$locality_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of age types
$query='SELECT * FROM L_agetypes';
$agetypes_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of phylogenetic justification types
$query='SELECT * FROM L_PhyloTypes ORDER BY PhyloJustType';
$phyjusttype_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of geological times
$query='SELECT GeolTimeID, Age, Period, t.ShortName, StartAge FROM geoltime g, L_timescales t WHERE g.Timescale=t.TimescaleID ORDER BY StartAge';
$geoltime_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

//Retrieve list of countries
$query='SELECT name FROM L_countries ORDER BY name';
$country_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

?>
<script type="text/javascript">

	$(document).ready(function() {
		// set up the main accordion UI
		$('#edit-steps').accordion({
			collapsible: true,	   // all panels can be collapsed
			heightStyle: 'content'  // conform to contents of each panel
		});

      // set up the fossils accordion UI
      updateFossilAccordion( 'COLLAPSE ALL' );

		// prepare auto-complete widgets
		$('#AC_PubID-display').autocomplete({
			source: '/autocomplete_publications.php',
		     /* source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
		     */
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
	             /* response: function(event, ui) {
				// another place to manipulate returned matches
				console.log("RESPONSE > "+ ui.content);
			},
		      */
			focus: function(event, ui) {
				///console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_PubID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				///console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_PubID-display').val('');
					$('#AC_PubID').val('');
					$('#AC_PubID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				///console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_PubID-display').val(ui.item.label);
				$('#AC_PubID').val(ui.item.value);
				$('#AC_PubID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
		     /* close: function(event, ui) {
				console.log("CLOSING VALUE > "+ this.value);
			},
		      */
			minChars: 3
		});

      // Most remaining widgets might have multiple instances (per fossil), so
      // we should use a separate function that's called whenever (eg) a new
      // fossil is added
      initFossilAutocompleteWidgets();

		initTipTaxaWidgets();


		// init widget groups
		$('#newPublication, #existingPublication').unbind('click').click(updatePublicationWidgets);
		updatePublicationWidgets();

      updateFossilPanelWidgets();

		$('input.deleteTipTaxaPair').unbind('click').click(function() {
			var $itsRow = $(this).closest('tr');
			// de-activate the autocomplete for its widgets
			$itsRow.find('.select-tip-taxa').autocomplete('destroy');
			// remove the entire row
			$itsRow.remove();
			// TODO: re-number the remaining rows?
			var nthPosition = 1;
			$('#tip-taxa-panel tr.tip-taxa-pair').each(function() {
				var $testRow = $(this);
				$testRow.find('.nth-pair').text( nthPosition );
				$testRow.find('input:text[name$=A]').attr('name', 'Pair'+nthPosition+'TaxonA').attr('id', 'Pair'+nthPosition+'TaxonA');;
				$testRow.find('input:text[name$=B]').attr('name', 'Pair'+nthPosition+'TaxonB').attr('id', 'Pair'+nthPosition+'TaxonB');;
				nthPosition++;
			});
		});
		$('#AddTipTaxaPair').unbind('click').click(function() {
			var $itsRow = $(this).closest('tr');
			var $prevRow = $itsRow.prev('tr'); // should always be a valid pair
			// restore default markup for previous row (we'll fix this below)
			$prevRow.find('.select-tip-taxa').autocomplete('destroy');
			$itsRow.before($prevRow.clone(true));
			var $newRow = $itsRow.prev('tr');
			// clear the new row's inputs and show its delete button
			$newRow.find('input:text').val('');
			$newRow.find('.deleteTipTaxaPair').show();
			// update its visible counter and widget names/IDs
			var $itsCounter = $newRow.find('.nth-pair');
			var position = parseInt($itsCounter.text()) + 1;
			$itsCounter.text( position );
			$newRow.find('input:text[name$=A]').attr('name', 'Pair'+position+'TaxonA').attr('id', 'Pair'+position+'TaxonA');;
			$newRow.find('input:text[name$=B]').attr('name', 'Pair'+position+'TaxonB').attr('id', 'Pair'+position+'TaxonB');;
			// init autocomplete behavior (and fix previous row)
			$prevRow.find('.select-tip-taxa').autocomplete(tipTaxaSettings);
			$newRow.find('.select-tip-taxa').autocomplete(tipTaxaSettings);
		});

	});

	// shared autocomplete settings for tip-taxa widgets
	tipTaxaSettings = {
		source: '/autocomplete_species.php',
		autoSelect: true,  // recognizes typed-in values if they match an item
		autoFocus: true,
		delay: 20,
		minLength: 3,
		// ASSUMES simplest case (value = label)
		change: function(event, ui) {
			console.log("CHANGED TO ITEM > "+ ui.item);
			if (!ui.item) {
				// widget was blurred with invalid value; clear any 
				// stale values from the UI!
				$(this).val('');
			}
		},
		select: function(event, ui) {
			console.log("CHOSEN > "+ ui.item.value);
			// TODO: any follow-up action, a la tagging UI?
		},
		minChars: 3
	};

	function initTipTaxaWidgets() {
		$('#tip-taxa-panel .select-tip-taxa').autocomplete(tipTaxaSettings);
	}

   function initFossilAutocompleteWidgets() {
		$('[id^=AC_FossilPubID-display-]').not('.ui-autocomplete-input').autocomplete({
			source: '/autocomplete_publications.php',
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			focus: function(event, ui) {
				///console.log("FOCUSED > "+ ui.item.FullReference);
            var pos = getFossilPosition( event.target );
				// clobber any existing hidden value!?
				$('#AC_FossilPubID-'+ pos).val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				///console.log("CHANGED TO ITEM > "+ ui.item);
            var pos = getFossilPosition( event.target );
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_FossilPubID-display-'+pos).val('');
					$('#AC_FossilPubID-'+pos).val('');
					$('#AC_FossilPubID-more-info-'+pos).html('&nbsp;');
				}
			},
			select: function(event, ui) {
				///console.log("CHOSEN > "+ ui.item.FullReference);
            var pos = getFossilPosition( event.target );
				$('#AC_FossilPubID-display-'+pos).val(ui.item.label);
				$('#AC_FossilPubID-'+pos).val(ui.item.value);
				$('#AC_FossilPubID-more-info-'+pos).html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
			minChars: 3
		});

		$('[id^=AC_PhyloPubID-display-]').not('.ui-autocomplete-input').autocomplete({
			source: '/autocomplete_publications.php',
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			focus: function(event, ui) {
				///console.log("FOCUSED > "+ ui.item.FullReference);
            var pos = getFossilPosition( event.target );
				// clobber any existing hidden value!?
				$('#AC_PhyloPubID-'+pos).val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				///console.log("CHANGED TO ITEM > "+ ui.item);
            var pos = getFossilPosition( event.target );
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_PhyloPubID-display-'+pos).val('');
					$('#AC_PhyloPubID-'+pos).val('');
					$('#AC_PhyloPubID-more-info-'+pos).html('&nbsp;');
				}
			},
			select: function(event, ui) {
				///console.log("CHOSEN > "+ ui.item.FullReference);
            var pos = getFossilPosition( event.target );
				$('#AC_PhyloPubID-display-'+pos).val(ui.item.label);
				$('#AC_PhyloPubID-'+pos).val(ui.item.value);
				$('#AC_PhyloPubID-more-info-'+pos).html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
			minChars: 3
		});

		$('[id^=AC_FossilSpeciesID-display-]').not('.ui-autocomplete-input').autocomplete({
			source: '/autocomplete_species.php',
		     /* source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
		     */
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
	             /* response: function(event, ui) {
				// another place to manipulate returned matches
				console.log("RESPONSE > "+ ui.content);
			},
		      */
			focus: function(event, ui) {
				///console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				//$('#AC_FossilSpeciesID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				///console.log("CHANGED TO ITEM > "+ ui.item);
            var pos = getFossilPosition( event.target );
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_FossilSpeciesID-display-'+pos).val('');
					//$('#AC_FossilSpeciesID').val('');
					//$('#AC_FossilSpeciesID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				///console.log("CHOSEN > "+ ui.item.FullReference);
            var pos = getFossilPosition( event.target );
				$('#AC_FossilSpeciesID-display-'+pos).val(ui.item.label);
				// fetch and display taxon metadata below
				$.ajax( '/fetch_taxon_properties.php', {
					dataType: 'json',
					data: {'calibration_ID': $('#CalibrationID').val(), 'autocomplete_match': ui.item.value},
					success: function(data) {
						$('input[name=ExistingFossilSpeciesID-'+pos+']').val(data.fossiltaxaID);
						$('input[name=ExistingSpeciesName-'+pos+']').val(data.properName);
						$('input[name=ExistingSpeciesCommonName-'+pos+']').val(data.commonName);
						$('input[name=ExistingSpeciesAuthor-'+pos+']').val(data.author);
						$('input[name=ExistingSpeciesPBDBTaxonNum-'+pos+']').val(data.pbdbTaxonNumber);
						if (data.properName === '') {
							$('#author-matched-from-'+pos).html('no match found');
						} else {
							$('#species-matched-from-'+pos).html('matched from table <b>'+ data.SOURCE_TABLE +'</b>');
						}
						if (data.author === '') {
							$('#author-matched-from'+pos).html('no match found');
						} else {
							$('#author-matched-from'+pos).html('matched from table <b>'+ data.AUTHOR_SOURCE_TABLE +'</b>');
						}
					},
					error: function(data) {
						// clear all fields below
                  var pos = getFossilPosition( event.target );
						$('input[name=ExistingFossilSpeciesID-'+pos+']').val('');
						$('input[name=ExistingSpeciesName-'+pos+']').val('');
						$('input[name=ExistingSpeciesCommonName-'+pos+']').val('');
						$('input[name=ExistingSpeciesAuthor-'+pos+']').val('');
						$('input[name=ExistingSpeciesPBDBTaxonNum-'+pos+']').val('');
						$('#species-matched-from-'+pos).html('no match found');
						$('#author-matched-from-'+pos).html('&nbsp;');
					}
				});
				// override normal display (would show numeric ID!)
				return false;
			},
		     /* close: function(event, ui) {
				console.log("CLOSING VALUE > "+ this.value);
			},
		      */
			minChars: 3
		});
   }


	// prepare widget groups and dependent widgets
	function updatePublicationWidgets() {
		if ($('#existingPublication').is(':checked')) {
			$('#pick-existing-pub').show();
			$('#enter-new-pub').hide();
		} else {
			$('#pick-existing-pub').hide();
			$('#enter-new-pub').show();
		}		
	}

   function updateFossilPanelWidgets( ) {
      // do a quick, non-destructive sweep of all panels (eg, after a new
      // fossil panel is added)

      // hide/show mutually exclusive areas based on radio and other options
		updateCollectionAcronymWidgets();
		updateLocalityWidgets();
		updateFossilSpeciesWidgets();
		updateFossilAgeWidgets();
		updateFossilPublicationWidgets();
		updatePhylogenyPublicationWidgets();

      // bind any associated widgets to maintain this behavior
		$('[id^=newCollectionAcronym-], [id^=existingCollectionAcronym-]')
         .unbind('click').click(updateCollectionAcronymWidgets);
		$('[id^=assignedLocality-], [id^=newLocality-], [id^=existingLocality-]')
         .unbind('click').click(updateLocalityWidgets);
		$('[id^=assignedFossilSpecies-], [id^=newFossilSpecies-], [id^=existingFossilSpecies-]')
         .unbind('click').click(updateFossilSpeciesWidgets);
		$('[id^=assignedFossilMinAge-], [id^=newFossilMinAge-], [id^=assignedFossilMaxAge-], [id^=newFossilMaxAge-]')
         .unbind('click').click(updateFossilAgeWidgets);
		$('[id^=assignedFossilPublication-], [id^=newFossilPublication-], [id^=existingFossilPublication-]')
         .unbind('click').click(updateFossilPublicationWidgets);
		$('[id^=newPhylogenyPublication-], [id^=existingPhylogenyPublication-], [id^=repeatFossilPublication-]')
         .unbind('click').click(updatePhylogenyPublicationWidgets);

      // bind fossil-identifier widgets to update its visible name
      // (also triggered from updateCollectionAcronymWidgets)
		$('[id^=CollectionNum-], [id^=CollectionAcro-], [id^=NewAcro-]')
         .unbind('keyup change').bind('keyup change', updateFossilDisplayName);

      // Activate needed autocomplete behaviors, BUT filter out
      // existing widgets using    hasClass('ui-autocomplete-input')...
      initFossilAutocompleteWidgets();
   }

   function updateFossilDisplayName() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         var pos = getFossilPosition( $panel );
         var itsCollectionAcro;
         if ($('#existingCollectionAcronym-'+pos).is(':checked')) {
            itsCollectionAcro = $.trim( $('#CollectionAcro-'+pos).val() );
         } else {
            itsCollectionAcro = $.trim( $('#NewAcro-'+pos).val() );
         }
         var itsCollectionNumber = $.trim( $('#CollectionNum-'+pos).val() );
         var itsDisplayName = itsCollectionAcro +' '+ itsCollectionNumber;
         if (itsDisplayName === ' ') {
            itsDisplayName = 'Unidentified';
         }
         $('#fossil-name-'+pos).html( itsDisplayName );
      });
   }

   function getRelatedFossilPanels( target ) {
      // fossil-panel operations can be specific to one, or apply to all,
      // depending on how (by whom) they are called
      if (target === window) {
         // general call for all panels
         return $('.single-fossil-panel');
      }  else {
         // modify just this panel
         return $(target).closest('.single-fossil-panel');
      }
   }

   function getFossilPosition( target ) {
      // get ordinal position (explicit, since some fossils may have been
      // deleted) for the fossil panel that holds this target
      return $(target).closest('.single-fossil-panel').find('input[name^=fossil_positions]').val();
   }

	function updateCollectionAcronymWidgets() {
      // just one panel, or all?
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=existingCollectionAcronym-]').is(':checked')) {
            $panel.find('[id^=pick-existing-collection-acronym-]').show();
            $panel.find('[id^=enter-new-collection-acronym-]').hide();
         } else {
            $panel.find('[id^=pick-existing-collection-acronym-]').hide();
            $panel.find('[id^=enter-new-collection-acronym-]').show();
         }		
         updateFossilDisplayName(this);
      });
	}
	function updateLocalityWidgets() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=assignedLocality-]').is(':checked')) {
            $panel.find('[id^=pick-existing-locality-]').hide();
            $panel.find('[id^=enter-new-locality-]').hide();
         } else if ($panel.find('[id^=existingLocality-]').is(':checked')) {
            $panel.find('[id^=pick-existing-locality-]').show();
            $panel.find('[id^=enter-new-locality-]').hide();
         } else {
            $panel.find('[id^=pick-existing-locality-]').hide();
            $panel.find('[id^=enter-new-locality-]').show();
         }		
      });
	}
	function updateFossilPublicationWidgets() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=assignedFossilPublication-]').is(':checked')) {
            $panel.find('[id^=pick-existing-fossil-pub-]').hide();
            $panel.find('[id^=enter-new-fossil-pub-]').hide();
         } else if ($panel.find('[id^=existingFossilPublication-]').is(':checked')) {
            $panel.find('[id^=pick-existing-fossil-pub-]').show();
            $panel.find('[id^=enter-new-fossil-pub-]').hide();
         } else {
            $panel.find('[id^=pick-existing-fossil-pub-]').hide();
            $panel.find('[id^=enter-new-fossil-pub-]').show();
         }		
      });
	}
	function updatePhylogenyPublicationWidgets() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=assignedPhylogenyPublication-]').is(':checked')) {
            $panel.find('[id^=pick-existing-phylo-pub-]').hide();
            $panel.find('[id^=enter-new-phylo-pub-]').hide();
         } else if ($panel.find('[id^=existingPhylogenyPublication-]').is(':checked')) {
            $panel.find('[id^=pick-existing-phylo-pub-]').show();
            $panel.find('[id^=enter-new-phylo-pub-]').hide();
         } else if ($panel.find('[id^=repeatFossilPublication-]').is(':checked')) {
            $panel.find('[id^=pick-existing-phylo-pub-]').hide();
            $panel.find('[id^=enter-new-phylo-pub-]').hide();
         } else {
            $panel.find('[id^=pick-existing-phylo-pub-]').hide();
            $panel.find('[id^=enter-new-phylo-pub-]').show();
         }		
      });
	}
	function updateFossilSpeciesWidgets() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=assignedFossilSpecies-]').is(':checked')) {
            $panel.find('[id^=pick-existing-fossil-species-]').hide();
            $panel.find('[id^=enter-new-fossil-species-]').hide();
         } else if ($panel.find('[id^=existingFossilSpecies-]').is(':checked')) {
            $panel.find('[id^=pick-existing-fossil-species-]').show();
            $panel.find('[id^=enter-new-fossil-species-]').hide();
         } else {
            $panel.find('[id^=pick-existing-fossil-species-]').hide();
            $panel.find('[id^=enter-new-fossil-species-]').show();
         }		
      });
	}
	function updateFossilAgeWidgets() {
      var $panels = getRelatedFossilPanels(this);
      $panels.each(function() {
         var $panel = $(this);
         if ($panel.find('[id^=assignedFossilMinAge-]').is(':checked')) {
            $panel.find('[id^=FossilMinAge-]').attr('disabled', 'disabled');
            $panel.find('[id^=AssignedMinAge-]').removeAttr('disabled');
         } else if ($panel.find('[id^=newFossilMinAge-]').is(':checked')) {
            $panel.find('[id^=AssignedMinAge-]').attr('disabled', 'disabled');
            $panel.find('[id^=FossilMinAge-]').removeAttr('disabled');
         }		
         if ($panel.find('[id^=assignedFossilMaxAge-]').is(':checked')) {
            $panel.find('[id^=FossilMaxAge-]').attr('disabled', 'disabled');
            $panel.find('[id^=AssignedMaxAge-]').removeAttr('disabled');
         } else if ($panel.find('[id^=newFossilMaxAge-]').is(':checked')) {
            $panel.find('[id^=AssignedMaxAge-]').attr('disabled', 'disabled');
            $panel.find('[id^=FossilMaxAge-]').removeAttr('disabled');
         }		
      });
	}

   function getNextAvailableFossilPosition() {
      var pos = $('#next-available-fossil-position').val();
      pos = parseInt(pos, 10); // force to integer
      $('#next-available-fossil-position').val(pos +1);  // increment counter
      return pos;
   }

   function addFossil() {
      $('#add-fossil-button').attr('disabled', 'disabled');
      var $fossilPanels = $('.single-fossil-panel');
      if ($('#new-panel-loader').length == 0) {
         // if there's a stale loader, use it!
         $('#fossil-panels').append('<div id="new-panel-loader">...</div>');
      }
      var $loader = $('#new-panel-loader');
      $loader.load(  // load new panel via AJAX
         '/protected/single_fossil_panel.php',
         { 
            calibrationID: $('#CalibrationID').val(),
            totalFossils: $fossilPanels.length,
            position: getNextAvailableFossilPosition(), ///$fossilPanels.length 
            newOrExistingCollection: 'EXISTING',
            matchCollectionAcro: '',
            matchCollectionNumber: '',
            newCollectionInstitution: ''
         },
         function() {
            $loader.replaceWith($loader.children());
            updateFossilAccordion('NEW PANEL');
            // activate fossil-panel behavior
            updateFossilPanelWidgets();

            $('#add-fossil-button').removeAttr('disabled');
         }
      );
   }
   function deleteFossil(position) {
      // remove the doomed panel and header
      /* NOTE: There's no need to unbind/kill widgets in the doomed panel; 
       * apparently, $.remove() unbinds simple events *and* jQuery UI widgets
       */
      $('#fossil-header-'+ position +', #fossil-panel-'+ position).remove();
      updateFossilAccordion('COLLAPSE ALL');
      return false;
   }

   function updateFossilAccordion( option ) {
      // re-initialize this accordion, after panels are added/removed
      var $accordionParent = $('#fossil-panels');
      var panelCount = $accordionParent.find('.single-fossil-panel').length;
      // what section (if any) should be opened after refresh?
      var reopenWithActiveSection = false;
      switch(option) {
         case 'NEW PANEL':
            reopenWithActiveSection = panelCount - 1;
            break;
         case 'SAME PANEL':
            reopenWithActiveSection = $accordionParent.accordion('option', 'active');
            break;
         case 'COLLAPSE ALL':
            reopenWithActiveSection = false;
            break;
      }
      // IF accordion is already present, destroy it
      if ($accordionParent.hasClass('ui-accordion')) {
         $accordionParent.accordion('destroy')
      }
      $accordionParent.accordion({ 
			collapsible: true,	   // all panels can be collapsed
			heightStyle: 'content', // conform to contents of each panel
         active: reopenWithActiveSection
      });
   }

   // utility for QA-testing of multiple fossils
   function sweepForDuplicateIDs() {
      var allIDs = new Array();
      $('*[id]').each(function() {
         var itsID = $(this).attr('id');
         allIDs.push(itsID);
      });
      var IDcount = allIDs.length;
      console.log( '>> '+ IDcount +' IDs found');

      var dupesFound = 0;
      allIDs.sort();
      var lastID = allIDs[0];
      for(var i = 1; i < IDcount; i++) {
         //console.log('... '+ lastID +' == '+ allIDs[i]);
         if (allIDs[i] === lastID) {
            console.log('*** DUPE! '+ lastID);
            dupesFound++;
         }
         lastID = allIDs[i];
      }
      console.log('>> '+ dupesFound +' duplicate IDs found on this page');
   }

   function fetchMatchingFossilProperties(clicked) {
      // AJAX fetch of a matching fossil (if any) and refresh of #fossil-properties panel
      var $fossilPanels = $('.single-fossil-panel');
      var $panel = getRelatedFossilPanels(clicked).eq(0);
      var pos = getFossilPosition( $panel );
      var newOrExistingCollection;
      var itsCollectionAcro;
      var newCollectionInstitution;
      if ($('#existingCollectionAcronym-'+pos).is(':checked')) {
         newOrExistingCollection = 'EXISTING';
         itsCollectionAcro = $.trim( $('#CollectionAcro-'+pos).val() );
         newCollectionInstitution = 'IGNORE_ME';
      } else {
         newOrExistingCollection = 'NEW';
         itsCollectionAcro = $.trim( $('#NewAcro-'+pos).val() );
         newCollectionInstitution = $.trim( $('#NewInst-'+pos).val() );
      }
      var itsCollectionNumber = $.trim( $('#CollectionNum-'+pos).val() );

      // validate and warn if missing/invalid fossil IDs
      if ((itsCollectionAcro === '') || (itsCollectionNumber === '')) {
         alert("Please check for missing collection number or acronym.");
         return false;
      }

      // TODO: force to lower-case?
      var duplicateIdentifierFound = false;
      var proposedIdentifier = itsCollectionAcro +" "+ itsCollectionNumber;
      // check all OTHER panels for the same acro+number
      $fossilPanels.not($panel).each(function() {
         var testPos = getFossilPosition( $(this) );
         var testCollectionAcro;
         if ($('#existingCollectionAcronym-'+testPos).is(':checked')) {
            testCollectionAcro = $.trim( $('#CollectionAcro-'+testPos).val() );
         } else {
            testCollectionAcro = $.trim( $('#NewAcro-'+testPos).val() );
         }
         var testCollectionNumber = $.trim( $('#CollectionNum-'+testPos).val() );
         var testIdentifier = testCollectionAcro +" "+ testCollectionNumber;
         // if the identifiers match, block this (duplicate) fossil
         if (testIdentifier === proposedIdentifier) {
            alert("This fossil is already associated with the calibration.");
            duplicateIdentifierFound = true;
            return false;
         }
      });
      if (duplicateIdentifierFound) {
         return false;
      }

      // bundle these values and try to fetch a matching fossil
      var $loader = $('#fossil-panel-'+pos);
      $loader.load(
         '/protected/single_fossil_panel.php #fossil-panel-'+ pos, 
	 // load just PART of the fetched page
         { 
            calibrationID: $('#CalibrationID').val(),
            totalFossils: $fossilPanels.length,
            position: pos,
            newOrExistingCollection: newOrExistingCollection,
            matchCollectionAcro: itsCollectionAcro,
            matchCollectionNumber: itsCollectionNumber,
            newCollectionInstitution: newCollectionInstitution
         },
         function() {
            $loader.replaceWith($loader.children());
            updateFossilAccordion('SAME PANEL');
            // activate fossil-panel behavior
            updateFossilPanelWidgets();
         }
      );
   }
</script>

<form action="update_calibration.php" method="post" id="edit-calibration" autocomplete="off">
<input type="hidden" name="nonce" value="<?= $nonce; ?>" />
<input type="hidden" name="addOrEdit" value="<?= $addOrEdit; ?>" />
<input type="hidden" id="CalibrationID" name="CalibrationID" value="<?= $CalibrationID; ?>" />

<div style="float: right; text-align: right;">
	<a href="/protected/manage_calibrations.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" value="Save Calibration" />
</div>

<h1><?=($addOrEdit == 'ADD') ? "Add a new calibration" : "Edit an existing calibration (id: ".$CalibrationID.")" ?> </h1>

<div>
  <table id="xxxxxx" width="100%" border="0">
  <tr>
    <td width="15%" align="right" valign="top"><b>publication status</b></td>
    <td width="25%" valign="top">&nbsp;
<!-- Make this a static field for now (inherits from publication's PublicationStatus
	<select name="PublicationStatus">
	  <?php /*
		$currentStatus = testForProp($calibration_data, 'PublicationStatus', '1');  // default is Private Draft
		while ($row = mysql_fetch_array($pubstatus_list)) {
			$thisStatus = $row['PubStatusID'];
			if ($currentStatus == $thisStatus) {
				echo '<option value="'.$row['PubStatusID'].'" selected="selected">'.$row['PubStatus'].'</option>';
			} else {
				echo '<option value="'.$row['PubStatusID'].'">'.$row['PubStatus'].'</option>';
			}			
		}
		*/
	  ?>
	</select>
-->
	  <?php $currentStatus = testForProp($calibration_data, 'PublicationStatus', '1');  // default is Private Draft ?>
	  <input type="hidden" name="PublicationStatus" value="<?= $currentStatus ?>" />
	  <?
		while ($row = mysql_fetch_array($pubstatus_list)) {
			$thisStatus = $row['PubStatusID'];
			if ($currentStatus == $thisStatus) {
				echo '<i>'.$row['PubStatus'].'</i>';
			}			
		}
	  ?>
    </td>
    <td width="10%" rowspan="2" align="right" valign="top">
	<b>admin comments</b>
    </td>
    <td width="50%" rowspan="2" valign="top">
	<textarea name="AdminComments" style="width: 95%; overflow: auto;" rows="3"><?= testForProp($calibration_data, 'AdminComments', '') ?></textarea>
    </td>
  </tr>
  <tr>
    <td width="15%" align="right" valign="top"><b>calibration quality</b></td>
    <td width="25%" valign="top">&nbsp;
	<select name="CalibrationQuality">
	  <?php
		$currentQuality = testForProp($calibration_data, 'CalibrationQuality', '1');  // default is Current
		while ($row = mysql_fetch_array($calibrationquality_list)) {
			$thisQuality = $row['QualityID'];
			if ($currentQuality == $thisQuality) {
				echo '<option value="'.$row['QualityID'].'" selected="selected">'.$row['Quality'].'</option>';
			} else {
				echo '<option value="'.$row['QualityID'].'">'.$row['Quality'].'</option>';
			}			
		}
	  ?>
	</select>
    </td>
  </tr>
  </table>
</div>


<div id="edit-steps">

<h3>1. Cite the initial publication of this calibration</h3>
<div>
  <p><input type="radio" name="newOrExistingPublication" value="EXISTING" id="existingPublication" checked="checked"> <label for="existingPublication">Choose an existing publication</label></input></p>
  <table id="pick-existing-pub" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>enter partial name</b></td>
    <td width="75%">
	  <input type="text" name="AC_PubID-display" id="AC_PubID-display" value="<?= testForProp($node_pub_data, 'ShortName', '') ?>" />
	  <input type="text" name="PubID" id="AC_PubID" value="<?= testForProp($node_pub_data, 'PublicationID', '') ?>" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
                    <a href="/protected/manage_publications.php" target="_new" style="float: right;">Show all publications in a new window</a>
	  <div id="AC_PubID-more-info" class="text-excerpt"><?= testForProp($node_pub_data, 'FullReference', '&nbsp;') ?></p>
    </td>
  </tr>
  </table>
  <p><input type="radio" name="newOrExistingPublication" value="NEW" id="newPublication"> <label for="newPublication">... <b>or</b> enter a new publication into the database</label></input></p>
  <table id="enter-new-pub" class="add-form" width="100%" border="0">
  <tr>
    <td width="25%" align="right" valign="top"><b>short form (author, date)</b></td>
    <td width="75%" ><input type="text" name="ShortForm" id="ShortForm"></td>
  </tr>
  <tr>
    <td align="right" valign="top"><b>full citation</b></td>
    <td><input type="text" name="FullCite" id="FullCite"  size="50" style="width: 95%;"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>doi</b></td>
    <td width="79%"><input type="text" name="DOI" id="DOI">
	<br/><i>You will be able to add a featured image and set its publication status <br/>later, from the <a href="/protected/manage_publications.php" target="_blank">Manage Publications</a> page.</i>
    </td>
  </tr>
</table>
</div>


<h3>2. Provide some basic information</h3>
<div>
	<table width="100%" border="0">
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>node name</strong></td>
                  <td width="79%"><input type="text" name="NodeName" id="NodeName" style="width: 280px;" value="<?= testForProp($calibration_data, 'NodeName', '') ?>">
                    &nbsp;(calibration id: <em><?= ($CalibrationID > 0) ? $CalibrationID : 'new calibration' ?></em>)
                    </td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>higher taxon</strong></td>
                  <td width="79%"><select name="HigherTaxon">
                  <?php
			$currentTaxon = testForProp($calibration_data, 'HigherTaxon', '');
			while ($row = mysql_fetch_array($highertaxa_list)) {
				$thisTaxon = $row['HigherTaxon'];
				if ($currentTaxon == $thisTaxon) {
					echo '<option value="'.$row['HigherTaxon'].'" selected="selected">'.$row['HigherTaxon'].'</option>';
				} else {
					echo '<option value="'.$row['HigherTaxon'].'">'.$row['HigherTaxon'].'</option>';
				}			
			}
		  ?>
                  </select> &nbsp;(choose the most specific applicable group)</td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>minimum age (mya)</strong></td>
                  <td width="79%"><input type="text" name="MinAge" id="MinAge" size=4 style="text-align: right;" value="<?= testForProp($calibration_data, 'MinAge', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>minimum age explanation</strong></td>
                  <td><textarea name="MinAgeJust" id="MinAgeJust" cols="50" rows="5"><?= testForProp($calibration_data, 'MinAgeExplanation', '') ?></textarea></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age (mya)</strong></td>
                  <td><input type="text" name="MaxAge" id="MaxAge" size=4 style="text-align: right;" value="<?= testForProp($calibration_data, 'MaxAge', '') ?>"></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age explanation</strong></td>
                  <td><textarea name="MaxAgeJust" id="MaxAgeJust" cols="50" rows="5"><?= testForProp($calibration_data, 'MaxAgeExplanation', '') ?></textarea></td>
                </tr>
<!--
                <tr>
                  <td align="right" valign="top"><strong>number of Node <?= isset($_POST['NodeCount']) ? $_POST['NodeCount'] : '?' ?> tip taxa pairs to enter</strong></td>
                  <td><input type="text" name="NumTipPairs" id="NumTipPairs" size=3></td>
                </tr>
              <tr>
              <td>&nbsp;</td>
              <td><label>
                <input type="submit" name="CreateNode" id="CreateNode" value="+">
                <b>continue to tip entry</b></label></td>
            </tr>
-->
	</table>
</div>


<h3>3. Describe the fossils used to date this node</h3>
<div>
   <p style="position: relative; top: -6px;">
      <input type="button" id="add-fossil-button" style="float: right; font-size: 0.8em;" value="add fossil" onclick="addFossil(); return false;"/>
      Add one or more fossils that were used in this node calibration.
   </p>
<? // stash a client-side tally to generate unique, incrementing fossil positions (ordinal IDs for all related DOM elements)
   $totalFossils = count($all_fossils); 
?>
   <input type="hidden" id="next-available-fossil-position" name="IGNORE_ME" value="<?= $totalFossils ?>" />
   <div id="fossil-panels">
<? for ($i = 0; $i < $totalFossils; $i++) {
      $fossil_data = $all_fossils[$i]['fossil_data']; 
      $fossil_species_data = $all_fossils[$i]['fossil_species_data'];
      $locality_data = $all_fossils[$i]['locality_data'];
      $collection_data = $all_fossils[$i]['collection_data'];
      $fossil_pub_data = $all_fossils[$i]['fossil_pub_data'];
      $phylo_pub_data = $all_fossils[$i]['phylo_pub_data'];

      $fossilIdentifier = testForProp($fossil_data, 'CollectionAcro', '') .' '. testForProp($fossil_data, 'CollectionNumber', '');
      $isFirstFossil = ($i == 0);
      $isLastFossil = ($i == ($totalFossils - 1));

      include('single_fossil_panel.php');
   } ?>
   </div><!-- END of #fossil-panels (accordion container) -->
</div><!-- END of main fossils section -->


<h3>4. Define tip taxa for the calibrated node</h3>
<!-- NOTE that this also incorporates the old createclade4, which was just validating these taxa -->
<div>

<p>
Place the target node by including or excluding taxa below. Include taxa to indicate MRCA (<i>A+B+...</i>), and exclude taxa to define a stem (<i>A-C</i> or <i>A+B-C</i>) or override the NCBI taxonomy. You can <b>preview the resulting tree</b> to see detailed results below.
</p>

<table id="tip-taxa-panel" width="100%" border="0">
    <tr>
      <td style="background-color: #eee;" width="50%">&nbsp; <b>Side A</b></td>
      <td style="background-color: #eee;" width="50%">&nbsp; <b>Side B</b></td>
    </tr>
    <tr class="tip-taxa-pair">
      <td valign="top">

	<table style="margin:0 auto;" id="tip-taxa-panel" border="0" width="80%">
	    <tbody>

<?php // list any A-side taxa found (if none, just prompt with +/- buttons)
{ ?>
	    <tr>
	      <td align="right" valign="top"><strong style="font-size: 1.5em;">+</strong></td>
	      <td><input autocomplete="off" style="width: 98%;" class="select-tip-taxa ui-autocomplete-input" name="Pair1TaxonA" id="Pair1TaxonA" value="Felis bieti" type="text"></td>

	      <td><input class="deleteTipTaxaPair" style="display: none;" value="delete" type="button"></td>
	    </tr>
<? } ?>

	    <tr>
	      <td colspan="3" style="text-align: center; padding: 10px 0;">
		<input value="include &lt;+&gt; taxon" id="includeTaxon_A" name="includeTaxon_A" type="button">
		 &nbsp;
		 &nbsp;
		 &nbsp;
		<input value="exclude &lt;&ndash;&gt; taxon" id="excludeTaxon_A" name="excludeTaxon_A" type="button">
	      </td>
	    </tr>
	</tbody>
	</table>

      </td>
      <td valign="top">

	<table style="margin:0 auto;" id="tip-taxa-panel" border="0" width="80%">
	    <tbody>
<?php // list any B-side taxa found (if none, just prompt with +/- buttons)
{ ?>
	    <tr>
	      <td align="right" valign="top"><strong style="font-size: 1.5em;">+</strong></td>
	      <td><input autocomplete="off" style="width: 98%;" class="select-tip-taxa ui-autocomplete-input" name="Pair1TaxonA" id="Pair1TaxonA" value="Felis bieti" type="text"></td>

	      <td><input class="deleteTipTaxaPair" style="display: none;" value="delete" type="button"></td>
	    </tr>
	    <tr>
	      <td align="right" valign="top"><strong>—</strong></td>
		  <td><input autocomplete="off" style="width: 98%;" class="select-tip-taxa ui-autocomplete-input" name="Pair6TaxonA" id="Pair6TaxonA" value="Felis bieti" type="text"></td>

	      <td><input class="deleteTipTaxaPair" style="" value="delete" type="button"></td>
	    </tr>
<? } ?>
	    <tr>
	      <td colspan="3" style="text-align: center; padding: 10px 0;">
		<input value="include &lt;+&gt; taxon" id="includeTaxon_B" name="includeTaxon_B" type="button">
		 &nbsp;
		 &nbsp;
		 &nbsp;
		<input value="exclude &lt;&ndash;&gt; taxon" id="excludeTaxon_B" name="excludeTaxon_B" type="button">
	      </td>
	    </tr>
	</tbody>
	</table>

      </td>
    </tr>
<?php
/*
   $NumTipPairs = count($tip_pair_data);
   if ($NumTipPairs == 0) { 
      // add one empty pair to start things off ?>
    <tr class="tip-taxa-pair">
      <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair1TaxonA" id="Pair1TaxonA" value=""></td>
      <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair1TaxonB" id="Pair1TaxonB" value=""></td>
    </tr>
<? } else { 
      for ($i = 1; $i <= $NumTipPairs; $i++) {
	   $index = $i - 1; ?>
       <tr class="tip-taxa-pair">
         <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair<?=$i?>TaxonA" id="Pair<?=$i?>TaxonA" value="<?= $tip_pair_data[$index]['TaxonA'] ?>"></td>
         <td><input style="width: 98%;" type="text" class="select-tip-taxa" name="Pair<?=$i?>TaxonB" id="Pair<?=$i?>TaxonB" value="<?= $tip_pair_data[$index]['TaxonB'] ?>"></td>
       </tr>
   <? }
   } 
*/ ?>

<!-- TODO: remove all references to AddTipTaxaPair, related controls -->
<!--
-->
</table>

<div style="background-color: #eee; border: 1px solid silver; padding: 4px 6px; margin-top: 12px;" width="100%" id="preview-tree"> 
	<div style="" id="preview-tree-legend"> 
		<span style="float: right; font-size: 0.8em; background-color: #ddd; padding: 2px 4px;"> 
		    <span style="color: #555;">Legend:</span> 
		    <i>pinned node (to NCBI)</i> 
		    &nbsp;&bullet;&nbsp; 
		    un-pinned node &nbsp;
		    &nbsp;&bullet;&nbsp; 
		    <i><b>pinned target</b></i> 
		    &nbsp;&bull;&nbsp; 
		    <b>un-pinned target</b> 
		</span> 
		<button style="position: relative; top: -0.9em;">Preview tree for this calibration</button> 
	</div> 

	<!-- TODO: Build this display on-the-fly, (re)generate via AJAX -->
	<i>Carnivora</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; <b>ur-cat</b> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Caniformia</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Felidae</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Eupleridae</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Herpestidae</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Nandiniidae</i> 
	<br> 
	 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; <i>Viverridae</i> 
</div><!-- END of #preview-tree -->

</div><!-- END of final step -->

</div><!-- END of div#edit-steps -->

<div style="float: right; text-align: right; margin-top: 12px;">
	<a href="/protected/manage_calibrations.php">Cancel</a>
	&nbsp;
	&nbsp;
	<input type="submit" value="Save Calibration" />
</div>

</form><!-- END of form#edit-calibration -->


<?php 
//open and print page footer template
require('../footer.php');
?>

