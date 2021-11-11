<?php
/**
 * List of features that belong to a certain product type.
 * Also serves list of features not belonging to any type, all types and all existing features.
 */
class shopSettingsTypefeatListAction extends waViewAction
{
    public function execute()
    {
        $sidebar_action = new shopSettingsTypefeatSidebarAction();
        $sidebar_html = $sidebar_action->display();

        $product_code_model = new shopProductCodeModel();
        $feature_model = new shopFeatureModel();
        $type_model = new shopTypeModel();

        $values_per_feature = 1;
        $type = waRequest::request('type', '', waRequest::TYPE_STRING);
        $type_id = intval($type);
        if ($type_id) {
            $type_info = $type_model->getById($type_id);
            if (!$type_info) {
                throw new waException('Not found', 404);
            }

            // Features assigned to single product type
            $features = $feature_model->getByType($type_id, 'id', $values_per_feature);
            $codes = $product_code_model->getByType($type_id);
            $title = $type_info['name'];
        } elseif ($type === 'empty') {
            // Features not assigned to any type
            $features = $feature_model->getByType(null, 'id', $values_per_feature);
            $features = $this->sortByName($features);
            $codes = $product_code_model->getByType(null);
            $title = _w('Not available for any product type');
        } elseif ($type === 'builtin') {
            // System (undeletable) features
            $features = $feature_model->getBuiltinFeatures();
            $features = $this->sortByName($features);
            $codes = null;
            $title = _w('System features');
        } elseif ($type === '') {
            // All features
            $count_all_features = $feature_model->countAll();
            if ($count_all_features < wa('shop')->getConfig()->getOption('features_per_page') || waRequest::cookie('force_all_features')) {
                $features = $feature_model->getFeatures(true, null, 'id', $values_per_feature);
                $features = $this->sortByName($features);
            } else {
                // When there are too many features, show text message instead
                $too_many_features = true;
                $features = [];
            }

            $codes = $product_code_model->getAll('id');
            $title = _w('All features');
        } else {
            // Features assigned to all existing types
            $features = $feature_model->getByType(0, 'id', $values_per_feature);
            $features = $this->sortByName($features);
            $codes = $product_code_model->getByType(0);
            $title = _w('Available for all product types');
            $type = 'all_existing';
        }
        if ($features) {
            shopFeatureModel::appendTypeNames($features);
            $type_features_model = new shopTypeFeaturesModel();
            $type_features_model->fillTypes($features);

            foreach ($features as &$f) {
                $f['visible_in_frontend'] = $f['status'] == 'public';
                $f['available_for_sku'] = !empty($f['available_for_sku']);
            }
            unset($f);
        }

        if ($codes) {
            $all_enabled_plugins = wa('shop')->getConfig()->getPlugins();
            foreach ($codes as $id => $code) {
                $code_plugin_enabled = !empty($code['plugin_id']) ? isset($all_enabled_plugins[$code['plugin_id']]) : false;
                $codes[$id]['code_plugin_enabled'] = $code_plugin_enabled;
                $codes[$id]['protected_code'] = $code['protected'] && $code_plugin_enabled;
            }
        }

        $this->view->assign([
            'title' => $title,
            'type_url_id' => $type,
            'is_filter_page' => !$type_id,
            'sidebar_html' => $sidebar_html,
            'too_many_features' => ifempty($too_many_features),
            'features' => $features,
            'codes' => $codes,
            'can_be_delete' => count($sidebar_action->types) !== 2,
        ]);
    }

    protected function sortByName($features)
    {
        uasort($features, function($f1, $f2) {
            return strcmp(mb_strtolower($f1['name']), mb_strtolower($f2['name']));
        });
        return $features;
    }
}
