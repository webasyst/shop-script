<?php

class shopSaleschannelUpdateMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $id = $this->post('id', true);
        $data = waRequest::post();
        $params = ifset($data, 'params', []);
        unset($data['params'], $data['id']);

        $params_mode = waRequest::request('params_mode', 'set', 'string') === 'set' ? 'set' : 'update';

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        try {
            $channel = $sales_channel_model->getById($id);
            if (!$channel) {
                throw new waAPIException('channel_not_found', _w('Sales channel not found.'), 404);
            }
            $channel_type = shopSalesChannelType::factory($channel['type']);
        } catch (waException $ex) {
            if (SystemConfig::isDebug()) {
                throw new waAPIException('unknown_type', $ex->getMessage(), 400, [
                    'trace' => $ex instanceof waException ? $ex->getFullTraceAsString() : $ex->getTraceAsString(),
                ]);
            } else {
                throw new waAPIException('unknown_type', _w('Sales channel type not found.'), 400);
            }
        }

        $data = shopSaleschannelUpdateMethod::sanitizeData($id, $data);
        shopSaleschannelUpdateMethod::validateData($id, $data);
        shopSaleschannelUpdateMethod::sanitizeAndValidateParams($channel_type, $id, $params, $params_mode);
        if ($data) {
            $sales_channel_model->updateById($id, $data);
        }
        if ($params_mode == 'set') {
            $sales_channel_params_model->set($id, $params);
        } else { // 'update'
            $sales_channel_params_model->update($id, $params);
        }

        $channel = $sales_channel_model->getById($id);
        $channel['params'] = $sales_channel_params_model->get($id);
        try {
            shopChannelsSaveController::saveWaidChannel($channel_type, $channel);
        } catch (Throwable $e) {
        }
        try {
            $channel_type->onSave($channel);
        } catch (Throwable $e) {
        }
        $channel = shopSaleschannelGetInfoMethod::formatSalesChannel($channel);
        $this->response = [
            'channel' => $channel,
        ];
    }

    public static function sanitizeData($id, $data)
    {
        if (!is_array($data)) {
            return [];
        }

        $data = array_intersect_key($data, [
            'type' => 1,
            'name' => 1,
            'description' => 1,
            'status' => 1,
            'sort' => 1,
        ]);
        if ($id) {
            unset($data['type']);
        }
        foreach ($data as $k => &$v) {
            switch ($k) {
                case 'status':
                    $v = $v ? 1 : 0;
                    break;
                case 'sort':
                    $v = (int) $v;
                    break;
                default:
                    $v = (string) $v;
            }
        }
        unset($v);

        return $data;
    }

    public static function validateData($id, $data)
    {
        if (!$id && empty($data['type'])) {
            throw new waAPIException('field_required', _w('Sales channel type is required.'), 400, [
                'field' => 'type',
            ]);
        }

        foreach (['name'] as $k) {
            if (array_key_exists($k, $data) && !strlen($data[$k])) {
                throw new waAPIException('field_required', _w('This field is required'), 400, [
                    'field' => $k,
                ]);
            }
        }

        if (!$id) {
            $channel = (new shopSalesChannelModel())->getByField([
                'name' => $data['name'],
                'type' => $data['type'],
            ]);
            if ($channel) {
                throw new waAPIException('type_name_unique', _w('A channel of this type has this name already.'), 400, [
                    'id' => $channel['id'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                ]);
            }
        }
    }

    public static function sanitizeAndValidateParams(shopSalesChannelType $channel_type, $id, &$params, $params_mode)
    {
        if (!is_array($params)) {
            $params = [];
        }

        $errors = $channel_type->sanitizeAndValidateParams($id, $params, $params_mode);
        if ($errors) {
            foreach ($errors as $e) {
                if (!is_array($e)) {
                    $e = [
                        'error_description' => $e,
                    ];
                }
                $field = ifset($e, 'field', null);
                if ($field && substr($field, 0, 12) == 'data[params]') {
                    $field = 'params'.substr($field, 12);
                }
                throw new waAPIException('bad_params', ifset($e, 'error_description', _w('Failed to validate type parameters.')), 400, [
                    'field' => $field,
                ]);
            }
        }
    }
}
