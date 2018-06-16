<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Root file
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 * 
 * @todo Router
 */

$pagegen = microtime(true); // Page generation time start

// Define site constants
define('DEV', ($_SERVER['HTTP_HOST'] === 'localhost'));
define('DRT', dirname(__DIR__) . DIRECTORY_SEPARATOR); // Directory Root
define('VRT', dirname(__FILE__) . DIRECTORY_SEPARATOR); // View Root (public)
define('ART', DRT . 'app' . DIRECTORY_SEPARATOR); // Application Root
define('PRT', '//' . preg_replace('/[\/]{2}/', '/', $_SERVER['HTTP_HOST'] . dirname(explode('index.php', $_SERVER['PHP_SELF'])[0]) . '/')); // Public web root

// Process url request at the first place
$req = isset($_GET['req']) ? explode('/', strip_tags($_GET['req'])) : array();
$req = preg_replace('/[^a-z0-9_.=-]/i', '', $req); // Basic unwanted character filter
if (empty($req[0])) $req[0] = 'report'; // Load homepage in case of no parameter defined

require_once(ART . 'core/init.php');
$valid_act = include(syspath('structure') . 'declare_action.php'); // Defined valid actions, grab from menu structure

// Check strict login and redirect if necessary
if (conf('strict_login', 'user') && !$me->id && (!in_array($req[1], ['login', 'recover']))) $page->redirect('account/login');

// Grab from available scripts, keeps users from requesting any file they want
$script = array_key_exists($req[0], $valid_act) ? $req[0] : 'error';
// Set page name, further customization to be done through script
$page->name = (!empty($req[1])) ? ucwords($req[1]) : ucwords($req[0]);

// Tracker, chatbox & bla bla staff goes here

// Include the script to generate page
if (file_exists(syspath('module') . $script . '.php')) {
	include(syspath('module') . $script . '.php');
} else { // Defined as valid act, but script not available
	$msg = $page->lang('base', ['coding_page', 'under_dev', 'check_back']);
	$page->message(sprintf(implode(' ', $msg), '"' . ucwords($script) . '"'), 'info');
	$msg['template_name'] = sprintf($msg['coding_page'], '"' . ucwords($script) . '"');
	$page->render('page_underdev', $msg)->flush();
}
// Template code gathered through script. Display final page
$page->out();