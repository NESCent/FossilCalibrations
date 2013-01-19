<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>

<?php
// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

//Query the database for higher taxa
$query='SELECT * FROM L_HigherTaxa';
$highertaxa_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

if($_POST['PubID']=="New") {
	
	if($_POST['ShortForm']=="") { echo "<h1 class=\"validation-error\">Incomplete publication information</h1><p><a href=\"createclade1.php\">Return to previous page.</a></p>"; }

//Check to make sure publication isn't already in database
$query='SELECT * FROM publications WHERE FullReference =\''.$_POST['FullCite'].'\'';
$result=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
if (mysql_num_rows($result)==0) {

//Enter publication
$query='INSERT INTO publications (ShortName, FullReference, DOI) VALUES (\''.$_POST['ShortForm'].'\', \''.$_POST['FullCite'].'\', \''.$_POST['DOI'].'\')';
$enter_pub=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$publicationID=mysql_insert_id();
$query='Select * FROM publications WHERE PublicationID='.$publicationID;
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$row = mysql_fetch_array($publication_list);
$publicationID=$row['PublicationID'];


?>
<h1>The following publication was entered in the database</h1>

<table width="100%" border="0">
   <tr>
    <td width="5%" align="center" valign="middle" bgcolor="#999999"><strong>id</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>short form</strong></td>
    <td width="55%" align="center" valign="middle" bgcolor="#999999"><strong>full citation</strong></td>
    <td width="15%" align="center" valign="middle" bgcolor="#999999"><strong>doi</strong></td>
    <td width="10%" align="center" valign="middle" bgcolor="#999999"><strong>date entered</strong></td>
  </tr>
  
  <tr align="left" valign="top">
    <td><?=$row['PublicationID']?></td>
    <td><?=$row['ShortName']?></td>
    <td><?=$row['FullReference']?></td>
    <td><?=$row['DOI']?></td>
    <td><?=$row['DateCreated']?></td>
  </tr>

  
</table>

<?php
//the next three lines are the end of the if statement checking for the publication in the database
} else {
	$row = mysql_fetch_array($result);
 	$publicationID=$row['PublicationID'];}

// the next line ends the if statement about whether a new publication is to be entered
} else { $publicationID=$_POST['PubID']; }

//Retrieve publication info, having gotten the ID from one of three above sources
$query='SELECT * FROM publications WHERE PublicationID >=\''.$publicationID.'\'';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
$pub_info=mysql_fetch_assoc($publication_list);


?>


        <form action="createclade3.php" method="post" name="CreateCalibration" id="CreateCalibration">
          <table width="100%" border="0">
            <tr>
            	<tr><td><h1>Create calibration for <?=$pub_info['ShortName']?> (PubID: <?=$pub_info['PublicationID']?>)<br /><br />Basic information</h1> </td></tr>
               <td><table width="100%" border="0">
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>node name</strong></td>
                  <td width="79%"><input type="text" name="NodeName" id="NodeName">
                    (calibration id: <em>new calibration</em>)
                    <input type="hidden" name="PubID" id="PubID" value="<?=$publicationID?>">
                    </td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>higher taxon</strong></td>
                  <td width="79%"><select name="HigherTaxon">
                  <?php
						while ($row = mysql_fetch_array($highertaxa_list)) {
						echo "<option value=\"".$row['HigherTaxon']."\">".$row['HigherTaxon']."</option>";
						}
				  ?>
                  </select> (choose the most specific applicable group)</td>
                </tr>
                <tr>
                  <td width="21%" align="right" valign="middle"><strong>minimum age (mya)</strong></td>
                  <td width="79%"><input type="text" name="MinAge" id="MinAge" size=4></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>minimum age explanation</strong></td>
                  <td><textarea name="MinAgeJust" id="MinAgeJust" cols="50" rows="5"></textarea></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age (mya)</strong></td>
                  <td><input type="text" name="MaxAge" id="MaxAge" size=4></td>
                </tr>
                <tr>
                  <td align="right" valign="top"><strong>maximum age explanation</strong></td>
                  <td><textarea name="MaxAgeJust" id="MaxAgeJust" cols="50" rows="5"></textarea></td>
                </tr>
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
          </table>
</td>
</tr>
</table>
        </form>



<?php 

//open and print page footer template
require('footer.php');
?>
