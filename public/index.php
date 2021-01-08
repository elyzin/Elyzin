<?php

define('INIT_TIME', microtime(true));
define('STZ', date_default_timezone_get()); // Server Timezone
define('DRT', dirname(__DIR__) . DIRECTORY_SEPARATOR); // Directory Root
define('VRT', dirname(__FILE__) . DIRECTORY_SEPARATOR); // View Root (public)
define('PRT', '//' . preg_replace('/[\/]{2}/', '/', $_SERVER['HTTP_HOST'] . dirname(explode('index.php', $_SERVER['PHP_SELF'])[0]) . '/')); // Public web root
define('STAKE', ['user', 'project', 'organization']);

error_reporting(E_ALL);
ini_set("display_errors","On");

require DRT . 'vendor/autoload.php';

\Elyzin\Core\Config::init();

// Initiate Error Handler
Fallacy::init();

// Setup Session
$sessions_path = Config::path('sessions');
File::makeDir($sessions_path);
session_save_path($sessions_path);
session_start();

define('INIT_SESSID', session_id());
if (isset($_SESSION['time']) && time() - $_SESSION['time'] > getenv('SESSION_TIMEOUT') * 60) {
    // Handle force logout on session timeout
    session_regenerate_id(true); // change session ID for the current session and invalidate old session ID
}
$_SESSION['time'] = time();

// Setup language
if(!isset($_SESSION['lang'])) $_SESSION['lang'] = getenv('LANGUAGE'); // Set session value as per preference of user during login

$lang = new \i18n();
$lang->setCachePath(Config::path('langcache'));
$lang->setFilePath(Config::path('language', false)); // language file path
$lang->setFallbackLang(getenv('LANGUAGE'));
$lang->setPrefix('Lang'); // This is gonna be the usable class in global namespace
$lang->setMergeFallback(true); // make keys available from the fallback language
//$lang->setForcedLang('en'); // force english, even if another user language is available
//$lang->setSectionSeparator('_');
$lang->init();

//echo '<img src="'.Security::getTotpUri('effone', Security::getNewSecret()).'" />';
//echo Security::makePass("Password");




App::run();