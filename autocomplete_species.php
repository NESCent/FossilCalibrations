<?php 
// open and load site variables
require('../config.php');

// check for a valid (non-empty) query
if (!isset($_GET["term"])) {
	echo "{'ERROR': 'no term submitted!'}";
	return;
}

$q = strtolower($_GET["term"]);
if (!$q || trim($q) == "") {
	// if string is empty, don't bother checking; just return no matches
	echo json_encode(array());
	return;
}

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// check list of names against this query
// TODO: show un-published names only to logged-in admins/reviewers

// fetch any matching taxon names (need descriptions, too? REQUIRES changing view 'AC_names_taxa')
$query='SELECT DISTINCT name AS value, name as label
	FROM AC_names_taxa
	WHERE name LIKE "'. mysql_real_escape_string($q) .'%"
        ORDER BY label' // slows things down, but necessary to choose some names (eg, Carnivora)
      .' LIMIT 10;';

$match_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

$result = array();
///while ($row = mysql_fetch_array($match_list)) {
while ($row = mysql_fetch_assoc($match_list)) {
	//array_push($result,...];
	$result[ ] = $row;  // much faster than translation below
}

// using built-in JSON support, see http://php.net/manual/en/function.json-encode.php
echo json_encode($result);
?>


