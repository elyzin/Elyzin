<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Initiator
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 */

 if (!defined("APP")) die(); // Deny direct access
// $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://'; // autoddetects, redundant

// Load site dependent functions (and utility functions)
require_once(dirname(__FILE__).'/func/site.php');

// Class autoloader
spl_autoload_register(function($c){@include syspath('class').preg_replace('#\\\|_(?!.+\\\)#','/',$c).'.php';});

// Error Handler
if(DEV){
	ini_set( "display_errors", "1" );
	error_reporting( E_ALL & ~E_NOTICE );
} else {
	error_reporting( 0 );
}

// Start a new or resume existing session
session_start();
	
$db 	= new db();
$me 	= new user();

// Set timezone as per user preference
if(!empty($me->pref('timezone'))){
	$timezone = $me->pref('timezone');
} elseif(isset($_COOKIE['timeoffset'])){ // Detected browser time zone. Cookie set by jquery in base.js
	$timezone = timezone_name_from_abbr("", $_COOKIE['timeoffset']*60, false);
} else { // Load timezone from site configuration
	$timezone = conf('timezone');
}
date_default_timezone_set($timezone);

$file 	= new file();
$clock 	= new clock();
$log	= new log();
$page 	= new page();
$page->pagegen = $pagegen; // Transfer page generation start time to page class	