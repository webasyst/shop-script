<?php

class shopDialogProductListCreateAction extends waViewAction
{
    private $set_dynamic_default_count = 8;

    public function execute()
    {
        $type = waRequest::get('type', '', waRequest::TYPE_STRING_TRIM);

        $parent_id = waRequest::get('parent_id', 0, waRequest::TYPE_INT);
        if ($parent_id) {
            $category_model = new shopCategoryModel();
            $parent = $category_model->getById($parent_id);
        }

        $this->template = 'DialogProduct'.ucfirst($type).'Create';

        $this->view->assign(array(
            'type' => $type,
            'parent' => $parent_id ? $parent : array()
        ));

        if ($type == 'category') {
            $tag_model = new shopTagModel();

            $stuff = '%category_url%';
            $frontend_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $stuff), true);
            $pos = strrpos($frontend_url, $stuff);
            $fontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;
            
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getFeatures('selectable', 1);
            $features += $feature_model->getFeatures('type', 'boolean');
            $features = $feature_model->getValues($features);
            
            $this->view->assign(array(
                'cloud'    => $tag_model->getCloud(),
                'currency' => wa()->getConfig()->getCurrency(),
                'frontend_base_url' => $fontend_base_url,
                'lang' => substr(wa()->getLocale(), 0, 2),
                'features' => $features
            ));
        } else if ($type == 'set') {
            $this->view->assign('default_count', $this->set_dynamic_default_count);
        }
    }
}