<?php
$ROOT = '../';
include $ROOT.'includes/main.php';
include $ROOT.'includes/fetch.php';
include $ROOT.'includes/post.php';
include $ROOT.'includes/render.php';

// Track which page of timelines we are looking at
$page = $_GET['p'];
// Which timeline we are reading
$timeline = $_GET['timeline'];
// Which topic (if any) we are reading
$topic = $_GET['topic'];
// Whether we are posting or only reading
$postmode = $_GET['postmode'];

// Default to the first page of timelines
if (!isset($page)) $page = 0;

// Posting in a topic
if (isset($_POST['content']) && isset($topic)) {
	$auth = $_COOKIE['auth'];
	$content = $_POST['content'];

	// Strip down the content ; )
	$content = trim($content);
	$content = htmlspecialchars($content);
	if (strlen($content) > CONFIG_RAL_POSTMAXLEN
	|| !strlen($content)
	|| !check_robocheck($_POST['robocheckid'], $_POST['robocheckanswer'])
	|| !($post = create_post($timeline, $topic, $auth, $content))) {
		header("HTTP/1.1 303 See Other");
		if (CONFIG_CLEAN_URL)
			$location = CONFIG_WEBROOT . "dariram";
		else
			$location = CONFIG_WEBROOT . "dariram.php";
		header("Location: $location?$_SERVER[QUERY_STRING]");
		die;
	}
	else {
		notify_listeners('POST', $post);
		header("HTTP/1.1 303 See Other");
		if (CONFIG_CLEAN_URL)
			$location = CONFIG_WEBROOT . "r3";
		else
			$location = CONFIG_WEBROOT . "r3.php";
		header("Location: $location?$_SERVER[QUERY_STRING]");
		die;
	}
}
// Posting to the timeline
else if (isset($_POST['content'])) {
	$auth = $_COOKIE['auth'];
	$content = $_POST['content'];

	// Strip down the content ; )
	$content = trim($content);
	$content = stripslashes($content);
	$content = htmlspecialchars($content);
	if (strlen($content) > CONFIG_RAL_POSTMAXLEN
	|| !strlen($content)
	|| !($topic = create_topic($timeline, $auth, $content))) {
		header("HTTP/1.1 303 See Other");
		if (CONFIG_CLEAN_URL)
			$location = CONFIG_WEBROOT . "dariram";
		else
			$location = CONFIG_WEBROOT . "dariram.php";
		header("Location: $location?$_SERVER[QUERY_STRING]");
		die;
	}
	else {
		notify_listeners('POST', $topic);
		header("HTTP/1.1 303 See Other");
		if (CONFIG_CLEAN_URL)
			$location = CONFIG_WEBROOT . "r3";
		else
			$location = CONFIG_WEBROOT . "r3.php";
		header("Location: $location?$_SERVER[QUERY_STRING]");
		die;
	}
}

// These parameters are in our URL, not in the querystring
if (CONFIG_CLEAN_URL) {
	unset($_GET['timeline']);
	unset($_GET['topic']);
}

$timelines = fetch_timelines();

// Timeline parameter extraction and verification
$i = count($timelines);
for ($i = count($timelines); $i + 1; $i--) {
	if ($timelines[$i]['name'] == $timeline) break;
}
// 404 timelines which do not exist
if ($i < 0) {
	http_response_code(404);
	include "{$ROOT}static/404.php";
	die;
}
$timeline = $timelines[$i]['name'];
$timelinedesc = $timelines[$i]['description'];
?>
<!DOCTYPE HTML>
<HTML>
<head>
<?php
	$title = '';
	if (isset($timeline)) $title .= " $timeline";
	if (isset($topic)) $title .= "/$topic";
	head($title);
?>
</head>
<body>
<div id=timelines class=sidebar>
	<h3>RAL</h3>
	<span id=latency>&nbsp;</span>
<?php
	rendercontinuitynav($page);
	if (CONFIG_CLEAN_URL)
		$a = CONFIG_WEBROOT . "info";
	else
		$a = CONFIG_WEBROOT . "info.php";
	print
