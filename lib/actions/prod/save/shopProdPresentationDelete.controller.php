<?php
/**
 * Delete presentation
 */
class shopProdPresentationDeleteController extends waJsonController
{
    public function execute()
    {
        $presentation_template_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        $presentation_model = new shopPresentationModel();
        $presentation_id = $presentation_model->select('id')->where('id = ?', (int)$presentation_template_id)->fetchField('id');
        if ($presentation_id) {
            $presentation_model->deleteById($presentation_template_id);
            $default_presentation = $presentation_model->getDefaultTemplateByUser(wa()->getUser()->getId());
            $presentation_model->updateByField('parent_id', $presentation_template_id, ['parent_id' => $default_presentation['id']]);

            $columns_model = new shopPresentationColumnsModel();
            $columns_model->deleteByField('presentation_id', $presentation_template_id);

            $presentation_model->correctSort();
        }
    }
}
