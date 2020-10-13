<?php

class shopBackendMarketingLayout extends shopBackendLayout
{
    public function execute()
    {
        // Depending on XRH/no XHR return full layout or main content only
        if (waRequest::isXMLHttpRequest()) {
            $this->template = 'string:{$content}';
            return waLayout::execute();
        }

        $this->executeAction('marketing_sidebar', new shopMarketingSidebarAction());

        // wrap HTML from action with marketing layout
        $view = wa('shop')->getView();
        $view->assign($this->blocks);
        $this->blocks['content'] = $view->fetch($this->getTemplate());

        $this->template = wa()->getAppPath('templates/layouts/Backend.html', 'shop');

        return parent::execute();
    }
}