<<<HTML
	<a class=help href="$a">About</a>

HTML;
?></div>
<div id=rightpanel>
<?php
	if (isset($topic)) {
		$title = strtoupper("$timeline [$topic]");
	}
	else {
		$title = $title = strtoupper($timeline);
		$subtitle = $timelinedesc;
	}
?>
	<header>
		<h1><?php print $title?></h1>
<?php if (isset($subtitle)) print
<<<HTML
		<em>$subtitle</em>
HTML;
?>
	</header>
	<ol vocab='http://schema.org/' typeof=BreadcrumbList
	class=breadcrumb>
<?php
		$a = CONFIG_WEBROOT;
		print
<<<HTML
		<li property=itemListElement typeof=ListItem>
			<a href='$a' property=item typeof=WebPage>
			<span property=name>RAL</span></a>
			<meta property=position content=1 />
		</li>

HTML;
		if (isset($timeline)) {
			if (CONFIG_CLEAN_URL)
				$a .= "max/$timeline";
			else
				$a .= "max.php?timeline=$timeline";
			print
<<<HTML
		›<li property=itemListElement typeof=ListItem>
			<a href='$a' property=item typeof=WebPage>
			<span property=name>$timeline</span></a>
			<meta property=position content=2 />
		</li>

HTML;
		}
		if (isset($topic)) {
			if (CONFIG_CLEAN_URL)
				$a .= "/$topic";
			else
				$a .= "&topic=$topic";
			print
<<<HTML
		›<li property=itemListElement typeof=ListItem>
			<a href='$a' property=item typeof=WebPage>
			<span property=name>$topic</span></a>
			<meta property=position content=3 />
		</li>

HTML;
		} ?>
	</ol>

	<?php
	// Browsing a topic (reader is in 'expanded' view)
	if (isset($topic)) {
		print
<<<HTML
	<div class="reader expanded"
	data-topic="$topic"
	data-timeline="$timeline">

HTML;
		$posts = fetch_posts($timeline, $topic);
		foreach ($posts as $post) {
			$content = $post['content'];
			$time = date('M d Y', strtotime($post['date']));
			$id = $post['id'];
			print
<<<HTML
		<article data-post=$id id=$id>
			<span class=info>
				<span class=id>[$id]</span>
				<time>$time</time>
			</span>
			<span class=content>$content</span>
		</article>

HTML;
		}
		print
<<<HTML
	</div>

HTML;
		if (isset($postmode)) {
			$q = $_GET;
			unset($q['postmode']);
			$q = http_build_query($q);
			if (CONFIG_CLEAN_URL)
				$target = CONFIG_WEBROOT
				. "max/$timeline/$topic";
			else
				$target = CONFIG_WEBROOT
				. "max.php";
			if (empty($q)) $href = $target;
			else $href = "$target?$q";
			$robocheck = gen_robocheck($_COOKIE['auth']);
			$robosrc = $robocheck['src'];
			$robocode = $robocheck['id'];
			print
<<<HTML
	<form class=reply method=POST action="?$q">
			<textarea rows=5
			maxlength=CONFIG_RAL_POSTMAXLEN
			name=content></textarea>
		<div class=buttons>
			<img src="$robosrc">
			<input name=robocheckid type=hidden value=$robocode>
			<input name=robocheckanswer placeholder="Type the above text" autocomplete=off>
			<input value=Post class=hoverbox type=submit>
			<a href="$href" class="cancel hoverbox">Cancel</a>

		</div>
	</form>

HTML;
		} else {
			$q = $_GET;
			if (empty($q)) $a = '?postmode';
			else {
				$q = http_build_query($q);
				$a = "?$q&postmode";
			}

			print
<<<HTML
	<footer>
	<span class=hoverbox>
		<a href='$a'>Reply to Topic</a>
	</span>

HTML;
			$q = $_GET;
			unset($q['topic']);
			if (CONFIG_CLEAN_URL) {
				$q = http_build_query($q);
				if (empty($q)) $a =  CONFIG_WEBROOT
				. "max/$timeline";
				else $a = CONFIG_WEBROOT
				. "max/$timeline?$q";
			}
			else {
				$q = http_build_query($q);
				$a = "?$q";
			}
			print
<<<HTML
	<span class=hoverbox>
		<a href=$a>Return</a>
	</span>
	</footer>

HTML;
		}
	// Browsing a timeline (reader is in 'timeline' view)
	} else {
		print
<<<HTML
	<div class="reader timeline"
	data-timeline="$timeline">

HTML;
		$q = $_GET;
		unset($q['postmode']);
		$topics = fetch_topics($timeline);
		foreach ($topics as $topic) {
			$content = $topic['content'];
			// Dress up the content
			$time = date("M d Y", strtotime($topic['date']));
			$id = $topic['id'];
			if (!CONFIG_CLEAN_URL)
				$q['topic'] = $id;
			$p = http_build_query($q);

			if (CONFIG_CLEAN_URL && empty($p))
				$a = CONFIG_WEBROOT . "max/$timeline/$id";
			else if (CONFIG_CLEAN_URL)
				$a = CONFIG_WEBROOT . "max/$timeline/$id?$p";
			else
				$a = "?$p";
			print
<<<HTML
		<article data-post=$id id=$id>
			<a href="$a" class=info>
				<span class=id>[$id]</span>
				<time>$time</time>
			› </a>
			<span class=content data-topic=$id>
			$content
			</span>
		</article>

HTML;
		}
		print
<<<HTML
		</div>

HTML;
		if (isset($postmode)) {
			$q = $_GET;
			unset($q['postmode']);
			$q = http_build_query($q);
			if (CONFIG_CLEAN_URL)
				$target = CONFIG_WEBROOT
				. "max/$timeline";
			else
				$target = CONFIG_WEBROOT
				. "max.php";
			if (empty($q)) $href = "$target";
			else $href = "$target?$q";
			$robocheck = gen_robocheck($_COOKIE['auth']);
			$robosrc = $robocheck['src'];
			$robocode = $robocheck['id'];
			print
<<<HTML
	<form class=reply method=POST action="?$q">
			<textarea rows=5
			maxlength=CONFIG_RAL_POSTMAXLEN
			name=content></textarea>
		<div class=buttons>
			<img src="$robosrc">
			<input name=robocheckid type=hidden value=$robocode>
			<input name=robocheckanswer placeholder="Type the above text" autocomplete=off>
			<input value=Post class=hoverbox type=submit>
			<a href="$href" class="cancel hoverbox">Cancel</a>

		</div>
	</form>

HTML;
		} else {
			$q = $_GET;
			if (CONFIG_CLEAN_URL) {
				unset($q['timeline']); unset($q['topic']);
			}
			$q = http_build_query($q);
			if (empty($q)) $a = "?postmode";
			else $a = "?postmode&$q";

			print
<<<HTML
	<footer>
		<span class=hoverbox>
			<a href=$a>Create a Topic</a>
		</span>
	</footer>

HTML;
		}
	}
	?>
</div>
<script src='<?php print CONFIG_WEBROOT?>js/remote.js'></script>
<script src='<?php print CONFIG_WEBROOT?>js/esthetic.js'></script>
<script src='<?php print CONFIG_WEBROOT?>js/render.js'></script>
<script>
var reader = document.getElementById(
	'rightpanel'
).getElementsByClassName('reader')[0];
var timelines = document.getElementById('timelines');
var timelinename = reader.getAttribute('data-timeline');
var topicid = reader.getAttribute('data-topic');

if (topicid !== null)
	subscribetopic(timelinename, topicid, reader);

var collection = timelines.getElementsByClassName('collection')[0];
var leftnav = timelines.getElementsByClassName('leftnav')[0];
var rightnav = timelines.getElementsByClassName('rightnav')[0];

connectnav(collection, leftnav, rightnav);

connectreader(reader);
</script>
</body>
</HTML>
