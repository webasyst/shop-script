<?php

class shopCategoryCreateAction extends waViewAction
{
    protected $template = 'wa-apps/shop/templates/actions/category/ProductsCategory.html';

    public function execute()
    {
        $tag_model = new shopTagModel();

        $stuff = '%category_url%';
        $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $stuff), true);
        $pos = strrpos($frontend_url, $stuff);
        $frontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;

        $feature_model = new shopFeatureModel();
        $parent = $this->getParentCategory();

        $settings = $this->getSettings();

        if ($parent) {
            $settings['routes'] = $parent['routes'];
            $settings['parent_id'] = $parent['id'];
        }

        $options = array(
            'frontend' => true,
            'status'   => null,
        );
        $features = $feature_model->getFilterFeatures($options, 20);

        //Get feature count
        $options_feature_count = [
            'select'   => 'COUNT(*)',
            'frontend' => true,
            'status'   => null,
        ];

        $feature_count = $feature_model->getFilterFeatures($options_feature_count, 500, false);
        $settings['feature_count'] = ifset($feature_count, 'COUNT(*)', 0);

        $settings['features'] = $features;

        $settings['filter'] = [
            'price' => [
                'id'        => 'price',
                'name'      => 'Price',
                'type'      => '',
                'code'      => '',
                'type_name' => '',
            ]
        ];

        $settings['filter'] += $features;

        /**
         * @event backend_category_dialog
         * @param array $category
         * @return array[string][string] $return[%plugin_id%] html output for dialog
         */
        $this->view->assign('event_dialog', wa()->event('backend_category_dialog', $settings));

        $this->view->assign(array(
            'parent'            => $parent,
            'cloud'             => $tag_model->getCloud(),
            'currency'          => wa()->getConfig()->getCurrency(),
            'frontend_base_url' => $frontend_base_url,
            'lang'              => substr(wa()->getLocale(), 0, 2),
            'features'          => $features,
            'routes'            => wa()->getRouting()->getByApp('shop'),
            'settings'          => $settings,
            'type'              => 'category'
        ));
    }

    public function getParentCategory()
    {
        $parent_id = (int)waRequest::get('parent_id');
        if (!$parent_id) {
            return array();
        }
        $category_model = new shopCategoryModel();
        $parent = $category_model->getById($parent_id);
        $category_routes_model = new shopCategoryRoutesModel();
        $parent['routes'] = $category_routes_model->getRoutes($parent_id);
        return $parent;
    }

    protected function getSettings()
    {
        $category_model = new shopCategoryModel();
        $category_routes_model = new shopCategoryRoutesModel();

        $settings = $category_model->getEmptyRow();

        $other_keys = [
            'has_children'           => false,
            'status'                 => null,
            'frontend_urls'          => array(),
            'allow_filter'           => false,
            'enable_sorting'         => 0,
            'include_sub_categories' => 0,
            'custom_conditions'      => '',
            'params'                 => [],
            'routes'                 => $category_routes_model->getEmptyRow(),
            'cloud'                  => $this->getTagsCloud(),
        ];

        return array_merge($settings, $other_keys);
    }

    protected function getTagsCloud()
    {
        $tag_model = new shopTagModel();
        $cloud = $tag_model->getCloud('name');

        return $cloud;
    }

}