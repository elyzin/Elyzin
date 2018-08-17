<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Base Function
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 */

if (!defined("APP")) die(); // Deny direct access

// Class autoloader
spl_autoload_register(function($c){@include syspath('class').preg_replace('#\\\|_(?!.+\\\)#','/',$c).'.php';});

/**
 * Return preconfigured system paths
 *
 * @param [type] $target
 * @return string
 */
function syspath($target){
	$path = parse_ini_file(dirname(__DIR__).'/conf/syspath.ini',true);
	foreach ($path as $key => $group) {
		if(array_key_exists($target,$group)){
			$pre = ($key === 'app') ? ART : ''; // Visual root not required.
			return $pre.str_replace(':',DIRECTORY_SEPARATOR, $group[$target]).DIRECTORY_SEPARATOR;
		}
	}
	return false;
}

/**
 * Return Settings Values
 *
 * @param [type] $node // Settings name
 * @param string $group // Settings Group Name
 * @return mixed
 * @todo Fetch user settings as well, avoid hardcoded ini path
 */
function conf(string $node, string $group = 'site'){
	$settings = parse_ini_file(syspath('config').'basefact.ini',true);
	if(!empty($settings[$group][$node])) return $settings[$group][$node];
	return false;
}