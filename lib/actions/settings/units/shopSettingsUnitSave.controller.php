<?php
/**
 * Accept POST from unit editor dialog to save new or existing unit.
 */
class shopSettingsUnitSaveController extends waJsonController
{
    public function execute()
    {
        $unit_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $unit_data = waRequest::post('unit', [], 'array');

        foreach (['short_name', 'name', 'okei_code'] as $field) {
            $unit_value = trim(ifset($unit_data, $field, ''));
            if (!strlen($unit_value)) {
                $this->errors[] = [
                    'name' => "unit[$field]",
                    'text' => _w('This field is required.'),
                ];
            }
        }

        $unit_model = new shopUnitModel();
        if ($unit_id) {
            $unit_model->updateById($unit_id, $unit_data);
        } else {
            $unit_id = $unit_model->insert($unit_data);
        }

        $this->response = $unit_model->getById($unit_id);
    }
}
