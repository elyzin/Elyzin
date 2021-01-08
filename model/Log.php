<?php

namespace Elyzin\Model;

class Log
{
    use \Elyzin\Core\Traits\Writer;

    public static $filepath = '/storage/log/log.txt';

    public static function __callStatic($method, $logline)
    {
        if(in_array($method, ['admin', 'mod', 'user', 'system']))
        {
            self::write($logline, $method);
        }
        else
        {
            throw new \Exception("Invalid log method '" . $method . "'");
        }
    }

    public static function write($logline, $type = 'user')
    {

    }
}