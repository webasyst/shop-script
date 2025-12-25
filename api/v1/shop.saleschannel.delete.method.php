<?php

class shopSaleschannelDeleteMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $id = $this->post('id', true);

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        $sales_channel_model->deleteById($id);
        $sales_channel_params_model->clear($id);

        $this->response = ['status' => 'ok'];
    }
}
