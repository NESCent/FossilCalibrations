<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php 
   require_once('FCD-helpers.php');
	if (!isset($PageTitle)) $PageTitle = 'Fossil Calibration Database (The Dating Site)'; 
?>
<title><?= $PageTitle ?></title>

<link href="/css/site.css" rel="stylesheet" type="text/css">
<link href="/css/fcd-theme/jquery-ui-1.9.2.custom.css" rel="stylesheet" type="text/css">

<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
<link rel="icon" href="/favicon.ico" type="image/x-icon">

<!-- local jQuery 
<script type="text/javascript" src="/js/jquery-1.8.3.js"></script>
-->
<script src="<?= getCurrentScheme() ?>://code.jquery.com/jquery-1.8.3.js"></script>
<script type="text/javascript" src="/js/jquery-ui-1.9.2.custom.js"></script>
<!-- NEWER jQuery and jQuery UI
<script type="text/javascript" src="/js/jquery-1.9.0.js"></script>
<script type="text/javascript" src="/js/jquery-ui-1.10.0.custom.js"></script>
-->
<!--
TODO: <script type="text/javascript" src="/js/jquery-ui-1.9.2.custom.min.js"></script>
-->

<!-- support for jQuery-Tagit plugin -->
<script src="/js/tagit.js"></script>
<script type="text/javascript">
	// prevent IE errors on console.log() calls
	if(!window.console) {
		window.console = {
			log : function(str) {
				// alert(str);
			}
		};
	}
</script>
<link rel="stylesheet" type="text/css" href="/css/tagit-stylish-yellow.css">

</head>
<body>
<div id="header">
    <div id="inner-header">
      <? if (isset($skipHeaderSearch) && $skipHeaderSearch)
	   { ?>
	<div id="skip-header-search">
		[For a basic text search, use the topmost field below]
	</div>
      <? } else { 
		// TODO: add basic search form here ?>
	<form id="simple-search" action="/search.php">
	    <!--<input type="submit" class="search-button" style="" value="Search" />-->
	    <input type="image" class="search-button" style="" value="Search" src="/images/search-button.png" title="Show search results" />
	    <input id="header-search-input" name="SimpleSearch" type="text" class="search-field" style="" placeholder="Search by author, publication, species, etc." value="" />
        </form>
	<script type="text/javascript">
		// prevent IE errors on console.log() calls
		if(!window.console) {
			window.console = {
				log : function(str) {
					// alert(str);
				}
			};
		}

		$(document).ready(function() {
			// bind autocomplete behavior for simple-search field
			$('#header-search-input').autocomplete({
				source: '/autocomplete_all_names.php',
				autoSelect: true,  // recognizes typed-in values if they match an item
				autoFocus: true,
				delay: 20,
				minLength: 3,
				minChars: 3
			});
		});
	</script>
      <? } ?>

	<?php // rotate header logo, randomly for now
 		$logoOptions = Array('dark', 'light', 'bw');
		// $logo = $logoOptions[ rand(0,2) ];
		$nthLogo = isset($_SESSION['nthLogo']) ? $_SESSION['nthLogo'] : 0;  // default value
		$nthLogo = ($nthLogo + 1) % 3;  // modulo 3 (rotates 0,1,2,0....)
		$_SESSION['nthLogo'] = $nthLogo;
	?>
  <? /* <a href="/"><img width="74" height="74" border="0" align="left" src="/images/header-logo-<?= $logoOptions[ $nthLogo ] ?>.png"></a> */ ?>
	<a href="/"><img width="74" height="74" border="0" align="left" src="/images/header-logo-light.png"></a>
	<h3 class="pe-title"><a href="http://palaeo-electronica.org/">Palaeontologia Electronica</a></h3>
	<h2 class="fc-title"><a href="/">Fossil Calibration Database</a></h2>
	<ul id="top-menu">
	    <li>
		<a href="/Browse.php">Browse Calibrations</a>
	    </li>
	    <li>
		<a href="http://palaeo-electronica.org/content/resources" target="_blank">Submit to PE</a>
	    </li>
	    <li>
		<a href="/faq.php">FAQ</a> 
	    </li>
	    <li>
		<a href="/about.php">About</a> 
	    </li>
<? if (userIsAdmin()) { ?>
	    <li>
		<a href="/protected/index.php" style="color: #fcc;" >Admin Dashboard</a> 
	    </li>
<!-- TODO: } else if (userIsReviewer()) {
	    <li>
		<a href="/logout.php" style="color: #ccf;" >Logout (Reviewer)</a> 
	    </li>
-->
<? } else { ?>
	    <li>
		<a href="mailto:contact@calibrations.palaeo-electronica.org?subject=FCD%20feedback">Contact Us</a>
	    </li>
<? } ?>
	</ul>
    </div>
</div>
<div id="main">
<div id="inner-main">
<!-- begin content -->

