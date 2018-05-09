<?php

if (!defined("APP")) die(); // Deny direct access

// Return preconfigured system paths
// =========================================================================================================
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

// Fetch Settings << Fetch user settings as well, avoid hardcoded ini path
// =========================================================================================================
function conf($node, $group = 'site'){
	$settings = parse_ini_file(syspath('config').'basefact.ini',true);
	if(!empty($settings[$group][$node])) return $settings[$group][$node];
	return false;
}