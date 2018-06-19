<?php

class page
{
	public $name = 'Home';
	private $pgen = 0;
	protected $html = "";
	public $bare = false;
	public $lang = 'en';
	private $site = '';
	public $caption = '';
	private $site_start = '';
	private $mustext = array('css' => [], 'js' => [], 'all' => []); // Mandatory resources to include in all pages
	protected $optext = array('css' => [], 'js' => [], 'all' => []); // Optional resources that can be added dynamically
	protected $extpaths = array();
	protected $message = array(); // Prompt & Inline message queue << CARRY INLINE MESSAGES ALSO IN REDIRECT
	protected $rendered = ''; // Variable to hold last generated / inserted text chunk to page temporarily 

	public function __construct($pgen)
	{
		$this->pgen = $pgen;

		// Set basename
		$this->site = conf('basename');
		$this->caption = conf('caption');
		$this->site_start = conf('begin');

		// Set paths
		foreach (['cache', 'template', 'language', 'favicon'] as $path) {
			$p = $path . 'path';
			$this->$p = syspath($path);
		}

		// Set language
		global $me;
		$language = $me->pref('language'); // User preferred language
		if (!$language && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $language = strtolower(explode('-', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0]); // Detected browser language
		$lang_exists = array_map('basename', glob($this->languagepath . '*', GLOB_ONLYDIR)); // Get available language directory names
		if (!$language || !in_array($language, $lang_exists)) $language = conf('language'); // Site default language
		$this->lang = $language;

		$this->copyright = date('Y', $this->site_start) . ((date('Y', $this->site_start) !== date('Y')) ? ' - ' . date('Y') : '') . ' ' . $this->site . ' // ' . $this->lang('base', 'copyright');

		// Set external resource & paths
		$this->mustext = array('css' => ['normalize', 'awesome', 'flexgrid'], 'js' => ['jquery-3', 'plugin'], 'all' => ['base']); // TRY TO SOFTCODE THIS
		$cacpext = $this->cachepath . md5(VRT);

		if (file_exists($cacpext) && (time() - filemtime($cacpext)) > conf('dircache') * 60) unlink($cacpext); // Delete old cache
		if (!file_exists($cacpext)) { // Cache paths if not exists
			$patharr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(VRT));
			foreach ($patharr as $pull => $splfi) {
				$cache[] = $pull;
			}
			$f = new file();
			$f->arrDump($cache, $cacpext);
		}

		$this->extpaths = include($cacpext);

		// Load unfired notifications
		if (isset($_SESSION['message'])) {
			$this->message['prompt'] = $_SESSION['message'];
			unset($_SESSION['message']);
		}
	}

	// Append additional external resource
	public function setext(array $incl = array())
	{
		// Include declared resources
		if (!empty($incl) && array_depth($incl) == 2) {
			$newext = array_merge_recursive($this->optext, $incl);

			foreach ($newext as $key => $value) {
				$setext[$key] = array_unique($value);
			}
			$this->optext = $setext;
			return true;
		}
	}

	// External resource includes builder
	private function makeInc($ext)
	{
		foreach ($ext as $type => $grp) {
			foreach ($grp as $name) {
				$paths = $this->getPath($name, $type);
				foreach ($paths as $v) {
					if ($type === 'css') $links[] = $this->htag('link', '', ['type' => 'text/css', 'rel' => 'stylesheet', 'href' => $v]);
					if ($type === 'js') $links[] = $this->htag('script', '', ['type' => 'text/javascript', 'src' => $v], 0);
					//$links[] = $this->render('incl_'.$type,$v);
					//$template .= $this->render('incl_'.$type,$v); // Don't include to page here, need to filter for duplicates
				}
			}
		}
		if (!empty($links)) {// Track duplicacy
			// THIS IS DOING FOR EXACT PATH, MAY BE CAN THINK OF SAME RESOURCE WITH DIFFERENT PATH
			if (DEV) {
				if (count($links) !== count(array_unique($links))) {
					$diff_links = array_unique(array_diff_assoc($links, array_unique($links))); // Get duplicated links
					foreach ($diff_links as $dl) $dls[] = substr(trim(basename($dl)), 0, -2); // ANY BETTER IDEA???
					$dls = '\'' . implode(', ', $dls) . '\'';
					$this->message['inline'][] = [(count($links) - count(array_unique($links))) . ' duplicate resource/s ' . $dls . ' filtered out.', 'dev'];
				}
			}
			$links = array_unique($links); // Filterout Duplicate Links
			return implode('', $links);
		}
		return false;
	}

	// External resource path finder
	private function getPath($name = '', $ext = '', $exact = 0)
	{
		$path = array();
		foreach ($this->extpaths as $file) {
			//$file = str_replace('\\', '/', $file);
			$xact = $exact ? (basename($file, '.' . $ext) === $name) : (strpos(basename($file, '.' . $ext), $name) !== false);
			if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === $ext && $xact) {
				$path[] = str_replace(VRT, '', $file);
			}
		}

