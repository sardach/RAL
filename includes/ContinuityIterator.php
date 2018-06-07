<?php namespace RAL;
include 'Continuity.php';
include 'Year.php';
include 'Topic.php';
include 'Reply.php';
include 'RecentPost.php';
include 'SearchResult.php';
include 'PreviewPost.php';
class ContinuityIterator {
	// State
	public $Continuity;
	public $Year;
	public $Topic;

	public $Selection = [];
	private $Parent;
	private $RM;

	public function __construct($RM) { $this->RM = $RM; }
	public function select($continuity = null,
	$year = null,
	$topic = null) {
		$dbh = $this->RM->getdb();
		$this->Selection = [];

		if (!$continuity) {
			$query = <<<SQL
			SELECT `Name`, `Post Count`, `Description` FROM
			`Continuities`
SQL;
			$res = $dbh->query($query);
			while ($row = $res->fetch_assoc()) {
				$this->Selection[] = new Continuity($row, $this);
			}
		} else {
			$query = <<<SQL
			SELECT `Name`, `Post Count`, `Description` FROM
			`Continuities` WHERE `Name`=?
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('s', $continuity);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$this->Continuity = new Continuity($row, $this);
			$this->Parent = $this->Continuity;
		} if ($continuity && !$year) {
			$query = <<<SQL
			SELECT `Continuity`, `Year`, COUNT(*) AS Count FROM
			`Topics` WHERE `Continuity`=? GROUP BY `Year` ORDER BY
			`Year` DESC
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('s', $continuity);
			$stmt->execute();
			$res = $stmt->get_result();
			while ($row = $res->fetch_assoc()) {
				$this->Selection[] = new Year($row, $this->Parent);
			}
		} else if ($continuity && $year) {
			$query = <<<SQL
			SELECT `Continuity`, `Year`, COUNT(*) AS Count FROM
			`Topics` WHERE `Continuity`=? AND `Year`=? GROUP BY
			`Year` ORDER BY `Year` DESC
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('si', $continuity, $year);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$this->Year = new Year($row, $this->Parent);
			$this->Parent = $this->Year;
		} if ($continuity && $year && !$topic) {
			$query = <<<SQL
			SELECT `Id`, `Created`, `Continuity`, `Content`
			, `Replies`, `Year` FROM `Topics` WHERE `Continuity`=?
			AND `Year`=? ORDER BY `Created` DESC
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('si', $continuity, $year);
			$stmt->execute();
			$res = $stmt->get_result();
			while ($row = $res->fetch_assoc()) {
				$this->Selection[] = new Topic($row, $this->Parent);
			}
		} else if ($continuity && $continuity && $topic) {
			$query = <<<SQL
			SELECT `Id`, `Created`, `Continuity`, `Content`
			, `Replies`, `Year` FROM `Topics` WHERE `Continuity`=?
			AND `Year`=? AND `Id`=? ORDER BY `Created` DESC
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('sii', $continuity, $year, $topic);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$this->Topic = new Topic($row, $this->Parent);
			$this->Parent = $this->Topic;
		} if ($continuity && $continuity && $topic) {
			$query = <<<SQL
			SELECT `Id`, `Continuity`, `Topic`, `Content`
			, `Created`, `Year` FROM `Replies`
			WHERE `Continuity`=? AND `YEAR`=? AND `Topic`=?
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('sii', $continuity, $year, $topic);
			$stmt->execute();
			$res = $stmt->get_result();
			while ($row = $res->fetch_assoc()) {
				$this->Selection[] = new Reply($row, $this->Parent);
			}
		}
	}
	public function selectSearch($q, $continuity = null,
	$year = null,
	$topic = null) {
		$q = "%{$q}%";
		$dbh = $this->RM->getdb();
		$this->Selection = [];
		if (!$continuity) {
			$query = <<<SQL
			SELECT `Id`, `Continuity`, `Topic`, `Content`
			, `Created`, `Year` FROM `Replies`
			WHERE MATCH(`Content`)
			AGAINST(? WITH QUERY EXPANSION)
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('s', $q);
		}
		$stmt->execute();
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$this->Selection[] = new SearchResult($row, $this);
		}
	}
	public function getRM() { return $this->RM; }
	public function render($format = 'html') {
		if (!$this->Selection[0]) return false;
		$this->Selection[0]->renderSelection(
			$this->Selection,
			$format
		);
	}
	public function renderBanner($format = 'html') {
		$this->Selection[0]->getParent()->renderBanner(
			$format
		);
	}
	public function renderPostButton() {
		$this->Selection[0]->getParent()->renderPostButton();
	}
	public function drawSearchBar($text = null) {
		if (!$this->Selection[0] ||
		$this->Selection[0]->getParent() == $this) {
			$target = $this->resolveSearch();
			print <<<HTML
	<form method=POST action="$target">
		<input name=query
		placeholder="Search RAL"
		value="$text">
		<input type=submit value="Go!">
	</form>
HTML;
		} else {
			$this->Selection[0]->getParent()->drawSearchBar();
		}
	}
	public function renderComposer($content = '') {
		$this->Selection[0]->getParent()->renderComposer($content);
	}
	public function renderRobocheck($content = '') {
		$this->Selection[0]->getParent()->renderRobocheck($content);
	}
	public function drawRSSButton() {
		if (CONFIG_CLEAN_URL) $href = CONFIG_WEBROOT . "rss";
		else $href = CONFIG_WEBROOT . "rss.php";
		$rssimg = CONFIG_WEBROOT . "res/rss.gif";
		print <<<HTML
		<div class="info-links"><a href="$href">
			<img src="$rssimg" alt=RSS title=RSS>
		</a></div>

HTML;
	}
	public function post($content) {
		$this->Selection[0]->getParent()->post($content);
	}
	public function resolve() {
		return $this->Selection[0]->getParent()->resolve();
	}
	public function resolveSearch($query = null) {
		if (!$this->Selection[0]) {
			if (CONFIG_CLEAN_URL && $query)
				return CONFIG_WEBROOT . "search/$query";
			if (CONFIG_CLEAN_URL)
				return CONFIG_WEBROOT . "search";
			if ($query)
				return CONFIG_WEBROOT . "search.php?query=$query";
			else
				return CONFIG_WEBROOT . "search.php";
		}
	}
	public function selectRecent($n = 0) {
		$this->Selection = [];
		$dbh = $this->RM->getdb();
		if (!$n) { $query = <<<SQL
			SELECT `Id`, `Created`, `Continuity`, `Topic`
			, `Content`, `Year` FROM `Replies` ORDER BY `Created`
SQL;
			$res = $dbh->query($query);
		}
		else { $query = <<<SQL
			SELECT `Id`, `Created`, `Continuity`, `Topic`
			, `Content`, `Year` FROM `Replies` ORDER BY `Created`
			DESC LIMIT ?
SQL;
			$stmt = $dbh->prepare($query);
			$stmt->bind_param('i', $n);
			$stmt->execute();
			$res = $stmt->get_result();
		}
		while ($row = $res->fetch_assoc()) {
			$this->Selection[] = new RecentPost($row, $this);
		}
	}
	public function title() {
		return $this->Selection[0]->getParent()->title();
	}
}
