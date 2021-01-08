<?php

namespace Elyzin\Controller;

use Security;

class Account extends App
{
    // Default function is to display control panel of the user
    function default() {
        if (!isset($_SESSION['user_id'])) {
            $this->login();
        } else {
            $user = $this->getStake('user', $_SESSION['user_id']);
            print_r($user);
        }
    }

    public function profile()
    {
    }

    public function register()
    {
        //$data['pass'] = Security::makePass($_POST['pass']);
    }

    public function login()
    {
        // Check if user is already logged in.
        $this->view->bare = true;
        $args = [];
        if (defined('POST')) {
            //if($_POST['user'] == "")
            //if($_POST['pass'] == "")
            
            // Get the user
            $user = $this->getStake('user', $_POST['user']);
            die(json_encode($user));
            // Validate Password
            if (Security::checkPass($_POST['pass'], $user['pass'])){
                //echo "Passed";
            }
        }

        $this->view->render('form.login', $args)->set();
    }

    public function logout()
    {
        $_SESSION = [];
        \session_unset();
        \session_destroy();
    }
}