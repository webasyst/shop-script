<?php

class shopSettingsOrderAssignmentsDeleteController extends waJsonController
{
    public function execute()
    {
        $rule_id = waRequest::post('rule_id', null, waRequest::TYPE_INT);

        if ($rule_id > 0) {
            $oar_model = new shopOrderAssignRulesModel();
            $oar_model->deleteById($rule_id);
        }

        $this->response = 'ok';
    }
}
