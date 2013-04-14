<?php 
/*
 * Return markup with a series of search results (fossil calibrations), based on the POSTed query
 *
 * TODO: allow different response types: HTML, JSON?
 */

// open and load site variables
require('Site.conf');

// build search object from GET vars or other inputs (eg, a saved-query ID)
include('build-search-query.php'); 

// Quick test for non-empty string
function isNullOrEmptyString($str){
    return (!isset($str) || ($str == null) || trim($str)==='');
}

// prepare containers for 
$results = array('foo');
$responseType = 'HTML'; // HTML | JSON | ?

// TODO: check for a valid (non-empty) search, else return empty list + message
/*
if (!isset($_POST["TODO"])) {
	echo "{'ERROR': 'empty search!'}";
	return;
}
*/

// connect to mySQL server and select the Fossil Calibration database (using newer 'msqli' interface)
$mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');

// return these results in the requested format
if ($responseType == 'JSON') {
	echo json_encode($results);
	return;
}
// still here? then build HTML markup for the results
foreach($results as $result) 
{ ?>
<div class="search-result">
	<table class="qualifiers" border="0">
		<tr>
			<td width="24">
			\/
			</td>
			<td width="*">
			99% match
			</td>
			<td width="100">
			9&ndash;12 Ma
			</td>
			<td width="120">
			Added Dec 9, 2012
			</td>
		</tr>
	</table>
	<a class="calibration-link">
		<span class="name">Insecta</span>
		<span class="citation">&ndash; Ware, J. 2011.</span>
	</a>
	<br/>
	<div class="optional-thumbnail"><img src="images/insecta.jpeg" /></div>
	<div class="details">
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result.
		Here are some details about the calibration in this result...
		&nbsp;
		<a class="more" href="#">more</a>
	</div>
</div>
<? }

if (count($results) > 10)  // TODO?
{ ?>
<div style="text-align: right; border-top: 1px solid #ddd; font-size: 0.9em; padding-top: 2px;">
	<a href="#">Show more results like this</a>
</div>
<? }

return;
?>


