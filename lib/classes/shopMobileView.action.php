<?php

class shopMobileViewAction extends waViewAction
{
    public function getTemplate()
    {
        $template = parent::getTemplate();
        $template = str_replace('Mobile'.$this->view->getPostfix(), $this->view->getPostfix(), $template);
        return $template;
    }

    protected function getLegacyTemplateDir()
    {
        return 'templates/actions-mobile/';
    }

    protected function getTemplateDir()
    {
        return $this->getLegacyTemplateDir();
    }

    public function execute()
    {
        $this->setLayout(new shopMobileLayout());
    }
}

