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

        /**
         * @event backend_category_dialog
         * @param array $category
         * @return array[string][string] $return[%plugin_id%] html output for dialog
         */
        $this->view->assign('event_dialog', wa()->event('backend_category_dialog', $settings));

        $category_routes_model = new shopCategoryRoutesModel();
        $settings['routes'] = $category_routes_model->getRoutes($id);

        $settings['frontend_urls'] = array();
        foreach ($category_model->getFrontendUrls($id, true) as $frontend_url) {
            $pos = strrpos($frontend_url, $settings['url']);
            $settings['frontend_urls'][] = array(
                'url' => $frontend_url,
                'base' => $pos !== false ? rtrim(substr($frontend_url, 0, $pos),'/').'/' : ''
            );
        }

        $settings['params'] = $category_params_model->get($id);
        if (isset($settings['params']['enable_sorting'])) {
            $settings['enable_sorting'] = 1;
            unset($settings['params']['enable_sorting']);
        } else {
            $settings['enable_sorting'] = 0;
        }

        $feature_model = new shopFeatureModel();
        $selectable_and_boolean_features = $feature_model->select('*')->
            where("(selectable=1 OR type='boolean' OR type='double' OR type LIKE 'dimension\.%' OR type LIKE 'range\.%') AND parent_id IS NULL")->fetchAll('id');

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

            // extract conditions for features
            foreach ($settings['conditions'] as $name => $value) {
                if (substr($name, -9) === '.value_id') {
                    unset($settings['conditions'][$name]);
                    $settings['conditions']['feature'][substr($name, 0, -9)] = $value;
                }
            }

            $settings['custom_conditions'] = $this->extractCustomConditions($settings['conditions']);

            $settings['features'] = $selectable_and_boolean_features;
            $settings['features'] = $feature_model->getValues($settings['features']);
        }

        $filter = $settings['filter'] !== null ? explode(',', $settings['filter']) : null;
        $feature_filter = array();
        $features['price'] = array(
            'id' => 'price',
            'name' => 'Price'
        );
        $features += $selectable_and_boolean_features;
        if (!empty($filter)) {
            foreach ($filter as $feature_id) {
                $feature_id = trim($feature_id);
                if (isset($features[$feature_id])) {
                    $feature_filter[$feature_id] = $features[$feature_id];
                    $feature_filter[$feature_id]['checked'] = true;
                    unset($features[$feature_id]);
                }
            }
        }
        $settings['allow_filter'] = (bool)$filter;
        $settings['filter'] = $feature_filter + $features;

        $category_og_model = new shopCategoryOgModel();
        $settings['og'] = $category_og_model->get($id);

        if (!empty($settings['parent_id'])) {
            $parent = $category_model->getById($settings['parent_id']);
        }
        if (empty($parent)) {
            $parent = array();
        }
        $this->view->assign('parent', $parent);

        return $settings;
    }

    /**
     * @param array $conditions
     * @return string
     */
    protected function extractCustomConditions($conditions)
    {
        foreach (array('price', 'tag', 'rating', 'feature', 'count', 'compare_price') as $name) {
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

        /**
         * @event backend_set_dialog
         * @param array $settings
         * @return array[string][string] $return[%plugin_id%] html output for dialog
         */
        $this->view->assign('event_dialog', wa()->event('backend_set_dialog', $settings));
        return $settings ? $settings : array();
    }


}
