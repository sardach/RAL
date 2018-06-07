<?php namespace RAL;
class Topic {
	/* SQL Data */
	public $Id;
	public $Created;
	public $Continuity;
	public $Content;
	public $Replies;
	public $Year;

	private $Parent;
	private $RM;

	public function __construct($row, $parent) {
		$this->Id = $row['Id'];
		$this->Created = $row['Created'];
		$this->Continuity = $row['Continuity'];
		$this->Content = $row['Content'];
		$this->Replies = $row['Replies'];
		$this->Year = $row['Year'];

		$this->Parent = $parent;
		return $this;
	}
	public function getRM() { return $this->Parent->getRM(); }
	public function getParent() { return $this->Parent; }
	public function resolve() {
		$WROOT = CONFIG_WEBROOT;
		if (CONFIG_CLEAN_URL) return "{$WROOT}view/"
			. rawurlencode($this->Continuity) . '/'
			. rawurlencode($this->Year) . '/'
			. rawurlencode($this->Id);
		else return "{$WROOT}view.php"
			. "?continuity=" . urlencode($this->Continuity)
			. "&year=" . urlencode($this->Year)
			. "&topic=" . urlencode($this->Id);
	}
	public function resolveComposer() {
		$WROOT = CONFIG_WEBROOT;
		if (CONFIG_CLEAN_URL) return "{$WROOT}composer/"
			. rawurlencode($this->Continuity) . '/'
			. rawurlencode($this->Year) . '/'
			. rawurlencode($this->Id);
		else return "{$WROOT}composer.php"
			. "?continuity=" . urlencode($this->Continuity)
			. "&year=" . urlencode($this->Year)
			. "&topic=" . urlencode($this->Id);
	}
	public function renderAsHtml() {
		$content = $this->getContentAsHtml();
		$href = htmlentities($this->resolve());
		$time = strtotime($this->Created);
		$prettydate = date('l M jS \'y', $time);
		$datetime = date(DATE_W3C, $time);
		print <<<HTML
	<section class=post>
		<h3 class=id>{$this->title()}</h3>
		<time datetime="$datetime">$prettydate</time><br />
		<span class=expand>
			<a href="$href">Replies ($this->Replies)</a>
		</span><hr />
		{$content}
	</section>

HTML;
	}
	public function renderAsText() {
		$content = $this->getContentAsText();
		print <<<TEXT
$this->Id. ($this->Created)
$content

TEXT;
	}
	public function renderAsSitemap() {
		$loc = $this->resolve();
print <<<XML
	<url>
		<loc>$loc</loc>
	</url>

XML;
	}
	public function renderSelection($items, $format) {
		switch ($format) {
		case 'html':
			print <<<HTML
	<article>
	<h2>{$this->Parent->title()}</h2><div class=content>
HTML;
			foreach ($items as $i) $i->renderAsHtml();
			say('</div></article>');
		break; case 'text':
			foreach ($items as $i) $i->renderAsText();
		break; case 'json':
			print json_encode($items);
		break; case 'sitemap':
			foreach ($items as $i) $i->renderAsSitemap();
		break; }
	}
	public function renderBanner($format) {
		return $this->Parent->renderBanner($format);
	}
	function title() {
		return "[{$this->Continuity}/{$this->Year}/"
		. "{$this->Id}]";
	}
	public function renderPostButton() {
		$href = $this->resolveComposer();
		print <<<HTML
		<nav class="info-links right">
		<a class=button href="$href">Reply to Topic</a>
		</nav>

HTML;
	}
	public function getContentAsHtml() {
		$bbparser = $this->getRM()->getbbparser();
		$visitor = $this->getRM()->getLineBreakVisitor();
		$bbparser->parse(htmlentities($this->Content));
		$bbparser->accept($visitor);
		return $bbparser->getAsHtml();
	}
	public function getContentAsText() {
		$bbparser = $this->getRM()->getbbparser();
		$bbparser->parse($this->Content);
		return $bbparser->getAsText();
	}
	public function renderComposer($content = '') {
		$action = htmlentities($this->resolveComposer());
		$cancel = htmlentities($this->resolve());

		print <<<HTML
		<h2>Reply to {$this->title()}</h2>
		<form method=POST action="$action" class=composer>
		<div class=textarea>
			<textarea autofocus rows=5 tabindex=1
			maxlength=5000
			placeholder="Contribute your thoughts and desires..."
			name=content>$content</textarea>
		<div class=bbcode-help>
		<header>RAL BBCode Reference</header><ul>
			<li>[aa]</li>
			<li>[b]</li>
			<li>[i]</li>
			<li>[em]</li>
			<li>[url]</li>
			<li>[url=<em>url</em>]</li>
			<li>[color=<em>Color</em>]</li>
			<li>[spoiler]</li>
			<li>[quote]</li>
		</ul>
		<footer>
			<a href=http://www.bbcode.org>What is this?</a>
		</footer>
		</div></div>
		<div class=buttons>
			<a href="$cancel" class="cancel">Cancel</a>
			<button value=preview name=preview
			tabindex=2
			type=submit>Post</button>
		</div>
		</form>

HTML;
	}
	public function renderRobocheck($content = '') {
		$action = htmlentities($this->resolveComposer());
		$cancel = htmlentities($this->resolve());
		$title = "[$this->Name]";

		$reply = new PreviewPost($content, $this);
		$content = htmlspecialchars($content);

		$robocheck = gen_robocheck();
		$robosrc = $robocheck['src'];
		$robocode = $robocheck['id'];
		$height = $robocheck['height'];
		$width = $robocheck['width'];

		print <<<HTML
		<h2>Double Check</h2>
		<p>Before you post, please verify that everything is as you
		intend. If the preview looks okay, continue by verifying your
		humanity and submitting your post.</p>

HTML;

		$reply->renderAsHtml();
		print <<<HTML
		<form method=POST action="$action" class=composer>
		<input type=hidden name=content value="$content">
		<div class=robocheck>
			<img height=$height width=$width src="$robosrc">
			<input name=robocheckid type=hidden value=$robocode>
			<input name=robocheckanswer
			tabindex=1
			placeholder="Verify Humanity"
			autocomplete=off>
		<div class="buttons center">
			<a href="$cancel" class="cancel">Cancel</a>
			<button name=post value=post type=submit
			tabindex=2>Post</button>
		</div></div></form>

HTML;
	}
	public function post($content) {
		$dbh = $this->getRM()->getdb();

		$query = <<<SQL
		INSERT INTO `Replies`
		(`Id`, `Continuity`, `Year`, `Topic`, `Content`) SELECT
		COUNT(*)+1 AS `Id`,
		? AS `Continuity`,
		? AS `Year`,
		? AS `Topic`,
		? AS `Content`
		FROM `Replies` WHERE Continuity=?
		AND YEAR=? AND Topic=?
SQL;
		$stmt = $dbh->prepare($query);
		$stmt->bind_param('siissii', $this->Continuity, $this->Year, $this->Id, $content, $this->Continuity, $this->Year, $this->Id);
		$stmt->execute();

		$query = <<<SQL
		UPDATE `Continuities` SET `Post Count`=`Post Count`+1
		WHERE `Name`=?
SQL;
		$stmt = $dbh->prepare($query);
		$stmt->bind_param('s', $this->Continuity);
		$stmt->execute();

		$query = <<<SQL
		UPDATE `Topics` SET `Replies`=`Replies`+1
		WHERE `Continuity`=? AND `Year`=? AND `Id`=?
SQL;
		$stmt = $dbh->prepare($query);
		$stmt->bind_param('sii', $this->Continuity, $this->Year, $this->Id);
		$stmt->execute();
	}
}
