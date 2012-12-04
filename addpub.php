<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>


<form action="enterpub.php" method="post" name="EnterPub" id="EnterPub">
<table width="100%" border="0">
  <tr>
    <td><h1>Add a publication to the database<br>Enter data, close resulting window, return to original form and refresh it before proceeding</h1></td>
  </tr>
  <tr><td>
  <table width="100%" border="0">
  <tr>
    <td width="21%" align="right" valign="top"><b>authors, date</b></td>
    <td width="79%"><input type="text" name="ShortForm" id="ShortForm"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>full citation</b></td>
    <td width="79%"><input type="text" name="FullCite" id="FullCite"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>doi</b></td>
    <td width="79%"><input type="text" name="DOI" id="DOI"></td>
  </tr>
    <tr>
    <td width="21%" align="right" valign="top">&nbsp;</td>
    <td width="79%"><label>
                <input type="submit" name="EnterPub" id="EnterPub" value="+">
                enter publication</label></td>
  </tr>
</table>
  </td></tr>
</table>
</form>

<?php 
//open and print page footer template
require('Footer.txt');
?>
