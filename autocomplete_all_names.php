<?php 
// open and load site variables
require('Site.conf');

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

// fetch any matching publication names
//$query='SELECT PublicationID AS value, ShortName as label, FullReference  // works with vanilla jQuery UI autocomplete

//$query='SELECT name AS value, name as label, description
// skipping description (currently unused in any case) to filter out duplicate names
$query='SELECT DISTINCT name AS value, name as label
	FROM AC_names_searchable
	WHERE name LIKE "'. $q .'%"'.
	// non-admin users should only see *Published* publication names
	((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? '' :  
		' AND is_public_name = 1'
	)
      .' ORDER BY label' // slows things down, but necessary to choose some names
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


