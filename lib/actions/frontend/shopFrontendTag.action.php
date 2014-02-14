<?php

class shopFrontendTagAction extends shopFrontendAction
{
    public function execute()
    {
        $tag = waRequest::param('tag');
        $this->setCollection(new shopProductsCollection('tag/'.waRequest::param('tag')));

        $this->view->assign('title', waRequest::param('tag'), true);
        $this->getResponse()->setTitle(htmlspecialchars($tag).' — '.$this->getStoreName());

        $this->addCanonical();

        /**
         * @event frontend_search
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_search', wa()->event('frontend_search'));
        $this->setThemeTemplate('search.html');
    }

}