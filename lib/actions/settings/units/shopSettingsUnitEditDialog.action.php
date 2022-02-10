<?php
/**
 * Dialog for edit the measurement unit
 */
class shopSettingsUnitEditDialogAction extends waViewAction
{
    public function execute()
    {
        $unit_id = waRequest::request('id', null, waRequest::TYPE_INT);

        $unit_model = new shopUnitModel();
        if (!$unit_id) {
            $unit = $unit_model->getEmptyRow();
        } else {
            $unit = $unit_model->getById($unit_id);
            if (!$unit) {
                throw new waException('Not found', 404);
            }
        }

        $this->view->assign([
            'unit' => $unit,
        ]);
    }
}
