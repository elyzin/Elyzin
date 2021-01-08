<?php
/**
 * @author Elyzin Devs <devs@elyz.in>
 * @since 1.0.0
 */

namespace Elyzin\Core;

use \Elyzin\Model\File;
use Security;

class Config
{
    protected static $conf = null;
    protected static $confpath;

    public static function init()
    {
        self::$confpath = strtolower(rtrim(__FILE__, '.php')).DIRECTORY_SEPARATOR;

        // Set Class Aliases
        $aliases = self::fetch('alias');
		foreach($aliases as $alias => $class){
			class_alias($class, $alias);
        }

        // Set environment variables (accessible through getenv() / $_ENV)
        $envs = self::fetch('env');
		foreach($envs as $set => $val){
			putenv($set.'='.$val);
        }

        // Set security options
        Security::conf(['site'=>'dtriv']);
    }

    public static function path(string $prop, bool $trail = true)
    {
        $path = self::fetch('path', $prop);

        // Lexis is language based. Append language directory
        if($prop == 'lexis') $path .= '/' . getenv('LANGUAGE');
        
        return rtrim(DRT . $path, '/\\') . ($trail ? DIRECTORY_SEPARATOR : '');
    }

    public static function fetch(string $type, $key = null)
    {
        if (!isset(self::$conf[$type])) {
            $config_file = self::$confpath . $type . '.ini';
            if (file_exists($config_file)) {
                self::$conf[$type] = File::read($config_file);
            } else {
                throw new \Exception("Configuration " . $type . " is missing!");
            }
        }
        if ($key !== null){
            return (isset(self::$conf[$type][$key])) ? self::$conf[$type][$key] : false;
        }
        return self::$conf[$type];
    }
}