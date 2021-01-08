<?php
/**
 * TODO: Page Title
 * TODO: nok Notice
 */
namespace Elyzin\View;

use Config;
use Security;
use Markup;
use Helper;
use File;
use \Lang;

class View
{
    public $bare = false; // Set true to hide header and footer
    public $title = 'Content';
    public $theme = 'base';
    protected $templatepath = '/';
    private $assets = ['css'=>[],'js'=>[]]; // Resources to include in page
    private $infix = array(); // Lazy variable injection parameters holder
    private $message = ''; // Message holder
    protected $rendered = ''; // Variable to hold last generated / inserted text chunk to page temporarily
    protected $content = ''; // Container to hold code body for final output

    public function __construct()
    {
        $this->site = getenv('SITENAME');
        $this->title = Lang::page_title;
        $this->theme = getenv('THEME');
        $this->templatepath = Config::path('templates') . strtolower($this->theme) . DIRECTORY_SEPARATOR;
        $this->logo = 'assets/.' . strtolower($this->theme) . '/images/logo.svg'; // MAKE IT BY SETUP
        $this->setAsset('base,jquery|js');

        if(!empty($_SESSION['message'])) {
            $this->message = $_SESSION['message'];
            unset($_SESSION['message']);
        }
    }

    /**
     * Generate HTML to include resources
     * @param bool $version // Whether file version to include with resurce link
     * @return string // HTML Code
     */
    private function includes($version = true){
        $assets = Helper::rsearch(VRT, '/.*css|.*js/');
        $tpl = ['css'=>'<link href="%s" rel="stylesheet" type="text/css" />
        ', 'js'=>'<script type="text/javascript" src="%s"></script>
        '];
        $min = getenv('MIN_ASSET') ? ".min." : ".";
        $includes = "";
        foreach($this->assets as $ext => $names){
            foreach($names as $name){
                $no_min = false; // Reset to false
                $matches = preg_grep("/".$name.$min.$ext."/", $assets);
                // Try to fallback to normal if minified version not found
                if(getenv('MIN_ASSET') && empty($matches)){
                    $no_min = true;
                    $matches = preg_grep("/".$name.".".$ext."/", $assets);
                }

                if(empty($matches)){
                    // Still not found? Report it to dev
                    $this->message('resourcena', 'dev', [$name.$min.$ext]);
                } else {
                    if(count($matches) > 1){
                        // Multiple matches found with same name. Report to dev and use first match
                        $this->message('resourcemulti', 'dev', [$name.$min.$ext]);
                    }

                    if($no_min === true){
                        $this->message('resourcenomin', 'dev', [$name.$min.$ext, $name.".".$ext]);
                    }
                    $matches = reset($matches);
                    if($version) $matches .= "?ver=" . filemtime(VRT.$matches);
                    $includes .= sprintf($tpl[$ext], $matches);
                }
            }
        }
        return $includes;
    }

	/**
	 * Add messages to show up in page
	 *
	 * @param string $message
	 * @param mixed $type
	 * @return bool
	 */
	public function message(string $message = '', $type = 'error', $bitz = [])
	{
		// No user input, sanitation not needed.
		if (empty($message)) return false;

		// Types are error, info, success (and dev: for inline only)
        if (is_numeric($type)) $type = ['error', 'success', 'info', 'dev'][$type];
        $message = 'message_'.$message;
        $message = Lang($message, $bitz);
		$this->message .= Markup::elem('div', $message, ['class' => ['notice', $type]]);
		return true;
    }
    
    /**
     * Template based HTML generator
     *
     * @param string $template : The name of the template
     * @param mixed $vars : The variables to insert in template, if not array then code gets placed directly
     * @return void // Returns the object for chaining
     */
    public function render(string $template, $vars = array(), bool $raw = false)
    {
        if (!$raw) {
            if (file_exists($this->templatepath . $template . '.htm')) {
                $template = file_get_contents($this->templatepath . $template . '.htm');
            } else {
                $this->error(404);
            }
        }

        if (is_array($vars) && !empty($vars)) {
            $template = strtr($template, $this->varbind($vars));
        }

        $this->rendered = $template;
        return $this;
    }

    // Returns the rendered code, part of render() method chain
    public function get()
    {
        return $this->rendered;
    }

    // Appends the code or clean out previously queued code and add fresh
    public function set($flag = false)
    {
        if ($flag) {
            $this->content = $this->rendered;
        } else {
            $this->content .= $this->rendered;
        }
    }

	/**
	 * Store additional external resources for including in page
	 *
	 * @param mixed $incl
	 * @return bool
	 */
	public function setAsset($incl = array())
	{
		if (!empty($incl)) {
            $assets = ['css'=>[],'js'=>[],'all'=>[]];
            if (is_string($incl)) $incl = explode(",", $incl);
			foreach ($incl as $key => $value) {
                if (!array_key_exists($key, $assets)){
                    if (is_string($value)) {
                        if(strpos($value, "|") !== false){
                            $value = explode("|", $value);
                            $key = array_key_exists($value[1], $assets) ? $value[1] : 'all';
                            $value = array($value[0]);
                        } else {
                            $value = array($value);
                            $key = 'all';
                        }
                    } else {
                        $key = 'all';
                    }
                }
                $assets[$key] = array_merge($assets[$key], $value);
            }
            
            $assets['css'] = array_merge($assets['css'], $assets['all']);
            $assets['js'] = array_merge($assets['js'], $assets['all']);
            unset($assets['all']);
			$assets = array_merge_recursive($this->assets, $assets);
			foreach ($assets as $key => $value) {
				$this->assets[$key] = array_unique($value);
			}
			return true;
		}
		return false;
	}

    /**
     * Redirects the page to defined url, corrects the url path and preserves push notices
     *
     * @param string $url : The URL to redirect to
     * @return void
     */
    public function redirect($url = '')
    {
        if (empty($url)) {
            $url = PRT;
        } else if (stripos($url, ':') !== true) { // Not a complete url
            if (stripos($url, PRT) === true) { // Same site partial url
                $url = explode(PRT, $url)[1];
            }
            $url = rtrim(PRT . ltrim($url, '/'), '/');
        } else {
            $this->error(404);
        }

        // Consider session variable for carryover messages & push notices
        if(!empty($this->message)) $_SESSION['message'] = $this->message;

        header('Location: ' . $url);
        exit();
    }

    public function error(int $code = 404)
    {
        http_response_code($code);

        $errdesc = File::read(Config::path('lexis') . 'errorcode.php');

        if(!isset($errdesc[$code])) exit('Unknown http status code "' . htmlentities($code) . '"');
        $backlink = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : PRT;
        $vars = ['logo' => $this->logo, 'code' => http_response_code(), 'description' => $errdesc[$code], 'backlink' => $backlink];
        $this->render('body.error', $vars)->set(true);
        $this->bare = true;
        $this->out();
    }

    /**
     * Lazy value injector (allows to inject value of variable(s) even after rendering a template)
     *
     * @param array $expression
     * @return void
     */
    public function infix(array $expression = [])
    {
        // Hold the parameters to inject at the end
        $this->infix = array_merge($this->infix, $this->varbind($expression));
    }

    private function varbind(array $expression = array())
    {
        return array_combine(array_map(function ($find) {
            return '{{ ' . $find . ' }}';
        }, array_keys($expression)), $expression);
    }
    
    private function lang($matches){
        return Lang($matches[1]);
    }

    /**
     * Final processing of rendered page code (output buffer callback)
     *
     * @param string $buffer
     * @return string
     */
    private function signOff(string $buffer)
    {
        // Lazy insert available variable assignments
        $buffer = \strtr($buffer, $this->infix);

        $buffer = \preg_replace_callback('/{:\s(.+?)\s:}/', [$this, 'lang'], $buffer);

        // Blankout all the unassigned variables
        $buffer = \preg_replace('/\\{{2}(.*?)\\}{2}/', '', $buffer);

        // Trim, white space and new lines removal, if required
        return getenv('MIN_ASSET') ? preg_replace('/\v(?:[\v\h]+)/', '', $buffer) : $buffer; // TRY A BETTER COMPRESSION LIB https://github.com/marcocesarato/PHP-Minifier
    }

    /**
     * Compile final output, buffer page HTML and eject
     *
     * @param integer $queryCount : Number of query ran through database, required if debug is turned on.
     * @return void
     */
    public function out(int $queryCount = 0)
    {
        /*
        // Check requirement of token and insert
        if(stripos($this->content, '{{ token }}') !== false)
        {
            $this->infix(['token' => Security::getCsrfToken()]);
        }
        */
        
        // Search for forms and append CSRF tokens
        \preg_match_all('/<form (.*?)>/', $this->content, $forms);
        $forms = array_unique($forms[0]);
        if(!empty($forms)){
            foreach ($forms as $form) {
                $token = Security::getCsrfToken(\simplexml_load_string($form."</form>")->attributes()->{'action'});
                $token = '
                <input type="hidden" name="token" value="'.$token.'" />';
                $this->content = str_replace($form, $form.$token, $this->content);
            }
        }
        session_write_close();

        if (empty($this->content)) {            
            $this->bare = true;
            $vars = [
                'logo' => $this->logo,
                'code' => Lang::message_generictitle,
                'description' => Lang::message_underdevdesc
            ];
            $this->render('body.error', $vars)->set(true);
        }

        // Start buffering
        ob_start(['self', 'signOff']);

        if (!$this->bare) {
            // Generate visible header
            $vars['logo'] = $this->logo;
            $this->infix(['page_header' => $this->render('page.header', $vars)->get()]);

            // Generate visible footer
            $vars = [];
            $this->infix(['page_footer' => $this->render('page.footer', $vars)->get()]);
        }

		// Add support for jquery plugins inclusion for autoload
		preg_match_all('/data-plugin=[\'\"](.+?)[\'\"]/s', $this->content, $plugins);
		$plugins = array_unique($plugins[1]);
		if (!empty($plugins)) $this->setAsset(implode(",", $plugins));

        $vars = [
            'page_title' => $this->site,
            'basename' => PRT,
            'favicon' => "",
            'head_include' => $this->includes()
        ];
        echo $this->render('page.head', $vars)->get();

        echo $this->message;
        echo $this->content;

        $vars = [];
        echo $this->render('page.foot', $vars)->get();

        // DEBUG
        //printf("%d%% Server load detected.<br>", Helper::serverload()); // Too slow
        //printf("%d database query executed.<br>", $this->Dbase->queryCount());
        //printf("Peak memory used %4f MiB.<br>", memory_get_peak_usage(false)/1024/1024);
        //printf("Page processed in %.6f ms.", (\microtime(true) - INIT_TIME) * 1000);

        // Throw final html to browser
        ob_end_flush();

        exit();
    }
}
