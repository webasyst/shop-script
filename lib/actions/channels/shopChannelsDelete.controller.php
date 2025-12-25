<?php
/**
 * Delete sales channel
 */
class shopChannelsDeleteController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        }

        $id = waRequest::request('id', null, 'int');
        if (!$id) {
            throw new waException('id is required');
        }

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        $sales_channel_model->deleteById($id);
        $sales_channel_params_model->clear($id);

        $this->response = 'ok';
    }
}
