<!-- end content -->
<? require_once('FCD-helpers.php'); ?>
</div><!-- end of div#inner-main -->
</div><!-- end of div#main -->
<? if (userIsLoggedIn()) { ?>
<a href="/logout.php" style="float: right; color: #999; text-decoration: none; padding: 4px 8px;">logout</a>
<? } else { ?>
<a href="/login.php"  style="float: right; color: #777; text-decoration: none; padding: 4px 8px;">login</a>
<? } ?>
<div id="inner-footer">
  &copy; 2013, Sponsored by: Coquina Press, NESCent. To the extent possible
  under law, the authors have waived all copyright and related or neighboring
  rights to the data in the Fossil Calibrations database. Full 
  <a href="https://github.com/NESCent/FossilCalibrations" target="_blank">source code</a>
  for this website is available under the 
  <a href="https://github.com/NESCent/FossilCalibrations/blob/master/LICENSE.txt" target="_blank">BSD (2-clause) license</a>.
</div>
</body>
</html>
