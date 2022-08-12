<?php
/**
 * Edit existing filter and return full data of new filter
 */
class shopProdFilterRewriteController extends waJsonController
{
    public function execute()
    {
        $active_id = waRequest::post('active_filter_id', null, waRequest::TYPE_INT);
        $update_id = waRequest::post('update_filter_id', null, waRequest::TYPE_INT);

        $filter_model = new shopFilterModel();
        $update_id = $filter_model->rewrite($active_id, $update_id);

        $this->response = $filter_model->getById($update_id, [
            'rules' => true,
        ]);
    }
}
