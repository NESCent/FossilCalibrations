<?php
// open and load site variables
require('Site.conf');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');


if (!isset($_GET['id'])) {
         die("Missing publication-image ID!");
}

$id = $_GET['id'];
if (empty($id) || !is_numeric($id)) {
         die("Invalid publication-image ID! ($id)");
}

$query = mysql_query("SELECT * FROM publication_images WHERE PublicationID='".$id."'");
$row = mysql_fetch_array($query);
$content = $row['image'];

header('Content-type: image/jpg');
echo $content;
?>
