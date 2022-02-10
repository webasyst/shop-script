<?php
/**
 * Dialog for edit the measurement unit
 */
class shopSettingsUnitDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $unit_id = waRequest::request('id', '', waRequest::TYPE_STRING);

        $unit_model = new shopUnitModel();
        $unit = $unit_model->getById($unit_id);
        if (!$unit) {
            throw new waException('Unit not found', 404);
        }

        $this->view->assign([
            'unit' => $unit,
        ]);
    }
}
