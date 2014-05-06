<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>

<!--
<div class="left-column">
	<div class="link-menu" style="">
		<a href="#basic-search-tips">Basic search tips</a>
		<a href="#advanced-search-tips">Advanced search tips</a>
		<a href="#notification">Notification</a>
	</div>
</div>
-->
<script type="text/javascript">
function toggleAnswer( $anAnswer ) {
	if ($anAnswer) {
		if ($anAnswer.is(':hidden')) {
			$anAnswer.show();
		} else {
			$anAnswer.hide();
		}
		return;
	} 
	var $toggle = $('#toggle-all-answers');
	var $allAnswers = $('.frequently-asked dd');
	if ($toggle.text().indexOf('show') > -1) {
		$allAnswers.show();
		$toggle.text('hide all answers');
	} else {
		$allAnswers.hide();
		$toggle.text('show all answers');
	}
}
$(document).ready(function() {
	$('#toggle-all-answers').unbind('click').click(function() {
		toggleAnswer(null);
	});
	$('.frequently-asked dt').unbind('click').click(function() {
		var $itsAnswer = $(this).next('dd');
		toggleAnswer( $itsAnswer );
	});
});
</script>

<div class="center-column" style="padding-right: 0;">

<h1>
<a href="#" id="toggle-all-answers"
   onclick="return false;" 
   style="float: right; font-size: 0.6em;">show all answers
</a>
Frequently Asked Questions
</h1>

<p>
	<i>Click any question below to see its answer, and click again to hide it. Click the '<strong>show all answers</strong>' link above to show (or hide) all answers.</i>
</p>

<dl class="frequently-asked">
	<dt>Is there a question?</dt>
	<dd>Yes, and here's the answer.</dd>

	<dt>But is there another question?</dt>
	<dd>Yes, there are at least two.</dd>

	<dt>Is there a third question?</dt>
	<dd>Yes, there are three questions in all!</dd>
</dl>

</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('footer.php');
?>
