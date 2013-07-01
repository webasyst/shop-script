<?php
class shopSettingsFeaturesFeatureListAction extends waViewAction
{
    public function execute()
    {
        $values_per_feature = 7;
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByType($type = waRequest::get('type', waRequest::TYPE_INT), 'id', $values_per_feature);
        if ($features) {
            shopFeatureModel::appendTypeNames($features);
            foreach ($features as &$feature) {
                $feature['types'] = array($type => true);
            }
            unset($feature);
        }
        $this->view->assign('features', $features);
        $this->view->assign('values_per_feature', $values_per_feature);

    }
}

