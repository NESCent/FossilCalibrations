<?php 
// open and load site variables
require('../config.php');

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
	Click any question below to see its answer, and click again to hide it. Click the '<i>show all answers</i>' link above to show (or hide) all answers.
</p>

<dl class="frequently-asked">


<dt>
What is the Fossil Calibration Database?
</dt>
<dd>
The Fossil Calibration Database is an open-access resource that curates vetted fossil calibration points. In order to be included in the database, a calibration must pass peer review and meet best practices for phylogenetic and stratigraphic justification. 
</dd>

<dt>
What is a calibration?
</dt>
<dd>
Calibrations provide age constraints for evolutionary trees. Combined with branch length estimates from molecular sequence data, calibrations allow us to infer the ages of branching events. The oldest vetted fossil of a given lineage provides a hard minimum age calibration for the divergence between that lineage and its sister taxon. Fossil evidence can also be used to justify a soft maximum age calibration.
</dd>

<dt>
How do I find calibrations?
</dt>
<dd>
There several ways to search for calibrations.
<ul>
<li>
Enter your clade of interest in search bar on the <em>Browse Calibrations</em> page.
</li>
<li>
Browse the NCBI taxonomic hierarchy by clicking on highlighted taxon names.
</li>
<li>
Advanced Search allows users to enter two taxa using <em>Search by Tip Taxa</em> to find calibrations near the node that unites those taxa. 
</li>
<li>
Advanced Search filters also permit searching for calibrations within a range of ages or geological time periods, by a certain author, or containing clade. 
</li>
</ul>
</dd>

<dt>
What if I can't find a calibration for my clade of interest?
</dt>
<dd>
If no results are found, no calibration meeting the best practices standards has been submitted and reviewed. New contributions are welcome! See <a href="#how-can-i-contribute">“How can I contribute?”</a> for more information. If you are looking for a particular calibration, but aren’t in a position to contribute, please leave us feedback so that we can try and solicit a publication in that area. (Just click the <em>Contact Us</em> link in the site header, or send an email to <a href="mailto:contact@calibrations.palaeo-electronica.org?subject=FCD%20feedback">contact@calibrations.palaeo-electronica.org</a>)
</dd>

<dt>
What should I do if I find an error?
</dt>
<dd>
Browse to the calibration page, then click <em>Comment on this calibration</em> to send feedback.
</dd>

<dt>
How do I download data?
</dt>
<dd>
From any page of search results, click the <em>Download</em> button to download a list of results as JSON or text. From any calibration page, click <em>Download</em> to download the entire calibration as JSON or text. An API is coming!
</dd>

<dt id="how-can-i-contribute">
How can I contribute?
</dt>
<dd>
If you would like to provide calibration for a clade not already present in the database or propose an updated calibration for an existing clade, we would encourage you to write a manuscript that can be submitted to the Fossil Calibrations Series at the journal <em>Palaeontologia Electronica</em> (PE). See the <a href="http://palaeo-electronica.org/content/resources" target="_blank">PE Author Guidelines</a> page for details.
</dd>

<dt>
How do I contact the Database Administration?
</dt>
<dd>
Just click the <em>Contact Us</em> link in the site header, or send an email to <a href="mailto:contact@calibrations.palaeo-electronica.org?subject=FCD%20feedback">contact@calibrations.palaeo-electronica.org</a>
</dd>

<dt>
How can I save a recurring search?
</dt>
<dd>
The simplest way is to use the Browse or Search tools in this site. When your results appear, copy the current URL from the browser’s address bar. This URL can be saved as a bookmark, shared via email, or used as a hyperlink on the web. When clicked, it will repeat the same search (or browse) operation on the latest data.
</dd>

<dt>
How is the Fossil Calibration different than TimeTree?
</dt>
<dd>
<a href="http://www.timetree.org/" target="_blank">TimeTree</a> is a database that stores published divergence time estimates resulting from previous studies. The Fossil Calibrations Database stores vetted fossil calibration points that can be used for new divergence dating analyses and related work.
</dd>

<dt>
How is the data licensed?
</dt>
<dd>
All data in the database is provided under a CC0 waiver, meaning that the data is free of any restrictions on use. Copyright does not apply to scientific facts, so the data in this database is not eligible for copyright protection. For more information, please see the <a href="https://creativecommons.org/choose/zero/" target="_blank">CC0 page at Creative Commons</a> or the <a href="http://datadryad.org/pages/faq#info-cc0" target="_blank">related FAQ</a> on the Dryad data repository.
</dd>

<dt>
How should I cite calibrations from the database?
</dt>
<dd>
<p>
If you use a calibration, please cite the original paper listed at the top of the results page. You can download a PDF of Fossil Calibration Series papers by clicking “view electronic resource”.  
</p>
<p>
The preferred citation for the database itself is listed below, but should not be used in place of citing the original papers when using specific calibrations. 
</p>
<pre>
[Title of Systematic Biology Paper]
</pre>
<p>
In cases where originally reported results have been updated in the database, please cite the original paper and also cite the database as follows:
</p>
<pre>
Fossil Calibrations Database, http://pe_url.org/, accessed on MM/DD/YYYY.
</pre>
</dd>

<dt>
What are the five Best Practices required for calibration points to be accepted into the Fossil Calibrations Database?
</dt>
<dd>
<p>
Best Practices are outlined in a collaborative paper available <a href="http://sysbio.oxfordjournals.org/content/61/2/346.short" target="_blank">here</a>.
</p>
<pre>
Parham, J.F., P.C.J. Donoghue, C.J. Bell, T.D. Calway, J.J. Head, P.A. Holroyd, J.G. Inoue, 
R.B. Irmis, W.G. Joyce, D.T. Ksepka, J.S.L. Patané, N.D. Smith, J.E. Tarver, M. Van Tuinen, 
Z. Yang, K.D. Angielczyk, J. Greenwood, C.A. Hipsley, L. Jacobs, P.J. Makovicky, J. Müller, 
K.T. Smith, J.M. Theodor, R.C.M. Warnock, and M.J. Benton. 2012.  Best practices for 
justifying fossil calibrations. Systematic Biology 61: 346-359. 
</pre>
</dd>

</dl>

</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('footer.php');
?>
