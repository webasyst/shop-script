<?php

/**
 * Class shopCategoryEditAction
 *
 * @see test tests/wa-apps/shop/actions/category/shopCategoryEditTest.php
 */
class shopCategoryEditAction extends waViewAction
{
    // TODO Нужен шаблон для ВА2
    protected $template = 'wa-apps/shop/templates/actions/category/ProductsCategory.html';

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

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
        $category_routes_model = new shopCategoryRoutesModel();
        $category_og_model = new shopCategoryOgModel();
        $category_helper = new shopCategoryHelper();

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
        $settings['conditions'] = shopProdCategoryDialogAction::parseConditions($settings['conditions']);
        $settings['custom_conditions'] = $this->extractCustomConditions($settings['conditions']);
        $settings = $this->getSorting($settings);
        $settings['explode_feature_ids'] = $this->getExplodeFeatureIds($settings);
        $settings['filter'] = [
            'price' => $category_helper->getDefaultFilters()
        ];
        $settings['allow_filter'] = (bool)$settings['explode_feature_ids'];
        $settings['allow_filter_data'] = [];
        $settings['features'] = [];
        $settings = $this->getIncludedFilters($settings);

        if ($settings['type'] == shopCategoryModel::TYPE_DYNAMIC) {
            $settings = $this->getDynamicSettings($settings);
            $this->formatConditions($settings);
        } elseif ($settings['type'] == shopCategoryModel::TYPE_STATIC) {
            $settings = $this->getStaticSettings($settings);
        }

