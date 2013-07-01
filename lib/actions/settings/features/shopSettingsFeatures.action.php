<?php
class shopSettingsFeaturesAction extends waViewAction
{
    public function execute()
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getAll($type_model->getTableId(), true);
        $type_features_model = new shopTypeFeaturesModel();
        $type_features_model->countFeatures($types);

        if (false) {
            $feature_model = new shopFeatureModel();

            $features = $feature_model->getFeatures(true, null, 'id', false);

            if ($features) {

                $type_features_model->fillTypes($features, $types);
                shopFeatureModel::appendTypeNames($features);
            }
        } else {
            $features = array();
        }

        $this->view->assign('product_types', $types);
        $this->view->assign('features', $features);
        $this->view->assign('icons', (array)$this->getConfig()->getOption('type_icons'));
    }
}
