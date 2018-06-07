<?php
$ROOT = '../';
include "{$ROOT}includes/main.php";
include "{$ROOT}includes/ContinuityIterator.php";

$RM = new RAL\ResourceManager();
$iterator = new RAL\ContinuityIterator($RM);

// Which continuity we are reading
$continuity = urldecode($_GET['continuity']);
// Which year are we browsing?
$year = @$_GET['year'];
// Which topic (if any) we are reading
$topic = @$_GET['topic'];
// Finally, what are we searching for?
$query = @$_GET['query'];

if (!empty($_POST)) {
	$query = @$_POST['query'];
	header("Location: " . $iterator->resolveSearch($query));
	exit;
}

$iterator->selectSearch($query, $continuity, $year, $topic);

?>

<!DOCTYPE HTML>
<HTML>
<head>
<?php
	$pagetitle = "[$continuity]";
	$pagedesc = "DEVELOPER MODE";
	include "{$ROOT}template/head.php";
?>
</head>
<body>
<header>
	<h1>RAL Site Search</h1>
	<span>Enquire within.</span>
</header>
<?php include CONFIG_LOCALROOT . "template/Feelies.php" ?>
<?php $iterator->drawSearchBar(@$query); ?><hr />
<?php if ($iterator->render() === false)
	print "Nobody's talking about {$query}!";
?>
<hr /><footer>
	<?php include "{$ROOT}template/Footer.php"; ?>
</footer>
</body>
</HTML>
