<?php
class shopSettingsFeaturesAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $types_per_page = (int) $this->getConfig()->getOption('types_per_page');
        $values_per_feature = (int) $this->getConfig()->getOption('features_values_per_page');

        $type_model = new shopTypeModel();
        $type_features_model = new shopTypeFeaturesModel();
        $feature_model = new shopFeatureModel();

        $show_all_features = $feature_model->countAll() < $this->getConfig()->getOption('features_per_page');

        $types = array(
            0 => array('id' => 0, 'name' => _w('All product types'), 'icon' => ''),
        );
        $types += $type_model->getAll($type_model->getTableId(), true);
        $type_features_model->countFeatures($types);

        if ($show_all_features) {
            if ($features = $feature_model->getFeatures(true, null, 'id', $values_per_feature)) {
                $show_all_features = count($features);
                $type_features_model->fillTypes($features);
                shopFeatureModel::appendTypeNames($features, true);
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
