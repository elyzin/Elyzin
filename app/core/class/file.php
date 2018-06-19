<?php

 class file
 {
 	public $tempfile 	= 'temp';

	public function __construct()
	{
		// Set paths
        foreach(['cache','temp'] as $path){
            $p = $path.'path';
            $this->$p = syspath($path);
        }
        $this->vaultpath = dirname($this->cachepath).DIRECTORY_SEPARATOR;

		//$this->makeDir($this->temppath, 0755);
        
	}

	public function size($size, $precision = 2) {
	    $i = floor(log($size, 1024));
    	return round($size / pow(1024, $i), $precision).['B','KB','MB','GB','TB','PB','EB','ZB','YB'][$i];
	}

	public function getPath($search_crt = '', $file_ext = '', $search_dir = '', $exact = false){
		if(empty(trim($search_dir))) $search_dir = $this->vaultpath;
        $cacpext = $this->cachepath.md5($search_crt.$file_ext.$search_dir.$exact);
		if(file_exists($cacpext)&& (time()-filemtime($cacpext))>conf('dircache')*60) unlink($cacpext); // Delete old cache
        if(!file_exists($cacpext)){ // Cache paths if not exists
			$allfiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($search_dir));

			$filtered_list = array();
			foreach ($allfiles as $eachfile){
                //$eachfile = str_replace(ART, '', $eachfile);
				if(!empty(trim($file_ext))){
					$real_ext = explode('.',$eachfile);
					if(strtolower(end($real_ext)) === strtolower($file_ext)){
						$filtered_list[] = (string)$eachfile;
					}
				} else {
					if(is_file($eachfile)) $filtered_list[] = (string)$eachfile;
				}
			}
			
			if(!empty(trim($search_crt))){
				foreach ($filtered_list as $fkey => $eachfile) {
					$basename = basename($eachfile, '.'.$file_ext);
					if(!strpos('+'.$basename,$search_crt)) unset($filtered_list[$fkey]); // Nasty fix for position 0. Use preg_match?
				}
            }
			if($search_dir !== $this->cachepath) $this->arrDump($filtered_list,$cacpext); // Create cache for next use
		}
		else
		{
			$filtered_list = include($cacpext);
		}
		return array_values($filtered_list);
	}

	// Write array data to file (cache)
	public function penCache($array = array(),$filename = '',$overwrite=true){ // Dummy function to ensure strict cache path
        if(!empty($filename)){
            // FORWARD BACKWARD SLASH CHECK REPLACE???
            $filename = $this->cachepath.basename($filename); // Make sure placing file to predefined cache path only
            $this->arrDump($array,$filename,$overwrite);
        }
    }

	public function arrDump($array = array(),$filename = '',$overwrite=true){ // MAKE OVERWRITE EFFECTIVE
		if(empty($fileName)) $fileName = $this->temppath.$this->tempfile;
		$content = var_export($array, true);

		$date = new DateTime();
		$date = $date->format('d-m-Y, H:i:s').' ('.date_default_timezone_get().')';
		$content = <<<EOD
<?php
//auto generated on $date

return $content;
EOD;
		$this->penSafe($content, $filename);
	}

	// Securely create / write file 
	public function penSafe($dataToSave, $fileName='', $append = 0){
		//$this->makeDir(dirname($filename)); // Ensure directory exists else create
		if($append){
			$pointer =  'a';
		}else{
			$pointer =  'w';
		}
		if ($fp = fopen($fileName, $pointer)){
	        $startTime = microtime(TRUE);
	        do{  $canWrite = flock($fp, LOCK_EX);
	           // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
	           if(!$canWrite) usleep(round(rand(0, 100)*1000));
	        }while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));

	        //file was locked so now we can store information
	        if ($canWrite){
	        	fwrite($fp, $dataToSave);
	            flock($fp, LOCK_UN);
	        }
	        fclose($fp);
	    }
	}

	// Create recursive directory with permission
	public function makeDir($path='',$mode = 0777,$rec=true){
		if (!is_dir($path)) mkdir($path, $mode, $rec);
		return (is_dir($path)) ? true : false;
	}

	// Delete recursive directory with all files in it
	public function wipeDir($dirname=''){
		array_map('unlink', glob("$dirname/*.*"));
		rmdir($dirname);
	}

	// Get recursive directory structure << Working function. DEPRICIATED to use in-built RecursiveIterator
	public function dirTree($dir, &$results = array()){
		if(!isset($dir)) $dir = $this->vaultpath;
	    $files = scandir($dir);
	    foreach($files as $key => $value)
	    {
	        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
	        if(!is_dir($path))
	        {
	            $results[] = $path;
	        }
	        else if($value != "." && $value != "..")
	        {
	            $this->dirtree($path, $results);
	            $results[] = $path;
	        }
	    }
	    return $results;
	}	
 }
 /*
 <?PHP 

// filename: class.jfile.php 
// author: jani mikkonen (jani@mikkonen.org) 
// comment: class to open/close files with file locking with timeout support 
// license: GPL 
// date: 29.11.2000 
// 
// v1.0: First release, totally untested and my first shot to produce something 
// like this. *all comments* are welcome! 
// 
// Usage: Check out the comments. If you need this kind of class you probably can 
// read the code to see what happens inside it. 
// TODO: * When opening or locking file, check if its not done allready. 
// * When closing, check if file is actually opened and/or locked. 


class jfile { 

    var $fileHandle; 
    var $fileName; 
    var $timeOut = 10; 
    var $status = 0; 
    var $fileMode; 
    var $lastStatus; 
    var $timeOutm = 200; 
    var $debug = false; 
    
    // Simple constructor. Does allmost nothing except you must pass the filename as 
    // a parameters to this class to actually open something. 

    function jfile($fn) { 
        $this->lastStatus = true; 
        $this->fileName = $fn; 
    } 

    // Private 
    // Opens a file with certain mode (read,write,append) and without locking. 

    function openFile($mode) { 
        if ($debug) print "in: openFile($mode)<br>"; 
        $this->lastStatus = false; 
        $this->fileMode=$mode; 
        $this->fileHandle = fopen($this->fileName,$this->fileMode); 
        if (! $this->fileHandle == false ) { 
            $this->lastStatus = true; 
        } 
        if ($debug) print "out: openFile($mode)<br>"; 
        return $this->lastStatus; 
    } 

    // Private 
    // Closes file that has been previously opened. 
    
    function closeFile() { 
        if ($debug) print "in: closeFile()"; 
        $this->lastStatus = false; 
        $this->fileMode=''; 
        $this->lastStatus = fclose($this->fileHandle); 
        if ($debug) print "out: closeFile()"; 
        return $this->lastStatus; 
    } 

    // Public 
    // Opens the file with certain mode and certain locking type. 
    // What the code does: 
    // Tries first to open the file and if successfull, then lock it 
    // with user given lock operation. In any case of failure, program sleeps 
    // for 200 milliseconds and tries again. Loop counter is checked against 
    // class varible timeOut and if counter is bigger, exit from the method 
    // and not open the file. $mode is passed to fopen() and $locktype is 
    // passed to flock operation. 

    function openFileAsLocked($mode,$lockType) { 
        if ($debug) print "in: openFileAsLocked($mode,$lockType)<br>"; 
        $this->lastStatus = false; 
        $counter = 0; 
        while ( ($this->lastStatus==false) && ($counter<$this->timeOut) ) { 
            $this->openFile($mode); 
            if ( $this->lastStatus==false ) { 
                $counter ++; 
                usleep($this->timeOutm); 
                
            } 
        } 
        if ($this->lastStatus==true) { 
            while ( ($this->lastStatus==false) && ($counter<$this->timeOut) ) { 
                $this->lastStatus = flock($this->fileHandle,$locktype); 
                if ($this->lastStatus==false) { 
                    $counter ++; 
                    usleep($this->timeOutm); 
                } 
            } 
        } 

        if ($debug) print "out: openFileAsLocked($mode,$lockType)<br>"; 
        return $this->lastStatus; 
    } 


    // Public 
    // Tries to free the lock and close the previously opened file 
    // What the code does: 
    // Exactly the same as openFileAsLocked() method but in reverse order. 

    function closeLockedFile() { 
        if ($debug) print "in: closeLockedFile()<br>"; 
        $this->lastStatus = false; 
        $counter = 0; 
        while ( ($this->lastStatus==false) && ($counter<$this->timeOut) ) { 
            $this->lastStatus = flock($this->fileHandle,LOCK_UN); 
            if (!$this->lastStatus) { 
                $counter ++; 
                usleep($this->timeOutm); 
            } 
        } 
        if ($this->lastStatus==true) { 
            while ( (! $this->lastStatus) && ($counter<$this->timeOut) ) { 
                $this->closeFile(); 
                if ( !$this->lastStatus ) { 
                    $counter ++; 
                    usleep($this->timeOutm); 
                } 
            } 
        } 
        if ($debug) print "out: closeLockedFile()<br>"; 
        return $this->lastStatus; 
    } 
} 
?> 
*/