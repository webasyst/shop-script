<?php

class shopSaleschannelAddMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $data = waRequest::post();
        $params = ifset($data, 'params', []);
        unset($data['params']);

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        $data = shopSaleschannelUpdateMethod::sanitizeData(null, $data);
        shopSaleschannelUpdateMethod::validateData(null, $data);

        try {
            $channel_type = shopSalesChannelType::factory($data['type']);
        } catch (waException $ex) {
            if (SystemConfig::isDebug()) {
                throw new waAPIException('unknown_type', $ex->getMessage(), 400, [
                    'trace' => $ex instanceof waException ? $ex->getFullTraceAsString() : $ex->getTraceAsString(),
                ]);
            } else {
                throw new waAPIException('unknown_type', _w('Sales channel type not found.'), 400);
            }
        }

        if (!shopLicensing::isPremium()) {
            $by_type = $sales_channel_model->getByField('type', $data['type'], true);
            if (count($by_type) > 0) {
                throw new waAPIException('premium_only', _w('Creating more channels is available in the premium version.'), 402);
            }
        }

        shopSaleschannelUpdateMethod::sanitizeAndValidateParams($channel_type, null, $params, 'set');

        $data['sort'] = $sales_channel_model->countAll();
        $id = $sales_channel_model->insert($data);
        $sales_channel_params_model->set($id, $params);

        $channel = $sales_channel_model->getById($id);
        $channel['params'] = $params;
        try {
            shopChannelsSaveController::saveWaidChannel($channel_type, $channel);
        } catch (Throwable $e) {
        }
        try {
            $channel_type->onSave($channel);
        } catch (Throwable $e) {
        }
        if (!empty($channel['wa_channel_id'])) {
            $sales_channel_model->updateById($id, ['wa_channel_id' => $channel['wa_channel_id']]);
        }
        $channel = shopSaleschannelGetInfoMethod::formatSalesChannel($channel);
        $this->response = [
            'channel' => $channel,
        ];
    }
}
