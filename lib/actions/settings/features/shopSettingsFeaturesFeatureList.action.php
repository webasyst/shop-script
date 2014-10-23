<?php
class shopSettingsFeaturesFeatureListAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $feature_model = new shopFeatureModel();

        $values_per_feature = 7;
        $type = waRequest::get('type', waRequest::TYPE_STRING, '');
        if ($type_id = intval($type)) {
            $features = $feature_model->getByType($type_id, 'id', $values_per_feature);
        } else {

            if ($type === 'empty') {
                $features = $feature_model->getByType(null, 'id', $values_per_feature);
            } elseif ($type === '') {
                $features = $feature_model->getFeatures(true, null, 'id', $values_per_feature);
            } else {
                $features = $feature_model->getByType(0, 'id', $values_per_feature);
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
