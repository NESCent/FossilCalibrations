<!-- end content -->
<? require_once('FCD-helpers.php'); ?>
</div><!-- end of div#inner-main -->
</div><!-- end of div#main -->
<div style="color: #fff; text-align: center; padding-top: 5px;">
<? if (userIsLoggedIn()) { ?>
<a href="/logout.php" style="float: right; color: #888; text-decoration: none; padding: 0 8px;">logout</a>
<? } else { ?>
<a href="/login.php" style="float: right; color: #777; text-decoration: none; padding: 0 8px;">login</a>
<? } ?>
  &copy; 2013, Sponsored by: Coquina Press, NESCent
</div>
</body>
</html>
