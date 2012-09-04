<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('Header.txt');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

//Query the database for publications
$query='SELECT * FROM publications ORDER BY ShortName';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());


?>


<form action="createclade2.php" method="post" name="EnterPub" id="EnterPub">
<table width="100%" border="0">
  <tr>
    <td><h1>Create calibration Step 1:<br />Specify the calibration publication</h1></td>
  </tr>
  <tr><td>
  <table width="100%" border="0">
  <tr>
    <td width="21%" align="right" valign="top"></td>
    <td width="79%"><p>
        Enter new publication into database
    </p></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>short form (author, date)</b></td>
    <td width="79%" ><input type="text" name="ShortForm" id="ShortForm"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>full citation</b></td>
    <td width="79%"><input type="text" name="FullCite" id="FullCite"  size="50" ></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>doi</b></td>
    <td width="79%"><input type="text" name="DOI" id="DOI"><p></td>
  </tr>
                  <tr>
                  <td align="right" valign="top"></td>
                  <td><select name="PubID" id="PubID">
                	<?php
						if(mysql_num_rows($publication_list)==0){
						?>
                    <option value="0">No publications in database, enter one above</option>
                	<?php
						} else {
							echo "<option value=\"New\">or choose entry from this list</option>";
							while($row=mysql_fetch_assoc($publication_list)) {
							echo "<option value=\"".$row['PublicationID']."\">".$row['ShortName']." (ID:".$row['PublicationID'].")</option>";
							}
						}
					?>
                    </select>
                    (<a href="Show_Publications.php" target="_new">Show complete citations</a>)</td>
                </tr>

    <tr>
    <td width="21%" align="right" valign="top">&nbsp;</td>
    <td width="79%"><P><label>
                <input type="submit" name="Submit" id="Submit" value="+">
                <b>continue to step 2</b></label></P></td>
  </tr>
</table>
  </td></tr>
</table>
</form>

<?php 
//open and print page footer template
require('Footer.txt');
?>
