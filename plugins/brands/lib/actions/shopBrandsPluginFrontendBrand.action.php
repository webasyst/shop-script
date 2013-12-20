<?php

class shopBrandsPluginFrontendBrandAction extends shopFrontendAction
{
    public function execute()
    {
        $brand = waRequest::param('brand');
        $feature_model = new shopFeatureModel();
        $feature_id = wa()->getSetting('feature_id', '', array('shop', 'brands'));
        $feature = $feature_model->getById($feature_id);

        $values_model = $feature_model->getValuesModel($feature['type']);
        $value_id = $values_model->getValueId($feature_id, $brand);

        if (!$value_id) {
            throw new waException('Brand not found', 404);
        }

        $c = new shopProductsCollection();
        $c->filters(array($feature['code'] => $value_id));
        $this->setCollection($c);

        $this->view->assign('title', htmlspecialchars($brand));
        $this->getResponse()->setTitle($brand);

        /**
         * @event frontend_search
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_search', wa()->event('frontend_search'));
        $this->setThemeTemplate('search.html');

        waSystem::popActivePlugin();
    }

}
