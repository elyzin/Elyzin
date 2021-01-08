<?php

namespace Elyzin\Controller;

use Config;
use Security;
use Scrutiny;
use Helper;

class App
{
    public static $request = null;
    protected static $method = 'default';
    protected $stake_identity = ['user' => 'username', 'project' => 'code', 'organization' => 'shortname'];

    // Initiator, also acts as router
    public static function run()
    {
        $ns = '\\' . __NAMESPACE__ . '\\';
        $el = __CLASS__;
        self::$request = strip_tags(array_shift($_GET)); // Request chunk ported through .htaccess
        $args = [];

        $request = array_values(array_filter(explode('/', self::$request)));

        // Set request method here
        define($_SERVER['REQUEST_METHOD'], true);
        define('AJAX', !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

        if (defined('POST') && !Security::checkCsrfToken($_POST['token'])) {
            (new $el)->error(401);
        }

        // Run all middlewares here
        foreach (glob(Config::path('middlewares') . '*.php') as $middleware) {
            //require_once $middleware;
        }

        // Post-middleware. Process request
        if (!empty($request)) {
            $request[0] = \ucfirst(\strtolower($request[0]));
            if (class_exists($ns . $request[0]) && is_subclass_of($ns . $request[0], $el)) {
                $el = $ns . $request[0];

                if (isset($request[1])) {
                    self::$method = $request[1];
                }

                if (method_exists($el, self::$method)) {
                    if (count($request) > 2) {
                        $args = array_slice($request, 2);
                    }
                    // Check for required argument count
                    $rm = new \ReflectionMethod($el, self::$method);
                    if ($rm->getNumberOfParameters() < count($args) || count($args) < $rm->getNumberOfRequiredParameters()) {
                        (new $el)->error(400); // Bad request
                    }
                } else {
                    (new $el)->error(); // Default, 404, Page not found
                }
            } else if (file_exists(DRT . 'legacy/' . $request[0] . '.php')) { // Legacy support
                $el = new $el;
                $args = array_slice($request, 1);
                include_once DRT . 'legacy/' . $request[0] . '.php';
                $el->view->out();
            } else {
                (new $el)->error(); // Invalid request
            }
        }

        $permit = ltrim($el, $ns);
        $el = new $el;

        // Check the permission of the current user against request.
        if ($el->checkPermit($permit, self::$method)) {
            // All good. We can call the method.
            call_user_func_array(array($el, self::$method), $args);
            //echo $el->dbase->table('user')->checksum()->Checksum;
            $el->view->out();
        } else {
            $el->error();
        }
    }

    // Magic method to auto inject the class to the container if not already injected
    public function __get($prop)
    {
        $prop = trim($prop);
        if (!isset($this->$prop)) {
            $class = Config::fetch('alias', \ucfirst($prop));
            if (!empty($class)) {
                $class = '\\' . rtrim(ltrim($class, '\\'), '()');
                $this->$prop = new $class();
            } else {
                // Throw error
                throw new \Exception("Class " . \ucfirst($prop) . " is not available!");
            }
        }
        return $this->$prop;
    }

    // Application default dashboard
    function default() {
        $args = ["type" => "error", "message" => "This is a test error message..."];
        $this->view->infix(["globalnotice" => $this->view->render('frame.notice', $args)->get()]);
        //print_r($this->getStake('user', 1));
        $this->view->message('underdevdesc', 'info');
        //$this->view->message('somesprintf', 'success', ['effone']);
        $this->view->render('<div class="matter">Application Default Dashboard Loaded</div>', [], true)->set();
        
        // echo $this->autorsz_compress_file(DRT.'test.gif');
    }

    protected function autorsz_compress_file($source, $destination = NULL) {
        $quality = 20; // Drive through settings
    
        if(empty($destination)) $destination = $source;
    
        $info = getimagesize($source);
        switch ($info['mime']) {
            case 'image/pjpeg':
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($source);
                imagejpeg($image, $destination, $quality);
                imagedestroy($image);
                break;
    
            case 'image/gif':
                if(preg_match('#(\x00\x21\xF9\x04.{4}\x00\x2C.*){2,}#s', file_get_contents($source)) != 1) // Leave animated GIFs as is for now
                {
                    $image = imagecreatefromgif($source);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    imagegif($image, $destination);
                    imagedestroy($image);
                }
                break;
    
            case 'image/png':
            case 'image/x-png':
                $image = imagecreatefrompng($source);
                imageAlphaBlending($image, true);
                imageSaveAlpha($image, true);
                $quality = 9 - round(($quality / 100 ) * 9 );
                imagePng($image, $destination, $quality);
                imagedestroy($image);
                break;
    
            default:
                # Do nothing...
                break;
        }
        return $destination;
    }

    protected function getStake(string $type, $identity = "")
    {
        $type = strtolower($type);
        if ($type == 'proj') {
            $type = 'project';
        }

        if ($type == 'org') {
            $type = 'organization';
        }

        if (array_key_exists($type, $this->stake_identity)) {
            if (empty($identity)) { // Get all
                return $this->dbase->table($type)->getAll();
            } else if (is_string($identity)) {
                $identity = explode(',', $identity);
                $num = $str = [];
                foreach ($identity as $id) {
                    if (is_int($id)) {
                        $num[] = $id;
                    } else {
                        $str[] = $id;
                    }
                }
            }

            switch (true) {
                case (!empty($num) && !empty($str)):
                    return $this->dbase->table($type)->in('id', $num)->orIn($this->stake_identity[$type], $str)->getAll();
                    break;

                case (!empty($num) && empty($str)):
                    return $this->dbase->table($type)->in('id', $num)->getAll();
                    break;

                case (empty($num) && !empty($str)):
                    return $this->dbase->table($type)->in($this->stake_identity[$type], $str)->getAll();
                    break;

                default:
                    return $this->dbase->table($type)->getAll();
                    break;
            }

        } else {
            trigger_error("Invalid Stake call: {$type}", E_USER_ERROR);
        }
    }

    protected function checkPermit($module, $method)
    {
        if (!in_array($module, ['App', 'Account', 'Support'])) { // Omit allowed modules
            //return false;
		}
		return true;
    }

    public function error($code = 404)
    {
        $this->view->error($code);
    }
}
