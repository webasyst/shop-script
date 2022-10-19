<?php
/**
 * Delete filter rule
 */
class shopProdFilterDeleteRuleController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('filter_id', null, waRequest::TYPE_INT);
        $rule_group = waRequest::post('rule_group', null, waRequest::TYPE_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        $new_presentation_id = shopProdPresentationEditColumnsController::duplicatePresentation($presentation_id);
        if ($new_presentation_id) {
            $this->response['new_presentation_id'] = $new_presentation_id;
            $presentation_model = new shopPresentationModel();
            $id = $presentation_model->select('filter_id')->where('`id` = ?', $new_presentation_id)->fetchField('filter_id');
        }

        $rules_model = new shopFilterRulesModel();
        $rules_model->deleteByField([
            'filter_id' => $id,
            'rule_group' => $rule_group
        ]);

        $rules_model->correctSortAfterDelete($id, $rule_group);
    }
}
