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
        $value_id = $values_model->getId($feature_id, $brand);

        $c = new shopProductsCollection();
        $c->filters(array($feature['code'] => $value_id));
        $this->setCollection($c);

        $this->view->assign('title', htmlspecialchars($brand));
        $this->getResponse()->setTitle($brand);

        $this->setThemeTemplate('search.html');
    }

}
