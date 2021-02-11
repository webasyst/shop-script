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

        $this->setCanonical();

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

    public function setCanonical()
    {
        $brand = waRequest::param('brand');

        // If brand has spaces ..
        $brand_has_spaces = preg_match("/\s+/", $brand);
        if ($brand_has_spaces) {

            $request_uri = wa()->getConfig()->getRequestUrl(false, true);

            // and request uri has %20
            // than mark canonical url with +, because plugin use urlencode everywhere when encode urls (urlencode convert spaces to '+'s)
            if (strpos($request_uri, '%20') !== false) {
                $request_uri = str_replace('%20', '+', $request_uri);
                $this->getResponse()->setCanonical(wa()->getConfig()->getHostUrl() . $request_uri);

                // no need call parent method, cause parent doesn't know about spaces encoding
                return;
            }

        }

        $this->getResponse()->setCanonical();

    }
}
