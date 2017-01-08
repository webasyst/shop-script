<?php


class shopSmartfiltersPlugin extends shopPlugin
{
    public function frontendCategory($category)
    {
        if(!$this->getSettings('enabled'))
            return '';

        $category_id = $category['id'];
        if ($category_id) {
            $feature_model = new shopSmartfiltersPluginFeatureModel();
            $filters = $feature_model->getByCategoryId($category_id);

            $list = new shopSmartfiltersPluginShowAction();
            $list->setFilters($filters);
            return $list->display(false);
        }
    }
}

