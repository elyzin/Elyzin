<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Model : User
 * @author Elyzin Devs <devs@elyz.in>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 * 
 * @todo Namespace
 * @todo Interface
 */

class User
{
	public $id;
	public $dispname;
	public $error;
	protected $message;
	public $permit;
	protected $who = 'other'; // 'self' represents object of user operating this class
	private $pref;
    private $site			= '';
	private $userstatpath 	= '';
	private $avatarpath 	= '';

    public function __construct(int $id=0){
        $this->site 		= conf('basename');
        $this->userstatpath = syspath('userstat');
        $this->avatarpath   = syspath('avatar');
    	if($id === 0 && isset($_SESSION['user']['id'])) $id = $_SESSION['user']['id'];
    	if($id === $_SESSION['user']['id'])	$this->who = 'self';
    	
    	$this->id = $id;
		if($id > 0) $this->setUser($id);
		
    }

    protected function setUser($id=0){ // Use __set_state?
    	if(is_numeric($id) && $id > 0) {
       		$base = new db();
       		$details = (array)$base->table($this->site.'_user')->where(['id'=>$id])->get(); //USE CACHE
       		if(!empty($details)){
	       		foreach ($details as $key => $value) $this->{$key} = $value; // Set all fields to class
				if($this->who === 'self') $this->lastproj = $_SESSION['user']['lastproj']; // Override
	       		if(empty($this->pref)) $this->pref = json_encode(parse_ini_file(ART.'core/conf/userpref.ini')); // Set default values if preference not defined
	       		$this->dispname = empty($this->fullname) ? $this->name : $this->fullname;
	       		// Set remaining days til password expiry
                $clock 	= new clock();
	       		$this->passdays = conf('pass_expiry','user')-floor((time()-($clock->stamp($this->passtime,'site'))/86400));

	       	} else {
				$this->id = 0;
	       	}
    	}
    }

    public function setLast(bool $login=false, bool $proj=false, bool $commit=true){ //<< DEPRECIATE
    	if($this->who != 'self'){// Self environment method only
    		$this->error = 'Modifying last actions of other users restricted.';
    		return false;
    	}

    	$update = array();

    	if($login){ // Last login time to update
    		if(!isset($_SESSION['user']['thislogin'])){
	    		$this->error = 'Unidentifined login time.';
	    		return false;
    		}
    		// While changing last login to this in database, actual last login can only be accessed through session vars
            $clock 	= new clock();
    		$update['lastlogin'] = $clock->stamp($_SESSION['user']['thislogin'],'db');
    	}

    	if($proj){ // Last project to update
    		if(!isset($_SESSION['project']['code'])){
	    		$this->error = 'Undefined current project.';
	    		return false;    			
    		}
    		// While changing last project to this in database, actual last ptoject can only be accessed through session vars
			$update['lastproj'] = $_SESSION['project']['code'];
    	}

    	if($commit){ // Commit changes to database
	    	global $dbx;
	    	//$where = array('id' => $this->id);
	    	if($dbx->table($this->site.'_user')->where('id', $this->id)->update($update)){
	    	//if($db->update( $this->site.'_user', $update, $where, 1 )){
	    		reCache($this->site.'_user');
	    		return true; // Commit Succeeded
	    	} else {
		    	$this->error = 'Database Operations Failed.';
		    	return false;
	    	}
	    } else {
	    	return $update; // Return update array for some other function to commit changes in database (reduce query count)
	    }
    }
    
	public function getState(bool $mode=false){
		// Code this, use as for each user. Depreciate users_getstate();
		return users_getstate($mode);
	}

    public function setState(int $state=3){
    	if($state >= 0 && $state <= 3){ //Ensure range. 0 = Offline, 1 = Away, 2 = Busy, 3 = Online
            $clock 	= new clock();
			$status[] = array('uid'=>$this->id, 'name'=>$this->dispname,'last_seen'=> $clock->stamp(time(),'db'), 'state'=>$state);
			$file_handle = fopen($this->userstatpath.$this->id.'.stt', "w");
			$result = fwrite($file_handle, json_encode($status)); // SAFE WRITER??
			fclose($file_handle);
			return $result;
		} else {
			return false;
		}
    }

    public function permit(string $prop='lvl', string $proj=null, string $unit=null){
		if(!sizeof($this->permit) || !in_array($prop, ['lvl','prj','unt'])) return 0;

		$permit = json_decode($this->permit, true);
		$lvl = key($permit);
		if($prop === 'lvl') return $lvl;

		if($prop === 'prj'){
			$prj = array(); // Blank container
			if($lvl >= 6){ // Have access to all projects
				// Get all projects
				$all_projects = [];//enCache('SELECT code FROM '.$this->site.'_projects');
				foreach ($all_projects as $proj) {
					$prj[] = $proj['code'];
				}
			} else {
				$prj = array_keys($permit[$lvl]); // Get allowed projects
			}
			return $prj;
		}

		if($prop === 'unt'){
			$unt = 0;
			//Set Unit access
			if(empty($proj)){
				if(isset($_SESSION['project']['code'])) $proj = $_SESSION['project']['code'];
			}
			if(isset($proj)){
				if(!isset($unit)){
					if(isset($_SESSION['project']['unit'])) $unit = $_SESSION['project']['unit'];
				}
			}
			if(isset($unit)){
				if($lvl > 6){
					$unt = '7';
				} else {
					$unit_key = array_search($unit, array_column(array_column(include('presets/units.php'),'name'),0));
					if(!empty($permit[$lvl]) && isset($permit[$lvl][$proj][$unit_key])) //<< CHECK SECOND CONDITION
					$unt = explode(',',$permit[$lvl][$proj])[$unit_key];
				}
			}
			return $unt;
		}
    }

    // Return user preference
    public function pref(string $prop){
    	if($this->id){
	    	if(in_array($prop, array_keys(json_decode($this->pref,true)))){
	    		return json_decode($this->pref)->$prop;
	    	}
	    }
    }

    public function avatar($id=0){
    	$id = $this->id;
    	$avatar = glob($this->avatarpath.$id.'.*'); // < USE $file->getPath()?
		$avatar = (count($avatar)>0) ? $avatar[0] : $this->avatarpath.'0.jpg';
		return $avatar;
    }

    public function logout($mode=0){ // Only self. Mode 0 is manual logout, mode 1 is auto

    	$this->setState(0);

    	if($mode){ // Auto logout, session timeout
    		$mode = 1; // Reset positive value to 1, in case
    		$uptime = $this->ontime + ($_SESSION['user']['lastact'] - $_SESSION['user']['thislogin']) + conf('session_timeout','user')*60;
    		$msg = "Session timed out. Please login again.";
    	} else { // Manual logout
    		$uptime = $this->ontime + time() - $_SESSION['user']['thislogin'];
    		$msg = "Logged out successfully.";
    	}
    	global $db;
    	$update = $this->setLast(1,1,0);
    	$update['ontime'] = $uptime;
		$db->table($this->site.'_user')->where('id', $this->id)->update($update);
		foreach ($_SESSION as $key => $value) { unset($_SESSION[$key]); }
        global $page;
		$page->message($msg,$mode+1);
    	$page->redirect();
    }
}