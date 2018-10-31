<?php

/**
 * Class shopCategoryEditAction
 *
 * @see test tests/wa-apps/shop/actions/category/shopCategoryEditTest.php
 */
class shopCategoryEditAction extends waViewAction
{
    protected $template = 'wa-apps/shop/templates/actions/category/ProductsCategory.html';

    public function execute()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */

        $settings = $this->getCategorySettings((int)$category_id);

        $this->view->assign(array(
            'hash'              => array('category', $category_id),
            'currency'          => $config->getCurrency(),
            'settings'          => $settings,
            'lang'              => substr(wa()->getLocale(), 0, 2),
            'routes'            => wa()->getRouting()->getByApp('shop'),
            'parent'            => $this->getParent(ifset($settings, 'parent_id', null)),
            'frontend_base_url' => $this->getFrontendBaseUrl()
        ));
    }

    protected function getCategorySettings($id)
    {
        $category_model = new shopCategoryModel();
        $category_params_model = new shopCategoryParamsModel();
        $feature_model = new shopFeatureModel();
        $category_routes_model = new shopCategoryRoutesModel();
        $category_og_model = new shopCategoryOgModel();

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

        $settings['routes'] = $category_routes_model->getRoutes($id);
        $settings['frontend_urls'] = $this->getFrontendUrls($id, $settings);
        $settings['og'] = $category_og_model->get($id);
        $settings['has_children'] = $category_model->countByField('parent_id', $settings['id']);
        $settings['params'] = $category_params_model->get($id);
        $settings['cloud'] = $this->getTagsCloud();
        $settings['conditions'] = $this->parseConditions($settings['conditions']);
        $settings['custom_conditions'] = $this->extractCustomConditions($settings['conditions']);

        if (isset($settings['params']['enable_sorting'])) {
            $settings['enable_sorting'] = 1;
            unset($settings['params']['enable_sorting']);
        } else {
            $settings['enable_sorting'] = 0;
        }

        $explode_feature_ids = $settings['filter'] !== null ? explode(',', $settings['filter']) : [];
        $price = [
            'id'        => 'price',
            'name'      => 'Price',
            'type'      => '',
            'code'      => '',
            'type_name' => '',
        ];

        $settings['filter'] = [];
        $settings['filter']['price'] = $price;
        $settings['allow_filter'] = (bool)$explode_feature_ids;
        $settings['allow_filter_data'] = [];

        $filter = $settings['features'] = [];

        //Get Included filters
        if (!empty($explode_feature_ids)) {
            $allow_filter = $feature_model->getById($explode_feature_ids);

            foreach ($explode_feature_ids as $feature_id) {
                if (isset($allow_filter[$feature_id])) {
                    $settings['allow_filter_data'][$feature_id] = $allow_filter[$feature_id];
                }
                if ($feature_id === 'price') {
                    $settings['allow_filter_data'][$feature_id] = $price;

                    //remove to avoid duplication
                    unset($settings['filter']['price']);
                }
            }
        }

        if ($settings['type'] == shopCategoryModel::TYPE_DYNAMIC) {
            //GET FEATURES
            $saved_features = ifset($settings, 'conditions', 'feature', []);
            $options_feature = [
                'code'   => array_keys($saved_features),
                'status' => null,
            ];

            $features = $feature_model->getFilterFeatures($options_feature);
            $features = $feature_model->getValues($features, $this->extendSavedConditions($features, $saved_features));

            shopFeatureModel::appendTypeNames($features);

            //Get feature count
            $settings['feature_count'] = $feature_model->getFeaturesCount([
                'select'   => 'COUNT(*)',
                'frontend' => true,
                'status'   => null,
            ]);

            //GET FILTERS
            $options_filter = [
                'frontend'  => true,
                'ignore_id' => array_keys($settings['allow_filter_data'])
            ];
            $filter = $feature_model->getFilterFeatures($options_filter);
            shopFeatureModel::appendTypeNames($filter);

            //Get filter count
            $settings['filter_count'] = $feature_model->getFeaturesCount([
                'select'   => 'COUNT(*)',
                'frontend' => true,
            ]);

            $settings['filter'] += $filter;
            $settings['features'] = $features;
        }

        if ($settings['type'] == shopCategoryModel::TYPE_STATIC) {
            $options_feature = array(
                'frontend'  => true,
                'type_id'   => $this->getTypesId($id),
                'ignore_id' => array_keys($settings['allow_filter_data'])
            );

            $features = $feature_model->getFilterFeatures($options_feature);
            $settings['filter_count'] = $feature_model->getFeaturesCount([
                'select'    => 'COUNT(*)',
                'frontend'  => true,
                'type_id'   => $this->getTypesId($id),
                'ignore_id' => array_keys($settings['allow_filter_data'])
            ]);

            $filter += $features;
            shopFeatureModel::appendTypeNames($filter);

            $settings['filter'] += $filter;
        }

        return $settings;
    }

    /**
     * @param $features
     * @param $saved_conditions
     * @return array
     */
    protected function extendSavedConditions($features, $saved_conditions)
    {
        $parsed_conditions = [];

        foreach ($saved_conditions as $feature_code => $condition_data) {
            if (isset($features[$feature_code]) && isset($condition_data['values'])) {
                $parsed_conditions[$feature_code] = (array)$condition_data['values'];
            }
        }

        return $parsed_conditions;
    }

    /**
     * Parse conditions string to array. Redesigned this method shopCollection::parseConditions();
     * @param string $conditions
     * @return array|mixed|null
     * @see how it work in waContactsCollection::searchPrepare
     */
    protected function parseConditions($conditions = '')
    {
        if (!$conditions) {
            return $conditions;
        }

        $escapedBS = 'ESCAPED_BACKSLASH';
        while (false !== strpos($conditions, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while (false !== strpos($conditions, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }

        $conditions = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $conditions));
        $conditions = explode('&', $conditions);

        $result = [];

        foreach ($conditions as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\\\', '\\&'), $part);
            $temp = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);

            if ($temp) {
                $name = array_shift($temp);
                $feature_name = substr($name, 0, -9);
                $is_feature = substr($name, -9) === '.value_id';

                //Get previous saved values
                if ($is_feature) {
                    $tmp_result = ifset($result, 'feature', $feature_name, []);
                } else {
                    $tmp_result = ifset($result, $name, []);
                }

                if ($name == 'tag') {
                    $tmp_result['type'] = 'tag';
                    $tmp_result['tags'] = str_replace('\&', '&', $temp[1]); //Remove escape ampersand
                    $tmp_result['tags'] = explode('||', $tmp_result['tags']);
                } elseif ($name == 'rating' || $name == 'count') {
                    $tmp_result['type'] = $name;
                    $tmp_result['condition'] = $temp[0];
                    $tmp_result['values'] = preg_split('@[,\s]+@', $temp[1]);
                } elseif ($temp[0] == '>=') {
                    $tmp_result['type'] = 'range';
                    $tmp_result['begin'] = $temp[1];
                    $tmp_result['end'] = isset($tmp_result['end']) ? $tmp_result['end'] : null;
                } elseif ($temp[0] == '<=') {
                    $tmp_result['type'] = 'range';
                    $tmp_result['begin'] = isset($tmp_result['begin']) ? $tmp_result['begin'] : null;
                    $tmp_result['end'] = $temp[1];
                } else {
                    $tmp_result['type'] = 'equal';
                    $tmp_result['condition'] = $temp[0];
                    $tmp_result['values'] = preg_split('@[,\s]+@', $temp[1]);
                }

                //Set update/new values
                if ($is_feature) {
                    $result['feature'][$feature_name] = $tmp_result;
                } else {
                    $result[$name] = $tmp_result;
                }
            }
        }

        return $result;
    }

    protected function getParent($parent_id)
    {
        $category_model = new shopCategoryModel();
        $parent = array();

        if (!empty($parent_id)) {
            $parent = $category_model->getById($parent_id);
        }

        return $parent;
    }

    protected function getTypesId($id)
    {
        $product_collection = new shopProductsCollection("category/{$id}");
        $product_collection->groupBy('type_id');
        $types = $product_collection->getProducts('type_id');

        return waUtils::getFieldValues($types, 'type_id');
    }

    protected function getTagsCloud()
    {
        $tag_model = new shopTagModel();
        $cloud = $tag_model->getCloud('name');

        return $cloud;
    }

    protected function getFrontendUrls($id, $settings)
    {
        $category_model = new shopCategoryModel();
        $urls = $category_model->getFrontendUrls($id, true);

        $frontend_urls = [];

        foreach ($urls as $frontend_url) {
            $pos = strrpos($frontend_url, $settings['url']);
            $frontend_urls[] = array(
                'url'  => $frontend_url,
                'base' => $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : ''
            );
        }

        return $frontend_urls;
    }

    public function getFrontendBaseUrl()
    {
        $stuff = '%category_url%';
        $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $stuff), true);
        $pos = strrpos($frontend_url, $stuff);
        $frontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;

        return $frontend_base_url;
    }

    /**
     * todo maybe this deprecated
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
        if ($conditions) {
            foreach ($conditions as $name => $value) {
                $custom_conditions[] = $name.implode('', $value);
            }
        }

        return implode('&', $custom_conditions);
    }
}