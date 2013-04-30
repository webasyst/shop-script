<?php

class shopFrontendTagAction extends shopFrontendAction
{
    public function execute()
    {
        $tag = waRequest::param('tag');
        $this->setCollection(new shopProductsCollection('tag/'.waRequest::param('tag')));
        $this->setThemeTemplate('search.html');
        $this->view->assign('frontend_search', array());
        $this->view->assign('title', waRequest::param('tag'), true);
        $this->getResponse()->setTitle(htmlspecialchars($tag).' — '.$this->getStoreName());
    }

}