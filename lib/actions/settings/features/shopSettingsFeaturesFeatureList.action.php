<?php
class shopSettingsFeaturesFeatureListAction extends waViewAction
{
    public function execute()
    {
        $values_per_feature = 7;
        $feature_model = new shopFeatureModel();

        $type = waRequest::get('type', waRequest::TYPE_INT);
        if ($type) {
            $features = $feature_model->getByType($type, 'id', $values_per_feature);
        } else {
            $type_model = new shopTypeModel();
            $types_per_page = $this->getConfig()->getOption('types_per_page');
            $show_all_types = (($type_model->countAll() - $types_per_page) < 3);
            if ($show_all_types) {
                $features = $feature_model->getFeatures(true, null, 'id', $values_per_feature);
            } else {
                $features = $feature_model->getByType($type, 'id', $values_per_feature);
            }
        }
        if ($features) {
            shopFeatureModel::appendTypeNames($features);
            $type_features_model = new shopTypeFeaturesModel();
            $type_features_model->fillTypes($features);
        }
        $this->view->assign('features', $features);
        $this->view->assign('values_per_feature', $values_per_feature);

    }
}

