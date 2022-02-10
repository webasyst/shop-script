<?php

class shopSettingsUnitSortController extends waJsonController
{
    public function execute()
    {
        $unit_ids = waRequest::post('ids', 0, waRequest::TYPE_ARRAY_INT);

        $type_units_model = new shopUnitModel();
        if (is_array($unit_ids)) {
            $type_units_model->setSortOrder($unit_ids);
        } else {
            $this->errors[] = [
                'id' => 'unit_sort_error',
                'text' => _w('No quantity units available for sorting.')
            ];
        }
    }
}
