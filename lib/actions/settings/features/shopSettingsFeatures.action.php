<?php
class shopSettingsFeaturesAction extends waViewAction
{
    public function execute()
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getAll($type_model->getTableId(), true);

        $feature_model = new shopFeatureModel();
        $features = $feature_model->getFeatures(true, null, 'id', true);

        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->fillTypes($features, $types);
        shopFeatureModel::appendTypeNames($features);

        $this->view->assign('product_types', $types);
        $this->view->assign('features', $features);

        $this->view->assign('icons', (array) $this->getConfig()->getOption('type_icons'));
    }
}