		if (empty($path) && DEV) {
			$this->message['inline'][] = ['External resource "' . $name . '" (' . $ext . ') not found.', 'dev'];
		}
		if (count($path) > 1) { // Multiple resources found, remove duplicates if any
			foreach ($path as $node => $rez) {
				//see($path);
				//$dotext = (!empty($ext)) ? '.'.$ext : '';
				if (in_array(pathinfo($rez, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($rez, PATHINFO_FILENAME) . '.min.' . pathinfo($rez, PATHINFO_EXTENSION), $path)) unset($path[$node]); // Keep compressed only
			}
		}
		return array_values($path);
	}

	// Add inline messages to show up in page
	public function message($message = '', $type = 'error', $sticky = 0, $stay = 5, $render = 'prompt')
	{ // Types are error, info, success (and dev: for inline only)
		// No user input, sanitation not needed.
		if (empty($message)) return false;
		if (is_numeric($type)) $type = ['error', 'success', 'info', 'dev'][$type];
		$this->message[$render][] = ($render === 'prompt') ? [$message, $type, $sticky, $stay] : [$message, $type];
		return true;
	}

	// Handle the language variables
	public function lang($file = 'base', $var = '')
	{
		if (empty($file)) {
			$this->message['inline'][] = ['Language file not defined.', 'dev'];
			return false;
		}

		if (is_string($file)) $file = (array)$file;
		$comblang = $rtrn = array(); // Empty combined language file array

		foreach ($file as $lfile) {
			if (!file_exists($this->languagepath . $this->lang . '/' . $lfile . '.php')) {
				$this->message['inline'][] = ['Language file \'' . $lfile . '\' not found.' . $this->languagepath . $this->lang . '/' . $lfile . '.php', 'dev'];
			} else {
				$langdef = include($this->languagepath . $this->lang . '/' . $lfile . '.php');
				$comblang = array_merge($comblang, $langdef);
			}
		}

		if (empty($var)) return $comblang; // If variables not defined return all

		if (is_string($var)) $var = (array)$var;

		foreach ($var as $lvar) $rtrn[$lvar] = $comblang[$lvar]; // Filterout required variables only

		if (count($rtrn) === 1) $rtrn = reset($rtrn);
		return ($rtrn);
	}

	// Programatic HTML Generator
	public function htag($tag = 'br', $content = '', $arg = array(), $multiline = true, $xhtml = false)
	{
		$void_tag = ['area', 'base', 'br', 'col', 'hr', 'img', 'input', 'link', 'meta', 'param', 'command', 'keygen', 'source']; // HTML5 Compliant Void Tags
		if (!empty($arg)) { // && !in_array($tag, ['br','hr']))
			$decl = array();
			foreach ($arg as $attrib => $value) {
				if (is_array($value)) $value = implode(', ', $value);
				if (is_numeric($attrib)) {
					//$attrib = array_flip($attrib); Doesn't work, as value becomes 0
					$attrib = $value;
					$value = '';
				}
				$value = (!$value) ? '' : '="' . $value . '"';
				$decl[] = $attrib . $value;
			}
			$decl = empty($decl) ? '' : ' ' . implode(' ', $decl);
		}
		if (in_array($tag, $void_tag)) {
			$multiline = false; // Force oneline for void tags
			$startclose = $content = '';
			$endopen = $xhtml ? ' /' : ''; // XHTML compatibility, false by default
		} else {
			$startclose = '>';
			$endopen = '</' . $tag;
		}

		$eol = $multiline ? PHP_EOL : '';
		return '
<' . $tag . $decl . $startclose . $content . $eol . $endopen . '>';
	}

	// Template based HTML Generator
	public function render($template_name, array $vars = array(), array $lang = array(), array $ext = array())
	{
		if (file_exists($this->templatepath . $template_name . '.tpl')) {
			$template = file_get_contents($this->templatepath . $template_name . '.tpl');

			// Handle the language variables
			if (is_array($lang) && !empty($lang)) {
				foreach ($lang as $key => $value) {
					if (is_numeric($key)) { // Sequential entry, load lang file
						$vars = array_merge($vars, $this->lang($value));
					} else { // Associative entry. Defined lang. Merge directly.
						$vars[$key] = $value;
					}
				}
			}

			// Place variables in template
			foreach ($vars as $key => $value) {
				$value = str_replace('{{' . $key . '}}', $value, $value);
				$template = str_replace('{{' . $key . '}}', $value, $template);
			}

			// Blankout all the unassigned variables
			$template = preg_replace('/\\{{2}(.*?)\\}{2}/', '', $template);
			// Include external resources
			//if(is_array($ext) && !empty($ext)) $template .= $this->makeInc($ext); // Don't include upfront
			if (is_array($ext) && !empty($ext)) $this->optext = array_unique(array_merge($ext, $this->optext)); // Append to include later at page build
		} else {
			$vr = $this->lang('base', ['under_dev', 'check_back']);
			if (DEV) $vr['template_name'] = $template_name;
			$template = $this->render('page_underdev', $vr)->get();
		}
		//return($template);
		$this->rendered = '
<!-- ' . $template_name . ' : start -->' . PHP_EOL
			. $template . PHP_EOL
			. '<!-- ' . $template_name . ' : end -->' . PHP_EOL;

		return $this;
	}
	// Manual code / text injection	to page
	public function sketch($code = '')
	{
		// VALIDATE / FILTER CODE / TEXT HERE, IF REQUIRED
		$this->rendered = $code;
		return $this;
	}
	// Returns the rendered code, part of render() method chain
	public function get()
	{
		return $this->rendered;
	}
	// Appends the code
	public function append()
	{
		$this->html .= $this->rendered;
	}
	// Clean out previously queued code and add fresh
	public function flush()
	{
		$this->html = $this->rendered;
	}

