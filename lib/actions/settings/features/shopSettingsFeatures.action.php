<?php
class shopSettingsFeaturesAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $types_per_page = $this->getConfig()->getOption('types_per_page');
        $values_per_feature = 7;

        $type_model = new shopTypeModel();
        $type_features_model = new shopTypeFeaturesModel();
        $feature_model = new shopFeatureModel();

        $types = $type_model->getAll($type_model->getTableId(), true);
        $type_features_model->countFeatures($types);

        $show_all_features = $feature_model->countAll() < $this->getConfig()->getOption('features_per_page');

        if ($show_all_features) {
            $feature_model = new shopFeatureModel();
            if ($features = $feature_model->getFeatures(true, null, 'id', $values_per_feature)) {
                $show_all_features = count($features);
                $type_features_model->fillTypes($features, $types);
                shopFeatureModel::appendTypeNames($features);
            }
        } else {
            $features = array();
        }

        $this->view->assign('type_templates', shopTypeModel::getTemplates());

        $this->view->assign('show_all_features', $show_all_features);
        $this->view->assign('show_all_types', (count($types) - $types_per_page) < 3);
        $this->view->assign('types_per_page', $types_per_page);
        $this->view->assign('values_per_feature', $values_per_feature);

        $this->view->assign('icons', (array)$this->getConfig()->getOption('type_icons'));

        $this->view->assign('product_types', $types);
        $this->view->assign('features', $features);
    }
}
