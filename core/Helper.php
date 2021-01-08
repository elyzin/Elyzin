<?php

namespace Elyzin\Core;

class Helper
{
    static $functions = [];

    public static function __callstatic($name, $arguments)
    {
        if (!isset(self::$functions[$name])) {
            $helper_path = Config::path('helpers') . $name . '.php';
            if (file_exists($helper_path)) {
                self::$functions[$name] = include $helper_path;
            }
        }

        if (isset(self::$functions[$name])) {
            return call_user_func_array(self::$functions[$name], $arguments);
        } else {
            trigger_error('Helper not found: ' . $name, E_USER_ERROR);
        }
    }
}