<?php

class shopSettingsUnitChangeStatusController extends waJsonController
{
    public function execute()
    {
        $unit_id = waRequest::post('unit_id', null, waRequest::TYPE_INT);
        if (!$unit_id) {
            throw new waException("Unknown unit id");
        }

        $status = waRequest::post('status', null, waRequest::TYPE_INT);
        if ($status == shopUnitModel::STATUS_DISABLED || $status == shopUnitModel::STATUS_ENABLED) {
            $product_units_model = new shopUnitModel();
            $units_used = $product_units_model->getUsedUnit();
            if ($status == shopUnitModel::STATUS_DISABLED && in_array((string) $unit_id, $units_used)) {
                $this->errors[] = [
                    'id'   => 'unit_status_error',
                    'text' => _w('The unit cannot be disabled because it is in use.')
                ];
            } else {
                $result = $product_units_model->changeStatus($unit_id, $status);
                if ($result) {
                    $this->response = $result;
                } else {
                    $this->errors[] = [
                        'id' => 'unit_status_error',
                        'text' => _w('Saving has failed.')
                    ];
                }
            }
        } else {
            $this->errors[] = [
                'id' => 'unit_status_error',
                'text' => _w('Invalid status.')
            ];
        }
    }
}
