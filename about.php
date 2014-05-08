<?php 
// open and load site variables
require('Site.conf');

// open and print header template
require('header.php');
?>

<div class="left-column">
	<div class="link-menu" style="">
		<a href="#fcd-purpose">Purpose</a>
		<a href="#fcd-support">Support</a>
		<a href="#fcd-people">People</a>
		<a href="mailto:contact@palaeontologia-electronica.org?subject=FCD%20feedback">Contact Us</a>
	</div>
</div>

<div class="center-column" style="padding-right: 0;">

<h1>
About Us
</h1>

<h3 id="fcd-purpose" class="contentheading" style="margin-top: 8px; line-height: 1.25em;">Purpose</h3>
<p>
The mission of the Fossil Calibration Database is to provide vetted fossil
calibration points that can be used for divergence dating by molecular
systematists. These calibrations follow the five best practices outlined by
<a href="http://sysbio.oxfordjournals.org/content/61/2/346.short" target="_blank">Parham et al. (2012)</a>.
</p>

<h3 id="fcd-support" class="contentheading" style="margin-top: 8px; line-height: 1.25em;">Support</h3>
<p>
This project was developed by the Working Group “Synthesizing and Databasing
Fossil Calibrations: Divergence Dating and Beyond”, supported by funding from
the National Evolutionary Synthesis Center (NSF EF-0905606). 
</p>

<h3 id="fcd-people" class="contentheading" style="margin-top: 8px; line-height: 1.25em;">People</h3>
<p>
A large collaborative team has contributed to the conception, implementation,
and maintenance of the Fossil Calibration Database including:
[TODO]
</p>

<img src="/images/meeting-0.jpg"/>
<br/>
<br/>
<img src="/images/meeting-1.jpg"/>
<br/>
<br/>
<img src="/images/meeting-2.jpg"/>
<br/>
<br/>
<img src="/images/meeting-3.jpg"/>
<br/>
<br/>
<img src="/images/meeting-4.jpg"/>

<script type="text/javascript">
$(document).ready(function() {
	$('img[src*="meeting-4.jpg"]').hover(
		function() {
			$(this).attr('src', '/images/meeting-4a.jpg');
		},
		function() {
			$(this).attr('src', '/images/meeting-4.jpg');
		}
	);
});
</script>

</div><!-- END OF center-column -->
<!--<div style="background-color: #fcc; color: #fff; clear: both;">test</div>-->
<?php 
//open and print page footer template
require('footer.php');
?>
