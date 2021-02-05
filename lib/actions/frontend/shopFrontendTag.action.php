<?php

class shopFrontendTagAction extends shopFrontendAction
{
    public function execute()
    {
        $tag = waRequest::param('tag');

        $collection = new shopProductsCollection('tag/'.$tag);
        if ($collection->count() <= 0 && !$this->doesTagExist($tag)) {
            throw new waException('Tag not found', 404);
        }

        $this->setCollection($collection);

        $this->view->assign('title', waRequest::param('tag'), true);
        $this->getResponse()->setTitle(htmlspecialchars($tag).' — '.$this->getStoreName());

        $this->getResponse()->setCanonical();

        /**
         * @event frontend_search
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_search', wa()->event('frontend_search'));
        $this->setThemeTemplate('search.html');
    }

    public function doesTagExist($id)
    {
        $tag_model = new shopTagModel();

        $tag = $tag_model->getByName($id);

        if (is_numeric($id) && !$tag) {
            $tag = $tag_model->getById($id);
        }

        if (!$tag) {
            return false;
        }
    }
}
