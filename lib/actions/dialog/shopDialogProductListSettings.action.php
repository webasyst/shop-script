<?php

class shopDialogProductListSettingsAction extends waViewAction
{
    public function execute()
    {
        $hash = $this->getHash();
        if (!$hash) {
            throw new waException("Unknown type of list");
        }

        $this->template = 'DialogProduct'.ucfirst($hash[0]).'Settings';
        $this->view->assign(array(
            'hash' => $hash,
            'currency' => wa()->getConfig()->getCurrency(),
            'settings' => $this->getSettings($hash[0], $hash[1]),
            'lang' => substr(wa()->getLocale(), 0, 2),
            'routes' => wa()->getRouting()->getByApp('shop')
        ));
    }

    private function getHash()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        if ($category_id) {
            return array('category', $category_id);
        }
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);
        if ($set_id) {
            return array('set', $set_id);
        }
        return null;
    }

    private function getSettings($type, $id)
    {
        if ($type == 'category') {
            return $this->getCategorySettings((int)$id);
        }
        if ($type == 'set') {
            return $this->getSetSettings($id);
        }
        return array();
    }

    private function getCategorySettings($id)
    {
        $category_model = new shopCategoryModel();
        $category_params_model = new shopCategoryParamsModel();

        $settings = $category_model->getById($id);
        if (!$settings) {
            return array();
        }

        $category_routes_model = new shopCategoryRoutesModel();
        $settings['routes'] = $category_routes_model->getRoutes($id);

        /**
         * @event backend_category.dialog
         * @param array $category
         * @return array[string][string] $return[%plugin_id%] html output for dialog
         */
        $this->view->assign('event_dialog', wa()->event('backend_category_dialog', $settings));

        $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $settings['full_url']), true);
        if ($frontend_url) {
            $pos = strrpos($frontend_url, $settings['url']);
            $settings['frontend_base_url'] = $pos !== false ? rtrim(substr($frontend_url, 0, $pos),'/').'/' : '';
        }
        $settings['frontend_url'] = $frontend_url;

        $settings['params'] = $category_params_model->get($id);
        if (isset($settings['params']['enable_sorting'])) {
            $settings['enable_sorting'] = 1;
            unset($settings['params']['enable_sorting']);
        } else {
            $settings['enable_sorting'] = 0;
        }

        if ($settings['type'] == shopCategoryModel::TYPE_DYNAMIC) {
            if ($settings['conditions']) {
                $settings['conditions'] = shopProductsCollection::parseConditions($settings['conditions']);
            } else {
                $settings['conditions'] = array();
            }

            $tag_model = new shopTagModel();
            $cloud = $tag_model->getCloud('name');
            if (!empty($settings['conditions']['tag'][1])) {
                foreach ($settings['conditions']['tag'][1] as $tag_name) {
                    $cloud[$tag_name]['checked'] = true;
                }
            }
            $settings['cloud'] = $cloud;

            $settings['custom_conditions'] = $this->extractCustomConditions($settings['conditions']);

        }

        $filter = $settings['filter'] !== null ? explode(',', $settings['filter']) : null;

        $feature_model = new shopFeatureModel();
        $features['price'] = array(
            'id' => 'price',
            'name' => 'Price'
        );
        $features += $feature_model->getFeatures('selectable', 1);
        $features += $feature_model->getFeatures('type', 'boolean');
        if (!empty($filter)) {
            foreach ($filter as $feature_id) {
                $feature_id = trim($feature_id);
                if (isset($features[$feature_id])) {
                    $features[$feature_id]['checked'] = true;
                }
            }
        }
        $settings['allow_filter'] = (bool)$filter;
        $settings['filter'] = $features;

        return $settings;
    }

    /**
     * @param array $conditions
     * @return string
     */
    protected function extractCustomConditions($conditions)
    {
        foreach (array('price', 'tag', 'rating') as $name) {
            if (isset($conditions[$name])) {
                unset($conditions[$name]);
            }
        }
        $custom_conditions = array();
        foreach ($conditions as $name => $value) {
            $custom_conditions[] = $name . implode('', $value);
        }
        return implode('&', $custom_conditions);
    }

    private function getSetSettings($id)
    {
        $set_model = new shopSetModel();
        $settings = $set_model->getById($id);
        return $settings ? $settings : array();
    }


}
