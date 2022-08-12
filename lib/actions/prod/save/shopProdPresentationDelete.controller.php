<?php
/**
 * Delete presentation
 */
class shopProdPresentationDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        $presentation_model = new shopPresentationModel();
        $presentation_ids = $presentation_model->select('id')->where('id = i:num OR parent_id = i:num', ['num' => $id])->fetchAll('id');
        $ids = array_keys($presentation_ids);
        $presentation_model->deleteById($ids);

        $columns_model = new shopPresentationColumnsModel();
        $columns_model->deleteByField('presentation_id', $ids);

        $presentation_model->correctSort();
    }
}