        $settings['conditions'] = $this->parseRangeValue($settings['features'], $settings['conditions']);
        return $settings;
    }

    protected function getSorting($settings)
    {
        if (isset($settings['params']['enable_sorting'])) {
            $settings['enable_sorting'] = 1;
            unset($settings['params']['enable_sorting']);
        } else {
            $settings['enable_sorting'] = 0;
        }

        return $settings;
    }

    protected function getExplodeFeatureIds($settings)
    {
        $result = [];

        if ($settings['filter'] !== null) {
            $result = explode(',', $settings['filter']);
        }

        return $result;
    }

    protected function getIncludedFilters($settings)
    {
        $feature_model = new shopFeatureModel();
        $category_helper = new shopCategoryHelper();

        if (!empty($settings['explode_feature_ids'])) {
            $allow_filter = $feature_model->getById($settings['explode_feature_ids']);

            foreach ($settings['explode_feature_ids'] as $feature_id) {
                if (isset($allow_filter[$feature_id])) {
                    $settings['allow_filter_data'][$feature_id] = $allow_filter[$feature_id];
                }
                if ($feature_id === 'price') {
                    $settings['allow_filter_data'][$feature_id] = $category_helper->getDefaultFilters();

                    //remove to avoid duplication
                    unset($settings['filter']['price']);
                }
            }
        }

        return $settings;
    }

    protected function getDynamicSettings($settings)
    {
        $category_helper = new shopCategoryHelper();
        $conditions = ifset($settings, 'conditions', 'feature', []);

        $options_feature_count = [
            'frontend' => true,
            'status'   => null,
        ];
        $settings['feature_count'] = $category_helper->getCount($options_feature_count);

        $options_filter_count = [
            'frontend' => true,
        ];
        $settings['filter_count'] = $category_helper->getCount($options_filter_count);

        $options_filter = [
            'frontend'  => true,
            'ignore_id' => array_keys($settings['allow_filter_data'])
        ];
        $settings['filter'] += $category_helper->getFilters($options_filter);

        $options_features = [
            'code' => array_keys($conditions),
            'ignore_complex_types' => true,
            'count' => false,
            'ignore' => false,
        ];
        $settings['features'] = $category_helper->getFilters($options_features);

        $all = $this->extendSavedConditions($settings['features'], $conditions);
        $settings['features'] = $category_helper->getFeaturesValues($settings['features'], $all);

        return $settings;
    }

    protected function getStaticSettings($settings)
    {
        $category_helper = new shopCategoryHelper();

        $type_id = $category_helper->getTypesId($settings['id']);

        $options = [
            'status'    => null,
            'frontend'  => true,
            'type_id'   => $type_id,
            'ignore_id' => array_keys($settings['allow_filter_data'])
        ];

        $settings['filter'] += $category_helper->getFilters($options);
        $settings['filter_count'] = $category_helper->getCount($options);

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

    protected function getParent($parent_id)
    {
        $category_model = new shopCategoryModel();
        $parent = array();

        if (!empty($parent_id)) {
            $parent = $category_model->getById($parent_id);
        }

        return $parent;
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
     * @param array $settings
     * @return void
     */
    protected function formatConditions(&$settings)
    {
        if (isset($settings['conditions']['type_id'])) {
            $type_model = new shopTypeModel();
            $settings['conditions']['type_id']['options'] = $type_model->select('`id`, `name`')->where('id IN (' . implode(',', array_map('intval', $settings['conditions']['type_id']['values'])) . ')')->fetchAll();
        }
        if (isset($settings['conditions']['feature']) && is_array($settings['conditions']['feature'])) {
            foreach ($settings['conditions']['feature'] as $code => &$condition) {
                if (isset($settings['features'][$code])) {
                    $feature = $settings['features'][$code];
                    if ($condition['type'] == 'range' && ($feature['type'] == 'range.date' || $feature['type'] == shopFeatureModel::TYPE_DATE)) {
                        $condition['begin'] = shopDateValue::timestampToDate($condition['begin']);
                        $condition['end'] = shopDateValue::timestampToDate($condition['end']);
                    } elseif (empty($feature['selectable']) && $condition['type'] == 'equal' && $feature['type'] == shopFeatureModel::TYPE_DATE) {
                        $selectable_values = [];
                        foreach ($feature['values'] as $selectable_value) {
                            $selectable_values[] = $selectable_value->timestamp;
                        }
                        $condition += [
                            'begin' => '',
                            'end' => ''
                        ];
                        if ($selectable_values) {
                            $condition['begin'] = shopDateValue::timestampToDate(min($selectable_values));
                            $condition['end'] = shopDateValue::timestampToDate(max($selectable_values));
                        }
                    }
                }
            }
            unset($condition);
        }
    }

    /**
     * todo. Please remove it sometime.
     * It is needed to upgrade to version 8 of the shop script
     * Previously, under strange conditions, you could save the range. Only not values, but specific id. This method converts such id to range.
     * I did not want to do this. I was tortured. Sorry T_T
     *
     * @param $features
     * @param $conditions
     * @return mixed
     * @see task #53.5941
     */
    protected function parseRangeValue($features, $conditions)
    {
        if ($features) {
            foreach ($features as $key => $feature) {
                $feature_type = ifset($feature, 'type', '');
                if (substr($feature_type, 0, 5) === 'range') {
                    $condition = ifset($conditions, 'feature', $feature['code'], '');

                    if ($condition) {
                        $condition_type = ifset($condition, 'type', '');
                        if ($condition_type === 'equal') {
                            $range_id = ifset($condition, 'values', 0, null);

                            //Get saved values
                            $range = ifset($feature, 'values', $range_id, []);
                            $condition = [
                                'type'  => 'range',
                                'begin' => ifset($range, 'begin_base_unit', 0),
                                'end'   => ifset($range, 'end_base_unit', 0),
                            ];
                            $conditions['feature'][$feature['code']] = $condition;
                        }
                    }
                }
            }
        }

        return $conditions;
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
                if (isset($value['type'])) {
                    if ($value['type'] == 'equal') {
                        if (!empty($value['condition']) && isset($value['values'])) {
                            $values = is_array($value['values']) ? implode(',', $value['values']) : $value['values'];
                            $custom_conditions[] = $name.$value['condition'].$values;
                        }
                    } else {
                        // not supported
                    }
                } else {
                    $custom_conditions[] = $name.implode('', $value);
                }
            }
        }

        return implode('&', $custom_conditions);
    }
}
