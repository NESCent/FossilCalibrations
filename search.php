<?php 
// open and load site variables
require('Site.conf');

$skipHeaderSearch = true;
// open and print header template
require('header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// build search object from GET vars or other inputs (eg, a saved-query ID)
$search = null;
include('build-search-query.php'); 

// add a timestamp to the form, so we're sure it will refresh properly
$nonce = md5('salt'.microtime());
?>

<form id="advanced-search" Xstyle="border: 1px dashed green;" autocomplete="off">
<input type="hidden" name="nonce" value="<?= $nonce ?>" />

<div id="simple-search-header" 
     style="">
	<select name="SortResultsBy" id="SortResultsBy" style="float: right; margin-top: 3px;">
		<option value="RELEVANCE_DESC" 		<?= ($search['SortResultsBy'] == 'RELEVANCE_DESC') ? 'selected="selected"' : '' ?> >Group by relevance</option>
		<option value="RELATIONSHIP" 		<?= ($search['SortResultsBy'] == 'RELATIONSHIP') ? 'selected="selected"' : '' ?> >Group by relationship</option>
		<option value="DATE_ADDED_DESC" 	<?= ($search['SortResultsBy'] == 'DATE_ADDED_DESC') ? 'selected="selected"' : '' ?> >Sort by date added</option>
		<option value="CALIBRATED_AGE_ASC" 	<?= ($search['SortResultsBy'] == 'CALIBRATED_AGE_ASC') ? 'selected="selected"' : '' ?> >Sort by calibrated age</option>
	</select>

<!--
	<h3 style="display: inline-block; font-size: 1em; font-family: Helvetica,Arial,sans-serif;">Search</h3>
-->
	<input name="SimpleSearch" id="SimpleSearch" type="text" style="width: 420px;" placeholder="Search by author, clade, publication, species,etc." value="<?= htmlspecialchars($search['SimpleSearch']) ?>"/>
	<input type="submit" style="" value="Update" />
</div>

<div class="left-column" style="">

	<!-- faceted search tools -->
	<div id="faceted-search">

<!--
		<h3 style="margin-top: 2px;">Recommended views</h3>
		<div style="text-align: center;">
			<select>
				<option>Recently added calibrations</option>
				<option>Calibrations in clade Mammalia</option>
				<option>Calibrations in clade Aves</option>
				<option>Advanced (using filters below)</option>
			</select>
		</div>
-->
		<h3>Advanced search filters</h3>
		<dl class="filter-list">
			<dt class="optional-filter">
				By <a class="term" href="#">extant (living) species</a>
				<input name="HiddenFilters[]" type="hidden" value="FilterByTipTaxa" <?= in_array('FilterByTipTaxa', $search['HiddenFilters']) ? '' : 'disabled="disabled"' ?> />
				<input name="BlockedFilters[]" type="hidden" value="FilterByTipTaxa" <?= in_array('FilterByTipTaxa', $search['BlockedFilters']) ? '' : 'disabled="disabled"' ?> />
				<div class="blocked-explanation">
					This is incompatible with the <strong>clade</strong> filter below. 
					Clear or hide that filter to use this one.
				</div>
			</dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 60px; text-align: right;">Species&nbsp;A&nbsp;</td>
    <td><input type="text" name="TaxonA" id="TaxonA" style="width: 92%;" placeholder="Proper or common name" value="<?= htmlspecialchars($search['FilterByTipTaxa']['TaxonA']) ?>"></td>
  </tr>
  <tr>
    <td style="text-align: right;">Species&nbsp;B&nbsp;</td>
    <td><input type="text" name="TaxonB" id="TaxonB" style="width: 92%;" placeholder="Proper or common name" value="<?= htmlspecialchars($search['FilterByTipTaxa']['TaxonB']) ?>"> </td>
  </tr>
  <tr>
    <td style="text-align: right; position: relative; top: -4px; font-size: 0.8em;">(optional)</td>
    <td>&nbsp;</td>
  </tr>
</table>
			</dd>

			<dt class="optional-filter">
				By any <a class="term" href="#">clade</a>
				<input name="HiddenFilters[]" type="hidden" value="FilterByClade" <?= in_array('FilterByClade', $search['HiddenFilters']) ? '' : 'disabled="disabled"' ?> />
				<input name="BlockedFilters[]" type="hidden" value="FilterByClade" <?= in_array('FilterByClade', $search['BlockedFilters']) ? '' : 'disabled="disabled"' ?> />
				<div class="blocked-explanation">
					This is incompatible with the <strong>tip taxa</strong> filter above. 
					Clear or hide that filter to use this one.
				</div>
			</dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 60px; text-align: right;">Clade&nbsp;</td>
    <td><input type="text" name="FilterByClade" id="FilterByClade" style="width: 92%;" placeholder="Proper or common name" value="<?= htmlspecialchars($search['FilterByClade']) ?>"></td>
<!--
    <td>
	<input type="submit" name="Submit1" id="Submit1" value="Show all within clade"
	       Xonclick="return testForTipTaxon( TODO );">
    </td>
-->
  </tr>
</table>
			</dd>

			<dt class="optional-filter">
				By age (in <a class="term" href="#">Ma</a>)
				<input name="HiddenFilters[]" type="hidden" value="FilterByAge" <?= in_array('FilterByAge', $search['HiddenFilters']) ? '' : 'disabled="disabled"' ?> />
				<input name="BlockedFilters[]" type="hidden" value="FilterByAge" <?= in_array('FilterByAge', $search['BlockedFilters']) ? '' : 'disabled="disabled"' ?> />
				<div class="blocked-explanation">
					This is incompatible with the <strong>geological time</strong> filter below. 
					Clear or hide that filter to use this one.
				</div>
			</dt>
			<dd>
<table width="100%" border="0" align="left">
  <tr>
    <td style="width: 140px; text-align: right;">Minimum (youngest)&nbsp;</td>
    <td><input type="text" name="MinAge" id="MinAge" style="width: 50%;" value="<?= htmlspecialchars($search['FilterByAge']['MinAge']) ?>">&nbsp;Ma&nbsp;</td>
  </tr>
  <tr>
    <td style="text-align: right;">Maximum (oldest)&nbsp;</td>
    <td><input type="text" name="MaxAge" id="MaxAge" style="width: 50%;" value="<?= htmlspecialchars($search['FilterByAge']['MaxAge']) ?>">&nbsp;Ma&nbsp;</td>
  </tr>
</table>
			</dd>

			<dt class="optional-filter">
				By <a class="term" href="#">geological time</a>
				<input name="HiddenFilters[]" type="hidden" value="FilterByGeologicalTime" <?= in_array('FilterByGeologicalTime', $search['HiddenFilters']) ? '' : 'disabled="disabled"' ?> />
				<input name="BlockedFilters[]" type="hidden" value="FilterByGeologicalTime" <?= in_array('FilterByGeologicalTime', $search['BlockedFilters']) ? '' : 'disabled="disabled"' ?> />
				<div class="blocked-explanation">
					This is incompatible with the <strong>age</strong> filter above. 
					Clear or hide that filter to use this one.
				</div>
			</dt>
			<dd style="margin-left:8px;">
<div style="text-align: center; margin: 4px 0 2px; padding-right: 12px;">
<select name="FilterByGeologicalTime" id="FilterByGeologicalTime">
	<!-- TODO: Build this list on-the-fly from live 'geoltime' table? (Is 30 values too many for a drop-down? maybe autocomplete?) -->
	<option value="" 		<?= ($search['FilterByGeologicalTime'] == '') ? 'selected="selected"' : '' ?> >Choose any period</option>
	<option value="Modern" 		<?= ($search['FilterByGeologicalTime'] == 'Modern') ? 'selected="selected"' : '' ?> >Modern, Quaternary, GSA 1999</option>
	<option value="Calabrian" 	<?= ($search['FilterByGeologicalTime'] == 'Calabrian') ? 'selected="selected"' : '' ?> >Calabrian, Quaternary, GSA 1999</option>
	<option value="Zanclean" 	<?= ($search['FilterByGeologicalTime'] == 'Zanclean') ? 'selected="selected"' : '' ?> >Zanclean, Neogene, GSA 1999</option>
	<option value="Tortonian" 	<?= ($search['FilterByGeologicalTime'] == 'Tortonian') ? 'selected="selected"' : '' ?> >Tortonian, Neogene, GSA 1999</option>
	<option value="Serravallian" 	<?= ($search['FilterByGeologicalTime'] == 'Serravallian') ? 'selected="selected"' : '' ?> >Serravallian, Neogene, GSA 1999</option>
	<option value="Bartonian" 	<?= ($search['FilterByGeologicalTime'] == 'Bartonian') ? 'selected="selected"' : '' ?> >Bartonian, Paleogene, GSA 1999</option>
	<option value="Danian" 		<?= ($search['FilterByGeologicalTime'] == 'Danian') ? 'selected="selected"' : '' ?> >Danian, Paleogene, GSA 1999</option>		     
</select>
</div>
			</dd>
<!--
			<dt style="height: 0px;">&nbsp;</dt>
-->
		</dl>
		<div class="faceted-search-tail"><input type="submit" value="Update Results"/></div>
	</div>
</div>
</form><!-- end of form#advanced-search -->

<div class="right-column" style="margin-top: -37px;">
<?php require('site-announcement.php'); ?>
<!-- news
	<div id="site-news">
		<h3 class="contentheading" style="margin-top: 0;">Site News</h3>
		<div class="news-item">
			<div class="dateline">
				Jan 1, 2013
			</div>
			<div class="headline">
				<a href="#">This is a Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
		<div class="news-item">
			<div class="dateline">
				Dec 29, 2012
			</div>
			<div class="headline">
				<a href="#">And This is a Second, Longer Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
		<div class="news-item">
			<div class="dateline">
				Nov 28, 2012
			</div>
			<div class="headline">
				<a href="#">Yet Another Headline</a>
			</div>
			<div class="excerpt">
				This is an excerpt of the news item, just
				enough to encourage clicking for the full
				story... <a href="#">more</a>
			</div>
		</div>
	</div>
-->

	<div id="site-news">
		<h3 class="contentheading" style="margin-top: 8px; line-height: 1.25em;">Raising the Standard in Fossil Calibration
		</h3>
		<p>
			The Fossil Calibration Database is a curated
			collection of well-justified calibrations, including many published in the
			journal <a href="#">Palaeontologia Electronica</a>. We also promote best practices for
			<a href="#">justifying fossil calibrations</a> and <a href="#">citing calibrations</a> 
			properly.
		</p>
	</div>

</div>

<div class="center-column" style="">
<div id="search-results" Xstyle="border: 1px dashed pink;">

<? // use query built above to search and display results
include('fetch-search-results.php'); 
?>

</div><!-- END of #search-results -->

</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->

<script type="text/javascript">

// check status of filters, using hidden "flag" fields for each
function filterIsHidden(filterName) {
	if ($('input:hidden[name^=HiddenFilters][value=FilterBy'+ filterName +']').is('[disabled]')) {
		return false;
	} else {
		return true;
	}
}
function filterIsBlocked(filterName) {
	if ($('input:hidden[name^=BlockedFilters][value=FilterBy'+ filterName +']').is('[disabled]')) {
		return false;
	} else {
		return true;
	}
}
function filterIsActive(filterName) {
	return !(filterIsHidden(filterName) || filterIsBlocked(filterName));
}

function filterHasNonEmptyValues(filterName) {
	var $flagField = $('input:hidden[value=FilterBy'+ filterName +']');
	var $filterBody = $flagField.closest('dt').next('dd');
	var valuesFound = false;
	$filterBody.find('input, select').each(function() {
		if ($(this).val() != '') {
			valuesFound = true;;
		}
	});
	return valuesFound;
}

function hideFilter(filterName) {
	$('input:hidden[name^=HiddenFilters][value=FilterBy'+ filterName +']').removeAttr('disabled');
}
function blockFilter(filterName) {
	$('input:hidden[name^=BlockedFilters][value=FilterBy'+ filterName +']').removeAttr('disabled');
}
function unblockFilter(filterName) {
	var $flagField = $('input:hidden[name^=BlockedFilters][value=FilterBy'+ filterName +']');
	$flagField.attr('disabled', 'disabled');
	// hide its blocking explanation, if visible
	var $blockMsg = $flagField.nextAll('div.blocked-explanation');
	$blockMsg.hide();
}
function activateFilter(filterName) {
	$('input:hidden[name^=HiddenFilters][value=FilterBy'+ filterName +']').attr('disabled', 'disabled');
	$('input:hidden[name^=BlockedFilters][value=FilterBy'+ filterName +']').attr('disabled', 'disabled');
}

function updateFilterList(option) {
	var $filterArea = $('.filter-list');

	// possibly block some filters, based on what's in others?
	if (option === 'ENFORCE FILTER RULES') {
		// active tip-taxon filter blocks clade
		if (filterIsActive('TipTaxa') && filterHasNonEmptyValues('TipTaxa')) {
			blockFilter('Clade');
		} else {
			unblockFilter('Clade');
		}

		// active clade filter blocks tip taxa
		if (filterIsActive('Clade') && filterHasNonEmptyValues('Clade')) {
			blockFilter('TipTaxa');
		} else {
			unblockFilter('TipTaxa');
		}

		// active min/max-age filter blocks geological time
		if (filterIsActive('Age') && filterHasNonEmptyValues('Age')) {
			blockFilter('GeologicalTime');
		} else {
			unblockFilter('GeologicalTime');
		}

		// active geological-time filter blocks min/max-age
		if (filterIsActive('GeologicalTime') && filterHasNonEmptyValues('GeologicalTime')) {
			blockFilter('Age');
		} else {
			unblockFilter('Age');
		}
	}

	// set initial filter state based on HiddenForm, BlockedForm values
	$filterArea.find('dt.optional-filter').each(function() {
		var $filterHeader = $(this);
		var $filterBody = $filterHeader.next('dd');
		// extract filter short name from value of a hidden flag field (FilterByAge => Age)
		var filterName = $filterHeader.find('input:hidden:eq(0)').val().split('FilterBy')[1];

		//if ($filterHeader.find('input[name^=BlockedFilter]').is('[disabled]')) {
		if (filterIsBlocked(filterName)) {
			$filterHeader.addClass('blocked-filter');
			$filterBody.addClass('blocked-filter');
		} else {
			$filterHeader.removeClass('blocked-filter');
			$filterBody.removeClass('blocked-filter');

			if (filterIsHidden(filterName)) {
				$filterHeader.removeClass('active-filter');
				$filterBody.removeClass('active-filter');
			} else {
				$filterHeader.addClass('active-filter');
				$filterBody.addClass('active-filter');
			}
		}
	});
}

$(document).ready(function() {
	updateFilterList();

	// bind expanding/collapsing advanced search filters
	var $filterArea = $('.filter-list');
	$filterArea.find('dt.optional-filter').unbind('click').click(function() {
		var $filterHeader = $(this);
		var $filterBody = $filterHeader.next('dd');
		var filterName = $filterHeader.find('input:hidden:eq(0)').val().split('FilterBy')[1];
		if (filterIsBlocked(filterName)) { 
			// clicking a locked filter should just show/hide its explanation
			$filterHeader.find('.blocked-explanation').toggle();
			return false;  
		}
		if (filterIsHidden(filterName)) {
			activateFilter(filterName);
		} else {
			hideFilter(filterName);
		}
		updateFilterList('ENFORCE FILTER RULES');
	});

	// bind individual search filters in all filters
	$filterArea.find('input:text, select').unbind('click keyup change').bind('click keyup change', function() {
		console.log('TEST');
		updateFilterList('ENFORCE FILTER RULES');
	});

	// shared autocomplete settings for all taxon widgets
	taxonPickerSettings = {
		source: '/autocomplete_species.php',
		autoSelect: true,  // recognizes typed-in values if they match an item
		autoFocus: true,
		delay: 20,
		minLength: 3,
		// ASSUMES simplest case (value = label)
		change: function(event, ui) {
			console.log("CHANGED TO ITEM > "+ ui.item);
			if (!ui.item) {
				// widget was blurred with invalid value; clear ALL 
				// related (stale) values from the UI!
				$(this).val('');
				$(this).parent().find('[id^=hintNodeSource_], [id^=hintNodeID_]').val('');
				updateFilterList('ENFORCE FILTER RULES');
			} else {
				console.log("FINAL VALUE (not pinging) > "+ ui.item.value);
				/* do we ever need this?
				var $selector = $(this); // SELECT element
				updateHintTaxonValues($selector, ui);
				*/
			}
		},
		select: function(event, ui) {
			console.log("CHOSEN ITEM > "+ ui.item);
			console.log("...ITS VALUE > "+ ui.item.value);
			/* AJAX fetch of corresponding node source/ID?
			var $selector = $(this); // SELECT element
			updateHintTaxonValues($selector, ui);
			*/
		},
		minChars: 3
	};

	$('input[name=TaxonA], input[name=TaxonB], input[name=FilterByClade]').autocomplete(taxonPickerSettings);
});

</script>
<?php 
//open and print page footer template
require('footer.php');
?>
