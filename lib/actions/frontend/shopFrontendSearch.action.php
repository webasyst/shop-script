<?php

class shopFrontendSearchAction extends shopFrontendAction
{
    public function execute()
    {
        $query = waRequest::get('query');
        $this->setCollection(new shopProductsCollection('search/query='.$query));

        $query = htmlspecialchars($query);
        $this->view->assign('title', $query);
        $this->getResponse()->setTitle(_w('Search').' - '.$query);

        if ($this->layout) {
            $this->layout->assign('query', $query);
        }
        if (!$query) {
            $this->view->assign('sorting', true);
        }
        $this->setThemeTemplate('search.html');
    }
}