<?php

namespace Elyzin\Controller;

use Config;
use File;
use Markup;
use Scrutiny;

class Support extends App
{
    function default() {
        $this->view->render("Support Dashboard Loaded", [], true)->set();
    }

    public function faq()
    {
        $vars['sections'] = "";
        $faqs = File::read(Config::path('lexis') . 'faq.php');
        foreach ($faqs as $section => $chunk) {
            $qas = '';
            foreach ($chunk as $qa) {
                $qas .= $this->view->render('support.faq.section.qa', ['question' => $qa[0], 'answer' => $qa[1]])->get();
            }
            $vars['sections'] .= $this->view->render('support.faq.section', ['section_head' => $section, 'qas' => $qas])->get();
        }
        $this->view->render('support.faq', $vars)->set();
    }

    public function contact()
    {
        if (defined('POST')) {
            $validation = Scrutiny::validate($_POST, "contact");
            //echo getenv('SITEMAIL');
        }
        if (!defined('POST') || !empty($error)) {
            $vars['contact_type'] = Markup::select([
                'name' => 'contact_type',
                'values' => 'contact.type',
                'placeholder' => 'Select a reason...',
            ]);
            $this->view->render('form.contact', $vars)->set();
        }
    }

    public function ticket()
    {

    }

    public function resource()
    {

    }
}
