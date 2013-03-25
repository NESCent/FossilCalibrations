<?php 
// open and load site variables
require('../Site.conf');

// check for a valid (non-empty) query
if (!isset($_POST["matched_name"])) {
	echo "{'ERROR': 'no name submitted in POST!'}";
	return;
}

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

// look for matching node (NCBI, then FCD) that uses this exact name



// check list of names against this query
// TODO: show un-published names only to logged-in admins/reviewers
$query="SELECT taxonid, 'NCBI' AS source
	FROM NCBI_names
	WHERE name LIKE '". mysql_real_escape_string($_POST["matched_name"]) ."'
    LIMIT 1;";
$match_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
$node_data = mysql_fetch_assoc($match_list);

if (!$node_data) {
    // fall back to FCD names *if* no NCBI node was found
    $query="SELECT taxonid, 'FCD' AS source
        FROM FCD_names
        WHERE name LIKE '". mysql_real_escape_string($_POST["matched_name"]) ."'".
        // non-admin users should only see *Published* publication names
        ((isset($_SESSION['IS_ADMIN_USER']) && ($_SESSION['IS_ADMIN_USER'] == true)) ? "" :  
            " AND is_public_name = 1"
        )
        ." LIMIT 1;";
    $match_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());	
    $node_data = mysql_fetch_assoc($match_list);
}

// return populated fields (or empty, if no matching node was found)
?>
<input type="text" readonly="readonly" style="width: 15%; color: #999; text-align: center;" 
       name="hintNodeSource_<?= $_POST["side"] ?>[]" id="hintNodeSource_<?= $_POST["side"] ?>_<?= $_POST["position"] ?>" value="<?= (!$node_data) ? 'ERROR' : $node_data['source'] ?>" />

<input type="text" readonly="readonly" style="width: 15%; color: #999; text-align: center;" 
       name="hintNodeID_<?= $_POST["side"] ?>[]" id="hintNodeID_<?= $_POST["side"] ?>_<?= $_POST["position"] ?>" value="<?= (!$node_data) ? 'ERROR' : $node_data['taxonid'] ?>" />
