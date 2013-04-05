<?php
class shopSettingsFeaturesFeatureDeleteController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::post('feature_id');

        if ($feature_id) {
            $model = new shopFeatureModel();
            $model->deleteById($feature_id);

            // realated model
            foreach (array(
                new shopProductFeaturesSelectableModel(),
                new shopTypeFeaturesModel(),
                new shopFeatureValuesDimensionModel(),
                new shopFeatureValuesDoubleModel(),
                new shopFeatureValuesTextModel(),
                new shopFeatureValuesVarcharModel(),
                new shopProductFeaturesModel(),
                new shopTypeUpsellingModel()
            ) as $m)
            {
                $m->deleteByField('feature_id', $feature_id);
            }
        }
    }
}
