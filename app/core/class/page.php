<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Model : Page
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 * 
 * @todo Namespace
 * @todo Interface
 */

class Page
{
	public $name = 'Home';
	protected $html = "";
	public $bare = false;
	private $mustext = array('css' => [], 'js' => [], 'all' => []); // Mandatory resources to include in all pages
	protected $optext = array('css' => [], 'js' => [], 'all' => []); // Optional resources that can be added dynamically
	protected $extpaths = array();
	private $infix = array(); // Lazy variable injection parameters holder
	protected $message = array(); // Prompt & Inline message queue << CARRY INLINE MESSAGES ALSO IN REDIRECT
	protected $rendered = ''; // Variable to hold last generated / inserted text chunk to page temporarily 

	/**
	 * Class construction method
	 *
	 * @param User $me // Dependency
	 */
	public function __construct(User $me, HTGen $ht) // ADD 'file'
	{
		// Set dependencies
		$this->di['me'] = $me;
		$this->di['ht'] = $ht;

		// Set basename
		$this->site = conf('basename');
		$this->caption = conf('caption');
		$this->site_start = conf('begin');

		// Set paths
		foreach (['cache', 'template', 'language', 'favicon'] as $path) {
			$p = $path . 'path';
			$this->$p = implode('/', explode('\\', syspath($path)));
		}

		// Set language
		$language = $this->di['me']->pref('language'); // User preferred language
		if (!$language && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $language = strtolower(explode('-', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0]); // Detected browser language
		$lang_exists = array_map('basename', glob($this->languagepath . '*', GLOB_ONLYDIR)); // Get available language directory names
		if (!$language || !in_array($language, $lang_exists)) $language = conf('language'); // Site default language
		$this->lang = $language;

		$this->copyright = date('Y', $this->site_start) . ((date('Y', $this->site_start) !== date('Y')) ? ' - ' . date('Y') : '') . ' ' . $this->site . ' // ' . $this->lang('base', 'copyright');

		// Set external resource & paths
		$this->mustext = array('css' => ['normalize', 'awesome', 'flex'], 'js' => ['jquery-3', 'plugin'], 'all' => ['base']); // TRY TO SOFTCODE THIS
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

	/**
	 * Store additional external resources for including in page
	 *
	 * @param array $incl
	 * @return bool
	 */
	public function setExt(array $incl = array())
	{
		if (!empty($incl) && array_depth($incl) == 2) {
			$newext = array_merge_recursive($this->optext, $incl);

			foreach ($newext as $key => $value) {
				$setext[$key] = array_unique($value);
			}
			$this->optext = $setext;
			return true;
		}
		return false;
	}

	/**
	 * Throw combined resources
	 *
	 * @param string $type
	 * @return void
	 */
	public function spitExt(string $type = "css")
	{
		$content_type = $type == "css" ? "text/css" : "application/javascript";
		//$callback = conf('compress_sourcecode', 'page') ? ["self", "compress_assets"] : null;
		// Load compressed resources chunk
		header("Content-type: $content_type; charset: UTF-8");
		//ob_start($callback);
		/*
		if (conf('compress_sourcecode', 'page')) {
			ob_start(function ($buffer) use ($type) {
				return $this->compress_assets($buffer, $type);
			});
		} else {
			ob_start();
		}
		 */
		ob_start();
		if (isset($_SESSION['resource'][$type]) && is_array($_SESSION['resource'][$type])) {
			foreach ($_SESSION['resource'][$type] as $resource) include($resource);
			unset($_SESSION['resource'][$type]);
		}
		if (empty($_SESSION['resource'])) unset($_SESSION['resource']);
		ob_end_flush();
		die();
	}

	/**
	 * External resource includes builder
	 *
	 * @param array $ext
	 * @param boolean $path_only
	 * @return void
	 */
	private function makeInc(array $ext, bool $path_only = false)
	{
		foreach ($ext as $type => $grp) {
			foreach ($grp as $name) {
				$paths = $this->getPath($name, $type);
				foreach ($paths as $v) {
					$v = str_replace('\\', '/', $v);
					if ($type === 'css') $links[] = $path_only ? $v : $this->di['ht']->elem('link', '', ['type' => 'text/css', 'rel' => 'stylesheet', 'href' => $v]);
					if ($type === 'js') $links[] = $path_only ? $v : $this->di['ht']->elem('script', '', ['type' => 'text/javascript', 'src' => $v], 0);
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
			return $path_only ? $links : implode('', $links);
		}
		return false;
	}

	// External resource path finder << MOVE TO FILE CLASS
	private function getPath($name = '', $ext = '', $exact = 0)
	{
		$path = array();
		foreach ($this->extpaths as $file) {
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
				$res_woext = pathinfo($rez, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($rez, PATHINFO_FILENAME);
				$res_ext = pathinfo($rez, PATHINFO_EXTENSION);
				if (conf('compress_sourcecode', 'page')) {
					if (in_array($res_woext . '.min.' . $res_ext, $path)) // Make sure compressed one exists
					unset($path[$node]); // Keep compressed only
				} else {
					if (stripos($res_woext, '.min')) { // Assuming non compressed version available. CHECK???
						unset($path[$node]);
					}
				}
			}
		}
		return array_values($path);
	}

	/**
	 * Add messages to show up in page
	 *
	 * @param string $message
	 * @param mixed $type
	 * @param integer $appearance
	 * @return bool
	 */
	public function message(string $message = '', $type = 'error', int $appearance = 5)
	{
		// No user input, sanitation not needed.
		if (empty($message)) return false;

		// Set default values
		$render = 'prompt';
		$sticky = 0;
		$stay = $appearance;

		// Types are error, info, success (and dev: for inline only)
		if (is_numeric($type)) $type = ['error', 'success', 'info', 'dev'][$type];

		if ($appearance < 0) {
			$render = 'inline';
		} else {
			if ($type == 'dev') return false; // No dev mode for prompt message
			if ($appearance == 0) {
				$sticky = 1;
			}
		}

		$this->message[$render][] = ($render === 'prompt') ? [$message, $type, $sticky, $stay] : [$message, $type];
		return true;
	}

	/**
	 * Return text strings for the language variables
	 *
	 * @param mixed $file // The language file(s) required to obtain values from
	 * @param mixed $var // The variables required to return values against
	 * @return mixed // Return string for single value, array for multiple
	 */
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
	
	/**
	 * Template based HTML Generator
	 *
	 * @param string $template_name
	 * @param array $vars // Defined template variable values
	 * @param array $lang // Language for loading variable values
	 * @param array $ext // Required external resource files
	 * @return void
	 */
	public function render(string $template_name, array $vars = array(), array $lang = array(), array $ext = array())
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

			// Blankout all the unassigned variables : not done here to support lazy value injection
			//$template = preg_replace('/\\{{2}(.*?)\\}{2}/', '', $template);

			// Include external resources
			//if(is_array($ext) && !empty($ext)) $template .= $this->makeInc($ext); // Don't include upfront
			if (is_array($ext) && !empty($ext)) $this->optext = array_unique(array_merge($ext, $this->optext)); // Append to include later at page build
		} else {
			$vr = $this->lang('base', ['under_dev', 'check_back']);
			if (DEV) $vr['template_name'] = $template_name;
			$template = $this->render('page_underdev', $vr)->get();
		}
		$this->rendered = (conf('compress_sourcecode', 'page') && !DEV) ? $template : '
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
	private function buildNav()
	{
		/*
		$user_gid = 1;
		$menu_items = $this->di['db']->table(conf('basename').'_action')->notWhere('permitted_gid', '>', $user_gid)->notWhere('icon', '')->getAll(); // Target icon to identify menu items, name can be created from action
		foreach ($menu_items as $menu_item) {
			$menu[$menu_item->parent_id][] = $menu_item;
		}
		echo '<pre style="text-align:left;">';
		print_r($menu);
		echo '</pre>';
		//$link = $this->di['ht']->elem('ul',$this->di['ht']->elem('li',$this->di['ht']->elem('a', 'Play', ['href'=>'play'],0),[],0));
		//return $this->di['ht']->elem('div',$this->di['ht']->elem('nav', $link),['id'=>'navigation']);
		 */
		$vars['username'] = isset($this->di['me']->name) ? $this->di['me']->name : 'Account';
		return $this->render('menu_struct', $vars)->get();
	}

	/**
	 * Page Forwarder
	 *
	 * @param string $url
	 * @return void
	 */
	public function redirect($url = '')
	{
		if (!strpos('+' . $url, PRT)) $url = PRT . $url; //prepend base url, also redirect to base url only if url is not provided
		if (!empty($this->message['prompt'])) $_SESSION['message'] = $this->message['prompt']; // Temporarily save noks to get back after redirect
		header('location: ' . $url);
		exit();
	}

	/**
	 * Lazy value injector (allows to inject value of variable(s) even after rendering a template)
	 *
	 * @param array $expression
	 * @return void
	 */
	public function infix(array $expression = [])
	{
		if (empty($this->html)) return;

		$expression = array_combine(array_map(function ($find) {
			return '{{' . $find . '}}';
		}, array_keys($expression)), $expression);
		
		// Hold the parameters to inject at the end
		$this->infix = array_merge($this->infix, $expression);
	}

	/**
	 * Final processing of rendered page code (output buffer callback)
	 *
	 * @param string $buffer
	 * @return string
	 */
	private function finalizeCode(string $buffer)
	{
		// Lazy insert available variable assignments
		$buffer = strtr($buffer, $this->infix);

		// Blankout all the unassigned variables
		$buffer = preg_replace('/\\{{2}(.*?)\\}{2}/', '', $buffer);

		// Trim, white space and new lines removal, if required
		return conf('compress_sourcecode', 'page') ? preg_replace('/\v(?:[\v\h]+)/', '', $buffer) : $buffer;
	}

	/**
	 * Router encountered error page generator
	 *
	 * @param integer $code // The code corresponding identified error
	 * @param string $request // Request string leading to the error
	 * @return void
	 */
	public function error($code = 404, $request = "")
	{
		switch ((int)$code) {
			case 503: // Service N/A. Found a better applicable code?
				$msg = $this->lang('base', ['coding_page', 'under_dev', 'check_back']);
				$this->message(sprintf(implode(' ', $msg), '"' . ucwords($request) . '"'), 'info');
				$msg['template_name'] = sprintf($msg['coding_page'], '"' . ucwords($request) . '"');
				$this->render('page_underdev', $msg)->flush();
				break;

			case 404:
			default:
				# code...
				break;
		}
	}

	/**
	 * Generate final page to show in browser
	 *
	 * @param integer $pagegen // Script execution start moment, required for estimating page generation time
	 * @return void
	 */
	public function out(int $pagegen = 0)
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
			$varh['title'] .= isset($pagename[strtolower($this->name)]) ? ' : ' . $pagename[strtolower($this->name)] : ' : ' . $this->name;
		}

		if (!$this->bare) {
			// Build Menu / Navigation
			$this->mustext['all'][] = 'nav';
			$varhr['name'] = conf('basename');
			$varhr['caption'] = $this->caption;
			$varhr['navigation'] = $this->buildNav();

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
		$madeinc = $this->makeInc($inclcss, conf('combine_resource', 'page'));
		if (conf('combine_resource', 'page')) {
			$_SESSION['resource']['css'] = $madeinc;
			$vhi['include'] = $this->di['ht']->elem('link', '', ['type' => 'text/css', 'rel' => 'stylesheet', 'href' => 'resource/css']);
		} else {
			$vhi['include'] = $madeinc;
		}

		$varh['include'] = $this->render('page_header_include', $vhi)->get();

		ob_start(['self', 'finalizeCode']); // Start buffering

		echo $this->render('page_head', $varh)->get();

		// Report inline messages
		if (!empty($this->message['inline'])) {
			foreach ($this->message['inline'] as $message) { // FILTER DEV MESSAGES FROM GENERAL
				$vmsg['msg'] = $message[0];
				$vmsg['type'] = $message[1];
				unset($message);
				echo $this->render('inline_msg', $vmsg)->get();
			}
		}
		echo $this->html . $this->di['ht']->elem('br');

		// Throw prompt notifications
		if (!empty($this->message['prompt'])) {
			$message = $msg = '';
			$this->message['prompt'] = array_unique($this->message['prompt'], SORT_REGULAR); // Remove duplicate messages, if any
			foreach ($this->message['prompt'] as $message) {
				$msg .= '$.nok({message: \'' . $message[0] . '\',type: \'' . $message[1] . '\',sticky: ' . $message[2] . ',stay: ' . $message[3] . '});' . PHP_EOL;
			}
		}
		if (!empty($msg)) $vhi['message'] = $this->di['ht']->elem('script', $msg, ['type' => 'text/javascript']); // Append the message script after initiating jQuery.

		// Create footer
		$incljs['js'] = $incl['js'];
		$madeinc = $this->makeInc($incljs, conf('combine_resource', 'page'));
		if (conf('combine_resource', 'page')) {
			$_SESSION['resource']['js'] = $madeinc;
			$vhi['include'] = $this->di['ht']->elem('script', '', ['type' => 'text/javascript', 'src' => 'resource/js'], 0);
		} else {
			$vhi['include'] = $madeinc;
		}
		$varf['include'] = $this->render('page_footer_include', $vhi)->get();
		echo $this->render('page_foot', $varf)->get();

		// Page generation time
		//if ($pagegen > 0) printf("Page created in %.6f seconds.", microtime(true)-$pagegen); //<< WORKING. NEED TO PLACE SOMEWHERE SUITABLE

		// Throw final html to browser
		ob_end_flush();
	}
}