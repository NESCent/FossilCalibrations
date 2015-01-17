<!-- end content -->
<? require_once('FCD-helpers.php'); ?>
</div><!-- end of div#inner-main -->
</div><!-- end of div#main -->
<? if (userIsLoggedIn()) { ?>
<a href="/logout.php" style="float: right; color: #999; text-decoration: none; padding: 4px 12px; margin-bottom: 40px;">logout</a>
<? } else { ?>
<a href="/login.php"  style="float: right; color: #777; text-decoration: none; padding: 4px 12px; margin-bottom: 40px;">login</a>
<? } ?>
<div id="inner-footer">
Sponsored by <a href="http://palaeo-electronica.org/owner.htm">Coquina Press</a> and 
<a href="http://nescent.org/">NESCent</a>. 
&nbsp;•&nbsp; 
All data is released under a 
<a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0 waiver</a>. To the 
extent possible under law, the authors have waived all copyright and related or 
neighboring rights to the data in the Fossil Calibrations database.  
&nbsp;•&nbsp; 
Full <a href="https://github.com/NESCent/FossilCalibrations">source code</a> for this
website (© 2013-2014, National Evolutionary Synthesis Center) is available
under the <a href="https://github.com/NESCent/FossilCalibrations/blob/master/LICENSE.txt">BSD
(2-clause) license</a>.
</div>
</body>
</html>
