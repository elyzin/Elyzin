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
	public $id = 0;
	public $dispname;
	public $permit;
	protected $who = 'other'; // 'self' represents object of user operating this class
	public $error;
	protected $di;
	protected $message;
	private $pref;
	private $userstatpath = '';
	private $avatarpath = '';

	public function __construct(db $db, Clock $clock)
	{
		$this->userstatpath = syspath('userstat');
		$this->avatarpath = syspath('avatar');
		$this->di['db'] = $db;
		$this->di['clock'] = $clock;
		$this->setUser();
	}

	public function setUser(int $id = 0)
	{
		if ($id === 0 && isset($_SESSION['user']['id'])) $this->id = (int)$_SESSION['user']['id'];
		if (isset($_SESSION['user']['id']) && $this->id === (int)$_SESSION['user']['id']) $this->who = 'self';
		if ($this->id > 0) {
			$details = (array)$this->di['db']->table(conf('basename') . '_user')->where(['id' => $this->id])->get(); //USE CACHE
			if (!empty($details)) {
				foreach ($details as $key => $value) $this->{$key} = $value; // Set all fields to class
				if (empty($this->pref)) $this->pref = json_encode(parse_ini_file(ART . 'core/conf/userpref.ini')); // Set default values if preference not defined
				if (empty($this->passdate)) $this->passdate = $this->regdate; // Set registration date as password set date if not available
				$this->dispname = empty($this->fullname) ? $this->name : $this->fullname;
			} else {
				$this->unsetUser();
			}
		}
		// Set timezone as per user preference
		if (!empty($this->pref('timezone'))) {
			$timezone = $this->pref('timezone');
		} elseif (isset($_COOKIE['timeoffset'])) { // Detected browser time zone. Cookie set by jquery in base.js
			$timezone = timezone_name_from_abbr("", $_COOKIE['timeoffset'] * 60, false);
		} else { // Load timezone from site configuration
			$timezone = conf('timezone');
		}
		date_default_timezone_set($timezone);
		if($this->id > 0) $_SESSION['user']['timezone'] = $timezone; // Save user timezone to session, reduce dependency
	}

	protected function unsetUser()
	{
		$this->id = 0;
		$this->dispname = "";
		$this->who = "other";
		$this->permit = null;
		unset($this->name);
	}

	public function setLast(bool $login = false, bool $proj = false, bool $commit = true)
	{ //<< DEPRECIATE
		if ($this->who != 'self') {// Self environment method only
			$this->error = 'Modifying last actions of other users restricted.';
			return false;
		}

		$update = array();

		if ($login) { // Last login time to update
			if (!isset($_SESSION['user']['thislogin'])) {
				$this->error = 'Unidentifined login time.';
			} else {
    			// While changing last login to this in database, actual last login can only be accessed through session vars
				$update['lastlogin'] = $this->di['clock']->stamp($_SESSION['user']['thislogin'], 'db');
			}
		}

		if ($proj) { // Last project to update
			if (!isset($_SESSION['project']['code'])) {
				$this->error = 'Undefined current project.';
			} else {
    			// While changing last project to this in database, actual last ptoject can only be accessed through session vars
				$update['lastproj'] = $_SESSION['project']['code'];
			}
		}

		if (count($update)) {
			if ($commit) { // Commit changes to database
				if ($this->di['db']->table(conf('basename') . '_user')->where('id', $this->id)->update($update)) {
					reCache(conf('basename') . '_user');
					return true; // Commit Succeeded
				} else {
					$this->error = 'Database Operations Failed.';
					return false;
				}
			} else {
				return $update; // Return update array for some other function to commit changes in database (reduce query count)
			}
		}
		return false;
	}

	public function getState(bool $mode = false)
	{
		// Code this, use as for each user. Depreciate users_getstate();
		return users_getstate($mode);
	}

	public function setState(int $state = 3)
	{
		if ($state >= 0 && $state <= 3) { //Ensure range. 0 = Offline, 1 = Away, 2 = Busy, 3 = Online
			$status[] = array('uid' => $this->id, 'name' => $this->dispname, 'last_seen' => $this->di['clock']->stamp(time(), 'db'), 'state' => $state);
			$file_handle = fopen($this->userstatpath . $this->id . '.stt', "w");
			$result = fwrite($file_handle, json_encode($status)); // SAFE WRITER??
			fclose($file_handle);
			return $result;
		} else {
			return false;
		}
	}

	public function permit(string $prop = 'lvl', string $proj = null, string $unit = null)
	{
		$permit = json_decode($this->permit, true);
		if (!is_array($permit)) $permit = [];
		if (!sizeof($permit) || !in_array($prop, ['lvl', 'prj', 'unt'])) return 0;

		$lvl = key($permit);
		if ($prop === 'lvl') return $lvl;

		if ($prop === 'prj') {
			$prj = array(); // Blank container
			if ($lvl >= 6) { // Have access to all projects
				// Get all projects
				$all_projects = [];//enCache('SELECT code FROM '.conf('basename').'_projects');
				foreach ($all_projects as $proj) {
					$prj[] = $proj['code'];
				}
			} else {
				$prj = array_keys($permit[$lvl]); // Get allowed projects
			}
			return $prj;
		}

		if ($prop === 'unt') {
			$unt = 0;
			//Set Unit access
			if (empty($proj)) {
				if (isset($_SESSION['project']['code'])) $proj = $_SESSION['project']['code'];
			}
			if (isset($proj)) {
				if (!isset($unit)) {
					if (isset($_SESSION['project']['unit'])) $unit = $_SESSION['project']['unit'];
				}
			}
			if (isset($unit)) {
				if ($lvl > 6) {
					$unt = '7';
				} else {
					$unit_key = array_search($unit, array_column(array_column(include('presets/units.php'), 'name'), 0));
					if (!empty($permit[$lvl]) && isset($permit[$lvl][$proj][$unit_key])) //<< CHECK SECOND CONDITION
					$unt = explode(',', $permit[$lvl][$proj])[$unit_key];
				}
			}
			return $unt;
		}
	}

    // Return user preference
	public function pref(string $prop)
	{
		if ($this->id) {
			if (in_array($prop, array_keys(json_decode($this->pref, true)))) {
				return json_decode($this->pref)->$prop;
			}
		}
	}

	/**
	 * Generates user avatar image path
	 *
	 * @param integer $id // User ID
	 * @return string
	 * @todo Gravatar Support
	 */
	public function avatar($id = 0)
	{
		$id = $this->id;
		$avatar = glob($this->avatarpath . $id . '.*'); // < USE $file->getPath()?
		$avatar = (count($avatar) > 0) ? $avatar[0] : $this->avatarpath . '0.jpg';
		return $avatar;
	}
}