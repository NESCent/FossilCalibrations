<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php if (!isset($PageTitle)) $PageTitle = 'Fossil Calibration Database (The Dating Site)'; ?>
<title><?= $PageTitle ?></title>

<link href="/css/site.css" rel="stylesheet" type="text/css">

</head>
<body>
<div id="header">
    <div id="inner-header">
        <div id="simple-search" style="">
	    <!--<input type="submit" class="search-button" style="" value="Search" />-->
	    <input type="image" class="search-button" style="" value="Search" src="/images/search-button.png" />
	    <input type="text" class="search-field" style="" value="Search by author, clade, publication, species, etc." />
        </div>
	<?php // rotate header logo, randomly for now
 		$logoOptions = Array('dark', 'light', 'bw');
		// $logo = $logoOptions[ rand(0,2) ];
		$nthLogo = isset($_SESSION['nthLogo']) ? $_SESSION['nthLogo'] : 0;  // default value
		$nthLogo = ($nthLogo + 1) % 3;  // modulo 3 (rotates 0,1,2,0....)
		$_SESSION['nthLogo'] = $nthLogo;
	?>
	<img width="74" height="74" border="0" align="left"
	     src="/images/header-logo-<?= $logoOptions[ $nthLogo ] ?>.png">
	<h3 class="pe-title"><a href="http://palaeo-electronica.org/">Palaeontologia Electronica</a></h3>
	<h2 class="fc-title"><a href="index.php">Fossil Calibration Database</a></h2>
	<ul id="top-menu">
	    <li>
		<a href="#">About Us</a> 
	    </li>
	    <li>
		<a href="index.php">Find Calibrations</a>
	    </li>
	    <li>
		<a href="createclade1.php">Enter Calibrations</a>
	    </li>
	    <li>
		<a href="http://palaeo-electronica.org/guide2011.pdf">Submit to PE</a>
	    </li>
	    <li>
		<a href="http://en.wikipedia.org/wiki/Help!_(album)" target="_blank">Help</a> 
	    </li>
	</ul>
    </div>
</div>
<div id="main">
<div id="inner-main">
<!-- begin content -->



