<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('Header.txt');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

?>
<h2 class="contentheading" style="margin-top: 20px;">Search by tip taxa</h2>

<form action="Find_CalibrationsByTips.php" method="GET" name="Find_CalibrationsByTips">
<table width="100%" border="0" align="left">
  <tr>
    <td width="12%" align="right">taxon A </td>
    <td width="25%"><input type="text" name="TaxonA" id="TaxonA"></td>
    <td width="50%">&nbsp;</td>
    <td width="13%">&nbsp;</td>
  </tr>
  <tr>
    <td align="right">taxon B</td>
    <td><input type="text" name="TaxonB" id="TaxonB"></td>
    <td><input type="submit" name="Submit1" id="Submit1" value="Search"></td>
    <td>&nbsp;</td>
  </tr>
</table>
</form>	
<div style="height: 12px; clear: both;">&nbsp;</div>

<h2 class="contentheading" style="clear: both;">Browse</h2>
<div id="browse-tools">
<form action="Find_CalibrationsByFossils.php" method="get" name="FindByAge">
<p>by Time interval (age in Ma)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <input name="FossilMinAge" type="text" size="3">:Min  <input name="FossilMaxAge" type="text" size="3">:Max <input type="submit" value="browse by interval"/> </p>
</form>
<form action="Find_CalibrationsByFossils.php" method="get" name="FindByGeolTIme">
<p>by Geological time:  <select name="Age" id="Age">
<?php
//Retrieve list of geological times
$query='SELECT GeolTimeID, Age, Period, t.ShortName, StartAge 
FROM geoltime g, L_timescales t, localities l 
WHERE g.Timescale=t.TimescaleID AND g.GeolTimeID=l.GeolTime ORDER BY StartAge';
$geoltime_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
						if(mysql_num_rows($geoltime_list)==0){
						?>
                    <option value="0">No geological time in database</option>
                	<?php
						} else {
							mysql_data_seek($geoltime_list,0);
						while($row=mysql_fetch_assoc($geoltime_list)) {
							echo "<option value=\"".$row['Age']."\">".$row['Age'].", ".$row['Period'].", ".$row['ShortName']."</option>";
							}

						}
					?>
                    </select> <input type="submit" value="browse by age"/>
</p>
</form>

<form action="Find_CalibrationsByFossils.php" method="get" name="FindByHigherTaxon">
<p>by clade:   <select name="HigherTaxon" id="HigherTaxon">
<?php
//Retrieve list of geological times
$query='SELECT DISTINCT HigherTaxon FROM View_Calibrations';
$highertaxon_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());
						if(mysql_num_rows($highertaxon_list)==0){
						?>
                    <option value="0">No higher taxa in database</option>
                	<?php
						} else {
							mysql_data_seek($highertaxon_list,0);
						while($row=mysql_fetch_assoc($highertaxon_list)) {
							echo "<option value=\"".$row['HigherTaxon']."\">".$row['HigherTaxon']."</option>";
							}

						}
					?>
                    </select> <input type="submit" value="browse by clade"/>
</p>
</form>
</div><!-- end of #browse-tools -->
<?php 
//open and print page footer template
require('Footer.txt');
?>
