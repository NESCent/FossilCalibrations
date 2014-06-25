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
		<a href="mailto:contact@calibrations.palaeo-electronica.org?subject=FCD%20feedback">Contact Us</a>
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
<img src="/images/meeting-0.jpg"/>
<p class="caption">
The initial "Fossil Calibrations Meeting," A Biodiversity Synthesis Meeting funded by John D. and Catherine T. MacArthur Foundation funding of the Biodiversity Synthesis Group of the Encyclopedia of Life. The meeting was held at the University of Bristol on September 22nd, 2009. Members include (Front row, left to right) Jason Head, Michael Benton, Tyler Calway, Philip Donoghue, Ken Angielczyk, James Tarver, Jenny Greenwood, Nathan Smith, Peter Makovicky, James Parham (leader). Chris Bell, Jun Inoue; (Back row, left to right): Randall Irmis, Marcel van Tuinen, Christy Hipsley, Ziheng Yang, Daniel Ksepka, Walter Joyce, Patricia Holroyd, Jessica Theodor, Rachel Warnock, Louis Jacobs.
</p>
<br/>
<br/>
<img src="/images/meeting-1.jpg"/>
<p class="caption">
The first Working Group Meeting for the Fossil Calibration Database was held at NESCent March 3rd-6th, 2011. Members include (Left to right): José Patané, James Parham (co-leader), Marcel van Tuinen, Jessica Ware, Elizabeth Hermsen (barely visible), Kristin Lamm, Matthew Carrano, Maria Gandolfo,  Jason Head, Matthew Phillips, Daniel Ksepka (co-leader), Rachel Warnock, Walter Joyce, Michael Benton.
</p>
<br/>
<br/>
<img src="/images/meeting-2.jpg"/>
<p class="caption">
The second Working Group Meeting for the Fossil Calibration Database was held at NESCent March 28th-April 1st, 2012. Members include (Left to right): Kristin Lamm, Rachel Warnock, Daniel Ksepka (co-leader),  Nathan Smith, Randall Irmis, David Polly, Rachel Ware, Elizabeth Hermsen, James Parham (co-leader), José Patané.
</p>
<br/>
<br/>
<img src="/images/meeting-3.jpg"/>
<p class="caption">
The third Working Group Meeting for the Fossil Calibration Database was held at NESCent October 14th-16th, 2012. Members include (Left to right): Karen Cranston, Rachel Warnock, Jessica Ware, Elizabeth Hermsen, Daniel Ksepka (co-leader), Adam Smith, Kristin Lamm, Jim Allman, Marcel van Tuinen, David Polly, James Parham (co-leader), Randall Irmis, Philip Donoghue, Jason Head, Nathan Smith, Michael Benton.
</p>
<br/>
<br/>
<img src="/images/meeting-4.jpg"/>
<p class="caption">
The fourth Working Group Meeting for the Fossil Calibration Database was held at NESCent May 5th-7th, 2014. Members include (Left to right): Jim Allman, Manpreet Kohli, Barbara Dobrin, Karen Cranston, Jennifer Rumford, Daniel Ksepka (co-leader), Chris Torres, Kristin Lamm, Marcel van Tuinen, Matthew Phillips, Jason Head, Nathan Smith, David Polly, Adam Smith, Rachel Warnock.
</p>

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
