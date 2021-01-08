<?php

// Search file paths in a specified directory as per defined pattern
    
return function ($folder, $pattern, $fullpath = false) {
	$dir = new \RecursiveDirectoryIterator($folder);
	$ite = new \RecursiveIteratorIterator($dir);
	$files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
	$fileList = array();
	foreach($files as $file) {
		if(!$fullpath) $file[0] = substr($file[0], strlen($folder));
		$fileList = array_merge($fileList, $file);
	}
	return array_values(array_unique($fileList));
};