<?php
/**
 * Edit existing presentation and return full data of new presentation
 */
class shopProdPresentationRewriteController extends waJsonController
{
    public function execute()
    {
        $active_id = waRequest::post('active_presentation_id', null, waRequest::TYPE_INT);
        $update_id = waRequest::post('update_presentation_id', null, waRequest::TYPE_INT);

        $presentation_model = new shopPresentationModel();
        $update_id = $presentation_model->rewrite($active_id, $update_id);

        $this->response = $presentation_model->getById($update_id, [
            'columns' => true,
        ]);
    }
}
