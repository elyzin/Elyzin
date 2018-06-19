<?php

class log
{
	private $id;
	private $ip;
	private $user;
	protected $logpath;

	public function __construct()
	{
		global $me;
		$this->id = $me->id;
		$this->user = !$me->user ? 'guest' : $me->user;;
		$this->ip = $this->realIp();
		$this->logpath = syspath('log');

		// Log the hit automatically while creating log object. (don't reconstruct this object to avoid unwanted hit log)
		$this->hitTrack();
	}

	public function hitTrack()
	{
		$agent = $this->userAgent();
		$data[] = $_SERVER['REQUEST_URI'];
		$data[] = $agent['name'] . ' ' . $agent['version'] . ' on ' . $agent['platform'];
		$this->writeLog($data);
	}

	public function event($data, $base = 'user')
	{
		if (strlen($data)) {
			$logline[] = $data;
			$this->writeLog($logline, $base);
		}
	}

	protected function writeLog($data = [], $base = 'site')
	{
		$logbase = array_map('basename', glob($this->logpath . '*', GLOB_ONLYDIR));
		if (!empty($data) && in_array($base, $logbase)) {
			// Prepare basic info
			$moment = new clock();
			$logline[] = $this->ip;
			$logline[] = $this->id;
			$logline[] = $this->user;
			$logline[] = $moment->stamp(time(), 'db');

			// Append additional info and stringify
			$logline = array_merge($logline, $data);
			$logline = implode('|', $logline) . PHP_EOL;

			// Write the log line to file
			$file = new file();
			$logfile = $this->logpath . $base . '/' . gmdate('ymd') . '.log';
			$file->penSafe($logline, $logfile, 1);
		}
	}

	private function checkBlockIP(string $ip = '')
	{
		if (file_exists('blocked_ips.txt')) {
			if ($ip = '') $ip = $this->realIp();
			$deny_ips = file('blocked_ips.txt');
			if ((array_search($ip, $deny_ips)) !== false) return true;
			return false;
			//die('Your IP adress (' . $ip . ') was blocked!');
		}
	}

	private function realIp()
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	private function userAgent() // Because get_browser is still unreliable.
	{
		$u_agent = $_SERVER['HTTP_USER_AGENT'];
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version = "";

		if (preg_match('/linux/i', $u_agent)) {
			$platform = 'linux';
		} elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
			$platform = 'mac';
		} elseif (preg_match('/windows|win32/i', $u_agent)) {
			$platform = 'windows';
		}

		if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
			$bname = 'Internet Explorer';
			$ub = "MSIE";
		} elseif (preg_match('/Trident/i', $u_agent)) { // this condition is for IE11
			$bname = 'Internet Explorer';
			$ub = "rv";
		} elseif (preg_match('/Firefox/i', $u_agent)) {
			$bname = 'Mozilla Firefox';
			$ub = "Firefox";
		} elseif (preg_match('/Chrome/i', $u_agent)) {
			$bname = 'Google Chrome';
			$ub = "Chrome";
		} elseif (preg_match('/Safari/i', $u_agent)) {
			$bname = 'Apple Safari';
			$ub = "Safari";
		} elseif (preg_match('/Opera/i', $u_agent)) {
			$bname = 'Opera';
			$ub = "Opera";
		} elseif (preg_match('/Netscape/i', $u_agent)) {
			$bname = 'Netscape';
			$ub = "Netscape";
		}

		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) .
			')[/|: ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $u_agent, $matches)) {
		}

		$i = count($matches['browser']);
		if ($i != 1) {
			if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
				$version = $matches['version'][0];
			} else {
				$version = $matches['version'][1];
			}
		} else {
			$version = $matches['version'][0];
		}
		if ($version == null || $version == "") {
			$version = "?";
		}

		return array(
			'userAgent' => $u_agent,
			'name' => $bname,
			'version' => $version,
			'platform' => $platform,
			'pattern' => $pattern
		);
	}
}