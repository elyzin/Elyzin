<?php

namespace Elyzin\Controller;

use Config;
use File;
use Markup;
use Scrutiny;

class Epistle extends App
{
    protected $project;

    public function __construct()
    {
        //$this->project = $this->getStake('project', $_SESSION['project']);
    }

    function default() {
        $this->view->setAsset('drag-arrange|js');
        $this->view->render("epistle.dashboard", [])->set();
    }
}
