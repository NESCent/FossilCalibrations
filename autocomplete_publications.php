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
$query='SELECT PublicationID AS id, ShortName, FullReference
	FROM publications 
	WHERE FullReference LIKE "%'. $q .'%"'.
	// non-admin users should only see *Published* publication names
	((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? '' :  
		'AND EXISTS(SELECT PublicationStatus FROM calibrations WHERE PublicationID = publications.PublicationID AND PublicationStatus = 4)'
	)
	.'LIMIT 10';

$match_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	

// mysql_num_rows($match_list) 
$result = array();
///while ($row = mysql_fetch_array($match_list)) {
while ($row = mysql_fetch_assoc($match_list)) {
	//array_push($result,...];
	$result[ ] = $row;  // much faster than translation below

	/* SLOW, but more clear and less data
	// rename properties, push each matching term onto the result list
	// NO, if renaming is required, do this in the query above (PublicationID AS id, etc)
	$result[ ] = array( 
		'id'=> $row['PublicationID'], 
		'label'=> $row['ShortName'],
		'value'=> $row['FullReference']
	);
	*/
}

/* static example data 

$items = array(
    "Great Bittern"=>"Botaurus stellaris",
    "Little Grebe"=>"Tachybaptus ruficollis",
    "Black-necked Grebe"=>"Podiceps nigricollis",
    "Little Bittern"=>"Ixobrychus minutus",
    "Black-crowned Night Heron"=>"Nycticorax nycticorax",
    "Heuglin's Gull"=>"Larus heuglini"
);

$result = array();
foreach ($items as $key=>$value) {
	if (strpos(strtolower($key), $q) !== false) {
	    array_push($result, array("id"=>$value, "label"=>$key, "value" => strip_tags($key)));
	}
	if (count($result) > 11) break;
}
*/

// using built-in JSON support, see http://php.net/manual/en/function.json-encode.php
echo json_encode($result);

// TODO: return a list of objects, one per result?
//$test = array( array('color' => "blue", 'size' => 'small', 'count' => 5), array('color' => "orange", 'size' => 'medium', 'count' => -3));
//echo json_encode($test);
//    returns: [{"color":"blue","size":"small","count":5},{"color":"orange","size":"medium","count":-3}]
?>


