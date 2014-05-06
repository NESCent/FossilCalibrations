<?php 
// open and load site variables
require('../Site.conf');

// open and print header template
require('../header.php');

// connect to mySQL server and select the Fossil Calibration database
$connection=mysql_connect($SITEINFO['servername'],$SITEINFO['UserName'], $SITEINFO['password']) or die ('Unable to connect!');
mysql_select_db('FossilCalibration') or die ('Unable to select database!');

//Query the database for publications
$query='SELECT * FROM publications ORDER BY ShortName';
$publication_list=mysql_query($query) or die ('Error  in query: '.$query.'|'. mysql_error());

?>
<script type="text/javascript">
//<![CDATA[ 
	$(document).ready(function() {
		$('#AC_PubID-display').autocomplete({
			source: '/autocomplete_publications.php',
/*
			source: function(request, response) {
				// TODO: pass request.term to fetch page '/autocomplete_publications.php',
				// TODO: call response() with suggested data (groomed for display?)
			},
*/
			autoSelect: true,  // recognizes typed-in values if they match an item
			autoFocus: true,
			delay: 20,
			minLength: 3,
			response: function(event, ui) {
				// another place to manipulate returned matches
				///console.log("RESPONSE > "+ ui.content);
			},
			focus: function(event, ui) {
				///console.log("FOCUSED > "+ ui.item.FullReference);
				// clobber any existing hidden value!?
				$('#AC_PubID').val('');
				// override normal display (would show numeric ID!)
				return false;
			},
			change: function(event, ui) {
				///console.log("CHANGED TO ITEM > "+ ui.item);
				if (!ui.item) {
					// widget blurred with invalid value; clear any 
					// stale values from the UI
					$('#AC_PubID-display').val('');
					$('#AC_PubID').val('');
					$('#AC_PubID-more-info').html('&nbsp;');
				}
			},
			select: function(event, ui) {
				///console.log("CHOSEN > "+ ui.item.FullReference);
				$('#AC_PubID-display').val(ui.item.label);
				$('#AC_PubID').val(ui.item.value);
				$('#AC_PubID-more-info').html(ui.item.FullReference);
				// override normal display (would show numeric ID!)
				return false;
			},
			close: function(event, ui) {
				///console.log("CLOSING VALUE > "+ this.value);
			},
/*
			onFindValue: function() {console.log('onFindValue!');},
			formatItem: function() {console.log('onFindValue!');},
			matchSubset: 1,
			matchContains: 1,
			cacheLength: 10,
			onItemSelect: function() {console.log('onItemSelect!');},
			autoFill: true
*/
			minChars: 4,
		});

		/* alternate test with jQuery-Tagit plugin */
		$('#tagit-PubID').tagit({
			// sortable: 'handle', 
			// sortable: true,
			triggerKeys:['enter', 'tab'], // allow spaces and commas in tag value
			seperatorKeys:[ ], // (sic) don't split auto-added tags on spaces or commas
				// default is ['comma','semicolon']
			select: true,  // uses a select widget to hold values (named after this UL)
			//editable: true,
			tagSource: '/autocomplete_publications.php',
			allowNewTags: false,
			maxTags: 1,

			ignoreMe: true
		});

	});
//]]>
</script>


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
    <td width="79%"><input type="text" name="FullCite" id="FullCite"  size="500" style="width: 100%;"></td>
  </tr>
  <tr>
    <td width="21%" align="right" valign="top"><b>doi</b></td>
    <td width="79%"><input type="text" name="DOI" id="DOI"><p></td>
  </tr>
                  <tr>
                  <td align="right" valign="top"></td>
                  <td>
		    <select name="PubID" id="PubID">
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
                    (<a href="/Show_Publications.php" target="_new">Show all publications</a>)</td>
                </tr>
		<?php
		if(mysql_num_rows($publication_list) > 0) {
			// test of auto-complete widget
		?>
                <tr>
                  <td align="right" valign="top"><b>find existing publication</b></td>
                  <td>
			<input type="text" name="AC_PubID-display" id="AC_PubID-display" value="" />
			<input type="text" name="AC_PubID" id="AC_PubID" value="" readonly="readonly" style="width: 30px; color: #999; text-align: center;"/>
			<div id="AC_PubID-more-info" class="text-excerpt">&nbsp;</p>
		  </td>
                </tr>
                <tr>
                  <td align="right" valign="top"><b>existing pubs (TagIt test)</b></td>
                  <td>
			<ul id="tagit-PubID" name="tagit-PubID[]">
				<li>test value</li>
			</ul>
		  </td>
                </tr>
		<? } ?>

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
require('../footer.php');
?>
