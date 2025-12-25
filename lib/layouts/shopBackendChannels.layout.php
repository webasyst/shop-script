<?php

class shopBackendChannelsLayout extends shopBackendLayout
{
    public function execute()
    {
        // Depending on XRH/no XHR return full layout or main content only
        if (waRequest::isXMLHttpRequest()) {
            $this->template = 'string:{$content}';
            return waLayout::execute();
        }

        // wrap HTML from action with channels layout
        $view = wa('shop')->getView();
        $view->assign($this->blocks);
        $this->blocks['content'] = $view->fetch($this->getTemplate());

        // wrap channels layout with default layout
        $this->template = wa()->getAppPath('templates/layouts/Backend.html', 'shop');
        return parent::execute();
    }
}
