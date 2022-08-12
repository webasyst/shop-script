<?php
/**
 * Move presentation
 */
class shopProdPresentationMoveController extends waJsonController
{
    public function execute()
    {
        $presentation_ids = waRequest::post('presentations', [], waRequest::TYPE_ARRAY);

        if (!empty($presentation_ids)) {
            $presentation_model = new shopPresentationModel();
            $presentation_ids = array_map('intval', $presentation_ids);
            $sort = 0;
            foreach ($presentation_ids as $id) {
                $presentation_model->updateByField('id', $id, ['sort' => $sort]);
                $presentation_model->updateByField('parent_id', $id, ['sort' => $sort]);
                $sort++;
            }
        }
    }
}
