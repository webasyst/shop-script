<?php

class shopSaleschannelListMethod extends shopApiMethod
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $filter_type = waRequest::request('type', '', 'string');

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();
        $channels = $sales_channel_model->getAll();
        $sales_channel_params_model->load($channels);
        foreach ($channels as $k => &$channel) {
            if (strlen($filter_type) > 0 && $channel['type'] != $filter_type) {
                unset($channels[$k]);
                continue;
            }
            $channel = shopSaleschannelGetInfoMethod::formatSalesChannel($channel);
        }
        unset($channel);
        $this->response = array_values($channels);
    }
}
