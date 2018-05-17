<?php

class clock
{
	protected $utc;
	protected $user;
	protected $site;

    public function __construct()
    {
    }

    public function read($secs=0){
	    $bit = array(
	        ' year'     => $secs / 31556926 % 12,
	        ' week'     => $secs / 604800 % 52,
	        ' day'      => $secs / 86400 % 7,
	        ' hour'     => $secs / 3600 % 24,
	        ' minute'   => $secs / 60 % 60,
	        ' second'   => $secs % 60
	        );
	    $ret = array();
	    foreach($bit as $k => $v){
	        if($v > 1)$ret[] = $v . $k . 's';
	        if($v == 1)$ret[] = $v . $k;
	        }
	    if(count($ret)>1){
	        array_splice($ret, count($ret)-1, 0, 'and'); // If single element left; this prepends unnecessary 'and'
	    }
	    $read = join(' ', $ret);
	    return (trim($read) === '') ? '0 second' : $read;
	}
	// Time Offset Handler
	// -----------------------------------------------------------------------
	/*
	Examples:
	// This will return 10800 (3 hours) ...
	$offset = get_timezone_offset('America/Los_Angeles','America/New_York');
	// or, if your server time is already set to 'America/New_York'...
	$offset = get_timezone_offset('America/Los_Angeles');
	// You can then take $offset and adjust your timestamp.
	$offset_time = time() + $offset;
	*/
	private function offset($remote_tz, $origin_tz = null){
	    if($origin_tz === null) {
	        if(!is_string($origin_tz = date_default_timezone_get())) {
	            return false; // A UTC timestamp was returned -- bail out!
	        }
	    }
	    $origin_dtz = new DateTimeZone($origin_tz);
	    $remote_dtz = new DateTimeZone($remote_tz);
	    $origin_dt = new DateTime("now", $origin_dtz);
	    $remote_dt = new DateTime("now", $remote_dtz);
	    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
	    return $offset;
	}

	// Returns the time offset of User / Site with UTC
	// -----------------------------------------------------------------------
	// example date('h:i:s',stamp(time(),'db'));
	public function stamp($stamp=0, $base=''){
	    $stamp = intval($stamp); // Sanitize
	    if(empty($stamp)) return false;
	    global $me;
	    $tz = empty($me->timezone) ? conf('timezone') : $me->timezone;
	    $tz = empty($tz) ? date_default_timezone_get() : $tz; // If nothing set server timezone
	    return $base == 'db' ? ($stamp - $this->offset('UTC', $tz)) : ($stamp + $this->offset('UTC', $tz));
	}

	// Returns user readable date from given yymmdd string
	// -----------------------------------------------------------------------
	public function datify($date='', $slot='day'){
	    if(!empty($date)){
	        if($slot === 'day') // Skipping other validations, internal use only
	        { return date('jS M\, Y', mktime(0, 0, 0, substr($date,2,2), substr($date,4,2), substr($date,0,2))); }
	        else if ($slot == 'week')
	        { return date('jS', mktime(0, 0, 0, 1, substr($date,6,2), 0)).' to '.date('jS M\, Y', mktime(0, 0, 0, substr($date,2,2), substr($date,4,2), substr($date,0,2))); }
	        else if ($slot == 'month')
	        { return date('M\, Y', mktime(0, 0, 0, substr($date,2,2), 1, substr($date,0,2))); }
	        else if ($slot == 'year')
	        { return date('Y', mktime(0, 0, 0, 1, 1, substr($date,0,2))); }
	        else
	        { return $slot; }
	    } else { return false; }
	}
}