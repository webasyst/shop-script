<?php
/**
 * Internal sidebar for types and features settings section.
 * Used in shopSettingsTypefeatListAction, as well as separately loaded via XHR.
 */
class shopSettingsTypefeatSidebarAction extends waViewAction
{
    public $types;

    public function execute()
    {
        $feature_model = new shopFeatureModel();
        $type_features_model = new shopTypeFeaturesModel();
        $count_all_features = $feature_model->countAll();

        $this->types = $this->getTypes();

        $count_features_all_types = ifset($this->types, 0, 'features_count', 0);

        if ($count_all_features < wa('shop')->getConfig()->getOption('features_per_page')) {
            // We only count unassigned when there are few features overall
            // because it's an expensive query
            $count_features_no_types = $type_features_model->countUnassignedFeatures();
        } else {
            $count_features_no_types = '';
        }

        $count_features_builtin = $feature_model->countBuiltinFeatures();

        $this->view->assign([
            'count_all_features' => $count_all_features,
            'count_features_all_types' => $count_features_all_types,
            'count_features_no_types' => $count_features_no_types,
            'count_features_builtin' => $count_features_builtin,
            'types' => $this->types,
        ]);
    }

    protected function getTypes()
    {
        $type_model = new shopTypeModel();
        $type_features_model = new shopTypeFeaturesModel();
        $types = array(
            0 => array('id' => 0, 'name' => _w('All product types'), 'icon' => ''),
        );
        $types += $type_model->getAll($type_model->getTableId());
        $type_features_model->countFeatures($types);
        return $types;
    }
}
