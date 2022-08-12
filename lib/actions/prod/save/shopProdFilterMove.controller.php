<?php
/**
 * Move filter
 */
class shopProdFilterMoveController extends waJsonController
{
    public function execute()
    {
        $filter_ids = waRequest::post('filters', [], waRequest::TYPE_ARRAY);

        if (!empty($filter_ids)) {
            $filter_model = new shopFilterModel();
            $filter_ids = array_map('intval', $filter_ids);
            $sort = 0;
            foreach ($filter_ids as $id) {
                $filter_model->updateByField('id', $id, ['sort' => $sort]);
                $filter_model->updateByField('parent_id', $id, ['sort' => $sort]);
                $sort++;
            }
        }
    }
}
