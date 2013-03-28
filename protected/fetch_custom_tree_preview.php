<?php
   /* This page uses partial form data from the calibration editor to build a
    * temporary tree definition based for this calibration, based on the currently
    * entered hints and taxa. These are marked to be INCLUDED (+) or EXCLUDED (-)
    * when searching for this calibration within the NCBI taxonomy.
    *
    * This is always an AJAX fetch from within the calibration editor page.
    */
   require_once('../FCD-helpers.php');

   // open and load site variables
   require_once('../Site.conf');
/*
?><pre><?= print_r($_POST) ?></pre><?
*/

   // bail out now if there are no hints on either side of this node defintion
   if (!isset($_POST["hintName_A"]) && !isset($_POST["hintName_B"])) {
	?>
	<p style="color: #999; text-align: center;">
		No node-definition hints found! Include (or exclude) NCBI taxa above to help searchers find this calibration.
	</p>
	<? 
	return;
   } 

   // connect to mySQL server and select the Fossil Calibration database 
   // $connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
   // NOTE that to use stored procedures and functions in MySQL, the newer mysqli API is recommended.
   ///mysql_select_db('FossilCalibration') or die ('Unable to select database!');
   $mysqli = new mysqli($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password'], 'FossilCalibration');

   // build up a temporary table of node-definition hints for each side in turn
   $query="CREATE TEMPORARY TABLE preview_hints LIKE node_definitions";
   $result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));

   $query="CREATE TEMPORARY TABLE preview_tree_definition (
		unique_name VARCHAR(80),
		entered_name VARCHAR(80),
		depth SMALLINT DEFAULT 0,
		source_tree VARCHAR(20),
		source_node_id INT(11),
		parent_node_id INT(11),
		is_pinned_node TINYINT(1) UNSIGNED,
		is_public_node TINYINT(1) UNSIGNED,
		calibration_id INT(11),
		is_explicit TINYINT UNSIGNED
	   ) ENGINE = memory";
   $result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));

   $calibrationID = $_POST['CalibrationID'];

   foreach (Array('A', 'B') as $side) {
       // skip this side if no values were submitted
       if (isset($_POST["hintName_$side"])) {
	   $hintNames = $_POST["hintName_$side"];
	   $hintNodeIDs = $_POST["hintNodeID_$side"];
	   $hintNodeSources = $_POST["hintNodeSource_$side"];
	   $hintOperators = $_POST["hintOperator_$side"];
	   $hintDisplayOrders = $_POST["hintDisplayOrder_$side"];
   
	   // assemble values for each row, making all values safe for MySQL
	   $rowValues = Array();
	   $hintCount = count($hintNames);
	   for ($i = 0; $i < $hintCount; $i++) {
		   // check for vital node information before saving
		   if ((trim($hintNames[$i]) == "") || 
		       (trim($hintNodeSources[$i]) == "") || 
		       (trim($hintNodeIDs[$i]) == "")) { 
			   // SKIP this hint, it's incomplete
			   continue;
		   }
		   $rowValues[] = "('". 
			   $calibrationID ."','". 
			   $side ."','". 
			   mysql_real_escape_string($hintNames[$i]) ."','". 
			   mysql_real_escape_string($hintNodeSources[$i])."','". 
			   mysql_real_escape_string($hintNodeIDs[$i]) ."','". 
			   mysql_real_escape_string($hintOperators[$i]) ."','". 
			   mysql_real_escape_string($hintDisplayOrders[$i]) ."')";
	   }
   
	   // make sure we have at least one valid row (hint) to save for this side
	   if (count($rowValues) > 0) {
		   $query="INSERT INTO preview_hints 
				   (calibration_id, definition_side, matching_name, source_tree, source_node_id, operator, display_order)
			   VALUES ". implode(",", $rowValues);
		   $result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
	   }
   
       }
   }
   $query='SELECT * FROM preview_hints';
   $result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
   $hints_data = array();
   while($row=mysqli_fetch_assoc($result)) {
      $hints_data[] = $row;
   }
/*
   foreach( $hints_data as $row ) {
?>
<pre><?= print_r($row) ?></pre>
<?
   }
*/
   mysqli_free_result($result);

   ///$query='CALL getFullNodeInfo( "preview_hints", "preview_tree_definition" )';
   $query='CALL buildTreeDescriptionFromNodeDefinition( "preview_hints", "preview_tree_definition" )';
   $result=mysqli_query($mysqli, $query) or die ('Error in query: '.$query.'|'. mysqli_error($mysqli));
   while(mysqli_more_results($mysqli)) {
	/*?><?= mysqli_next_result($mysqli) ? '.' : '!' ?><?*/
	//mysqli_next_result($mysqli) or die ('Error in next result: '.$query.'|'. mysqli_error($mysqli)); // wait for this to finish
	mysqli_next_result($mysqli);
	mysqli_store_result($mysqli);
   }

   $query='SELECT * FROM preview_tree_definition';
   $result=mysqli_query($mysqli, $query) or die ('Error  in query: '.$query.'|'. mysqli_error($mysqli));
   $included_taxa_data = array();
   while($row=mysqli_fetch_assoc($result)) {
      $included_taxa_data[] = $row;
   }
   mysqli_free_result($result);

  ?><p style="color: #999;">This calibration will match for searches within any of these <?= count($included_taxa_data) ?> NCBI taxa:</p><?

   foreach( $included_taxa_data as $row ) {
	/* ?><pre><?= print_r($row) ?></pre><? */
	?><i><?= $row['unique_name'] ?></i><?
	if ($row['entered_name'] && ($row['entered_name'] != $row['unique_name'])) { 
	    ?>&nbsp; (entered as '<?= $row['entered_name'] ?>')<?
	}
	?><br/><?
   }
?>
