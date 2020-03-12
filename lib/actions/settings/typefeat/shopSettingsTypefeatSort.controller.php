<?php
/**
 * Reorder features that belong to a single type.
 * Used during drag-and-drop sorting of features.
 */
class shopSettingsTypefeatSortController extends waJsonController
{
    public function execute()
    {
        $model = new shopTypeFeaturesModel();
        $type_id = waRequest::post('type_id', 0, waRequest::TYPE_INT);
        $feature_ids = waRequest::post('ids', 0, waRequest::TYPE_ARRAY_INT);

        //$model->move($item, $after, $type);
    }
}
