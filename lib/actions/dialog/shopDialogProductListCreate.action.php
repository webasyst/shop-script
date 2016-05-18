<?php

class shopDialogProductListCreateAction extends waViewAction
{
    private $set_dynamic_default_count = 8;

    public function execute()
    {
        $type = waRequest::get('type', '', waRequest::TYPE_STRING_TRIM);
        $this->template = 'DialogProduct'.ucfirst($type).'Create';
        $this->view->assign('type', $type);
        if ($type == 'category') {
            $this->categoryExecute();
        } else if ($type == 'set') {
            $this->setExecute();
        }
    }

    public function categoryExecute()
    {
        $tag_model = new shopTagModel();

        $stuff = '%category_url%';
        $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $stuff), true);
        $pos = strrpos($frontend_url, $stuff);
        $frontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;

        $feature_model = new shopFeatureModel();
        $features = $feature_model->getFeatures('selectable', 1);
        $features += $feature_model->getFeatures('type', 'boolean');
        $features = $feature_model->getValues($features);

        $parent = $this->getParentCategory();
        $settings = array(
            'routes' => array(),
            'has_children' => false
        );
        if ($parent) {
            $settings['routes'] = $parent['routes'];
        }


        $this->view->assign(array(
            'parent' => $parent,
            'cloud'    => $tag_model->getCloud(),
            'currency' => wa()->getConfig()->getCurrency(),
            'frontend_base_url' => $frontend_base_url,
            'lang' => substr(wa()->getLocale(), 0, 2),
            'features' => $features,
            'routes' => wa()->getRouting()->getByApp('shop'),
            'settings' => $settings
        ));
    }

    public function getParentCategory()
    {
        $parent_id = (int) waRequest::get('parent_id');
        if (!$parent_id) {
            return array();
        }
        $category_model = new shopCategoryModel();
        $parent = $category_model->getById($parent_id);
        $category_routes_model = new shopCategoryRoutesModel();
        $parent['routes'] = $category_routes_model->getRoutes($parent_id);
        return $parent;
    }

    public function setExecute()
    {
        $this->view->assign('default_count', $this->set_dynamic_default_count);
    }
}