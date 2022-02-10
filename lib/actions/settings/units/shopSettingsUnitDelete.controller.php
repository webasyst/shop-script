<?php
/**
 * Delete given unit
 */
class shopSettingsUnitDeleteController extends waJsonController
{
    public function execute()
    {
        $unit_id = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$unit_id) {
            return;
        }

        $unit_model = new shopUnitModel();
        $units_used = $unit_model->getUsedUnit();
        if (in_array((string) $unit_id, $units_used)) {
            $this->errors[] = [
                'id'   => 'unit_delete_error',
                'text' => _w('The unit cannot be deleted because it is in use.')
            ];
        } else {
            $unit_model->deleteById($unit_id);
        }
    }
}
