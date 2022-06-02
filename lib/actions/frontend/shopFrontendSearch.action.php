<?php

class shopFrontendSearchAction extends shopFrontendAction
{
    public function execute()
    {
        $query = waRequest::get('query');
        try {
            $this->setCollection(new shopProductsCollection('search/query='.str_replace('&', '\&', $query)));
        } catch (waDbException $dbe) {
            $this->view->assign('products', []);
        }

        $query = htmlspecialchars($query);
        $this->view->assign('title', $query);
        $this->getResponse()->setTitle($query.' — '.$this->getStoreName());

        if ($this->layout) {
            $this->layout->assign('query', $query);
        }
        if (!$query) {
            $this->view->assign('sorting', true);
        }

        $units = shopHelper::getUnits();
        $this->view->assign('units', $units);
        $this->view->assign('formatted_units', shopFrontendProductAction::formatUnits($units));
        $this->view->assign('fractional_config', shopFrac::getFractionalConfig());

        /**
         * @event frontend_search
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_search', wa()->event('frontend_search'));
        $this->setThemeTemplate('search.html');
    }
}