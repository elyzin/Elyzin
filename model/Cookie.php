<?php

namespace Elyzin\Model;

class Cookie
{
    private $options = ['expire' => 0, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => false, 'samesite' => 'lax'];

    public function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
        $this->options['expire'] = \time() + 86400;
    }

    private function init()
    {
        // Load default settings
        $this->options = array_merge($this->options, ['domain' => 'elyzin.com', 'secure' => true, 'samesite' => 'strict']);
    }

    public function __call($key, $value)
    {
        if (array_key_exists($key, $this->options)) {
            if (!empty($value) && count($value) === 1) {
                $this->options[$key] = $value[0];
                return $this;
            } else {
                return $this->options[$key];
            }
        } else {
            throw new \Exception("Invalid method.");
        }
    }

    public function save()
    {
        if(!empty($this->name) && !empty($this->value))
        {
            \setcookie($this->name, $this->value, $this->options['expire'], $this->options['path'], $this->options['domain'], $this->options['secure'], $this->options['httponly'], $this->options['samesite']);
            $this->name = ""; // Prevent re-save on destruction
            return true;
        } else{
            return false;
        }
    }
}