<?php

if (empty($_SESSION['project_code'])) {
    $passfrag = ['account/login', 'account/repass']; // First one must be the redirect (which nullifies infinite loop)
    $pass = 0;
    foreach ($passfrag as $ignore) {
        $ignore = Helper::pathbound($ignore);
        if (substr(Helper::pathbound(self::$request), 0, strlen($ignore)) === $ignore) {
            $pass++;
        }

    }
    if ($pass === 0) {
        $el = new $el;
        $el->view->redirect($passfrag[0]);
    }
}