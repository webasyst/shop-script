<?php

class shopSaleschannelGetInfoMethod extends shopApiMethod
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $id = $this->get('id', true);

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();
        $channel = $sales_channel_model->getById($id);
        if (!$channel) {
            throw new waAPIException('channel_not_found', _w('Sales channel not found.'), 404);
        }
        $channel['params'] = $sales_channel_params_model->get($id);
        $channel = shopSaleschannelGetInfoMethod::formatSalesChannel($channel);
        $this->response = $channel;
    }

    public static function formatSalesChannel($channel)
    {
        $schema = [
            'id' => 'integer',
            'type' => 'string',
            'name' => 'string',
            'description' => 'string',
            'wa_channel_id' => 'string',
            'status' => 'integer',
            'sort' => 'integer',
            'params' => 'object',
        ];
        $channel = shopFrontApiFormatter::formatFieldsToType($channel, $schema);
        unset($channel['params']['bot_token']);
        return array_intersect_key($channel, $schema);
    }
}
