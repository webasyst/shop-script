<?php
/**
 * Dialog to add existing features to product type.
 */
class shopSettingsTypefeatFeatureAddExistingAction extends waViewAction
{
    public function execute()
    {
        $type_id = waRequest::request('type_id', '', waRequest::TYPE_STRING);
        if ($type_id) {
            $type_model = new shopTypeModel();
            $type = $type_model->getById($type_id);
        }
        if (empty($type)) {
            throw new waException('Not found', 404);
        }

        $type_features_model = new shopTypeFeaturesModel();
        $type_features = $type_features_model->getByField('type_id', $type_id, 'feature_id');

        $feature_model = new shopFeatureModel();
        $rows = $feature_model->query('SELECT id, code, name FROM shop_feature WHERE parent_id IS NULL ORDER BY name');

        $features = [];
        foreach($rows as $feature) {
            if (isset($type_features[$feature['id']])) {
                $feature['enabled'] = true;
            }
            $features[] = $feature;
        }
        $rows->free();
        unset($rows, $type_features);

        $this->view->assign([
            'features' => $features,
            'type' => $type,
        ]);
    }
}
