<?php

class shopMobileViewAction extends waViewAction
{
    public function getTemplate()
    {
        $template = parent::getTemplate();
        $template = str_replace('templates/actions/', 'templates/actions-mobile/', $template);
        $template = str_replace('Mobile'.$this->view->getPostfix(), $this->view->getPostfix(), $template);
        return $template;
    }

    public function execute()
    {
        $this->setLayout(new shopMobileLayout());
    }
}