	// Navigation Builder
	private function build_navigation()
	{
		//$link = $this->htag('ul',$this->htag('li',$this->htag('a', 'Play', ['href'=>'play'],0),[],0));
		//return $this->htag('div',$this->htag('nav', $link),['id'=>'navigation']);
		return $this->render('menu_struct')->get();
	}

	// Page Forwarder
	public function redirect($url = '')
	{
		if (!strpos('+' . $url, PRT)) $url = PRT . $url; //prepend base url, also redirect to base url only if url is not provided
		if (!empty($this->message['prompt'])) $_SESSION['message'] = $this->message['prompt']; // Temporarily save noks to get back after redirect
		header('location: ' . $url);
		exit();
	}

	// Generate final page to show in browser
	public function out()
	{
	// Auto include external resources
		$ext = include(ART . 'view/struct/declare_resource.php');
		foreach ($ext as $type => $expression) {
			foreach ($expression as $resource => $criteria) {
				foreach ($criteria as $identifier) {	// Prepare regular expression
					$rgx = '';
					if (empty($identifier) || $identifier === 'tag') { // its a tag
						$rgx = '\<\b' . $resource . '\b';
					} else {
						$rgx = '\b' . $identifier . '\b ?={1} ?(\'|\").*\b' . $resource . '\b';
					}
					if (preg_match('/' . $rgx . '/', $this->html)) {
						$this->mustext[$type][] = $resource;
					}
				}
			}
		}

		$varh = $varf = array();
		$varh['basepath'] = PRT;
		$varh['faviconpath'] = $this->faviconpath;

		// Set page title
		$varh['title'] = $this->site;
		if (!empty($this->name)) {
			$pagename = include($this->languagepath . $this->lang . '/pagename.php');
			$pagename = $pagename[strtolower($this->name)];
			$varh['title'] .= empty($pagename) ? ' : ' . $this->name : ' : ' . $pagename;
		}

		if (!$this->bare) {
			// Build Menu / Navigation
			$this->mustext['all'][] = 'nav';
			$varhr['navigation'] = $this->build_navigation();

			// Generate the visible header
			$varh['header'] = $this->render('page_header', $varhr)->get();

			// Generate the visible footer
			$vf['about'] = sprintf(conf('about'), '"' . $this->site . '"', $this->site);
			$vf['copyright'] = $this->copyright;
			$vf['page_gen'] = date("F j, Y, g:i A") . " [" . date_default_timezone_get() . "]";
			$varf['footer'] = $this->render('page_footer', $vf, ['footer'])->get();
		}
		foreach (['css', 'js'] as $reso) {
			$incl[$reso] = array_unique(array_merge($this->mustext[$reso], $this->mustext['all'], $this->optext[$reso], $this->optext['all'])); // CHECK SEQUENCE
		}

		$inclcss['css'] = $incl['css'];
		$vhi['include'] = $this->makeInc($inclcss);
		$varh['include'] = $this->render('page_header_include', $vhi)->get();

		// Throw final html to browser
		echo $this->render('page_head', $varh)->get();

		// Report inline messages
		if (!empty($this->message['inline'])) {
			foreach ($this->message['inline'] as $message) { // FILTER DEV MESSAGES FROM GENERAL
				$vmsg['msg'] = $message[0];
				$vmsg['type'] = $message[1];
				echo $this->render('inline_msg', $vmsg)->get();
			}
		}
		echo $this->html . $this->htag('br');

		// Throw prompt notifications
		if (!empty($this->message['prompt'])) {
			$message = $msg = '';
			$this->message['prompt'] = array_unique($this->message['prompt']); // Remove duplicate messages, if any
			foreach ($this->message['prompt'] as $message) {
				$msg .= '$.nok({message: \'' . $message[0] . '\',type: \'' . $message[1] . '\',sticky: ' . $message[2] . ',stay: ' . $message[3] . '});' . PHP_EOL;
			}
		}
		if (!empty($msg))
			$vhi['message'] = $this->htag('script', $msg, ['type' => 'text/javascript']); // Append the message script after initiating jQuery.

		// Create footer
		$incljs['js'] = $incl['js'];
		$vhi['include'] = $this->makeInc($incljs);
		$varf['include'] = $this->render('page_footer_include', $vhi)->get();
		echo $this->render('page_foot', $varf)->get();

		//printf("Page created in %.6f seconds.", microtime(true)-$this->pgen); << WORKING. NEED TO PLACE SOMEWHERE
	}
}