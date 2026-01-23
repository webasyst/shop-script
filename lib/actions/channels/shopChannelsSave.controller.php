<?php
/**
 * Save sales channel settings for new or existing channel
 */
class shopChannelsSaveController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        }

        $id = waRequest::request('id', null, 'int');
        if (!$id || $id < 0) {
            if (!waRequest::request('is_new')) {
                $this->errors[] = [
                    'error_description' => 'ID is required', // should never be visible to user
                ];
                return;
            }
            $id = null;
        }

        $params_mode = 'set';
        if ($id) {
            $params_mode = waRequest::request('params_mode', 'set', 'string') === 'set' ? 'set' : 'update';
        }

        $data = waRequest::post('data', [], 'array');
        $params = ifset($data, 'params', []);
        unset($data['params']);

        $data = $this->sanitizeData($id, $data);
        $this->validateData($id, $data);

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        try {
            if ($id) {
                $channel = $sales_channel_model->getById($id);
                if (!$channel) {
                    $this->errors[] = [
                        'error_description' => _w('Sales channel not found.'),
                    ];
                    return;
                }
                $channel_type = shopSalesChannelType::factory($channel['type']);
            } else {
                $channel_type = shopSalesChannelType::factory($data['type']);
            }
        } catch (waException $ex) {
            $err = [
                'error_description' => _w('Sales channel type not found.'),
            ];
            if (SystemConfig::isDebug()) {
                $err['error_description'] = $ex->getMessage();
                $err['trace'] = $ex instanceof waException ? $ex->getFullTraceAsString() : $ex->getTraceAsString();
            }
            $this->errors[] = $err;
            return;
        }

        $this->sanitizeAndValidateParams($channel_type, $id, $params, $params_mode);
        if ($this->errors) {
            return;
        }

        if ($data) {
            if ($id) {
                $sales_channel_model->updateById($id, $data);
            } else {
                if (!shopLicensing::isPremium()) {
                    $by_type = $sales_channel_model->getByField('type', $data['type'], true);
                    if (count($by_type) > 0) {
                        $this->errors[] = [
                            'error_description' => _w('Creating more channels is available in the premium version.'),
                        ];
                        return;
                    }
                }
                $data['sort'] = $sales_channel_model->countAll();
                $id = $sales_channel_model->insert($data);
            }
        }

        if ($params_mode == 'set') {
            $sales_channel_params_model->set($id, $params);
        } else { // 'update'
            $sales_channel_params_model->update($id, $params);
        }

        $this->response = $sales_channel_model->getById($id);
        $this->response['params'] = $sales_channel_params_model->get($id);

        try {
            $should_update = empty($this->response['wa_channel_id']);
            self::saveWaidChannel($channel_type, $this->response);
            if ($should_update && !empty($this->response['wa_channel_id'])) {
                $sales_channel_model->updateById($id, [
                    'wa_channel_id' => $this->response['wa_channel_id'],
                ]);
            }
        } catch (Throwable $e) {
            $this->response['error_description'] = $e->getMessage();
            if (SystemConfig::isDebug()) {
                $this->response['error_stack_trace'] = (string) $e;
            }
        }

        try {
            $channel_type->onSave($this->response);
        } catch (Throwable $e) {
            $this->response['error_description'] = $e->getMessage();
            if (SystemConfig::isDebug()) {
                $this->response['error_stack_trace'] = (string) $e;
            }
        }
    }

    public function sanitizeData($id, $data)
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

    public function sanitizeAndValidateParams(shopSalesChannelType $channel_type, $id, &$params, $params_mode)
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
                $this->errors[] = $e;
            }
        }
    }

    public function validateData($id, $data)
    {
        if (!$id && empty($data['type'])) {
            $this->errors[] = [
                'error_description' => _w('Sales channel type is required.'),
            ];
        }

        foreach (['name'] as $k) {
            if (array_key_exists($k, $data) && !strlen($data[$k])) {
                $this->errors[] = [
                    'error_description' => _w('This field is required'),
                    'field' => "data[{$k}]",
                ];
            }
        }
    }

    public static function saveWaidChannel(shopSalesChannelType $channel_type, array &$channel)
    {
        if (!$channel_type instanceof shopSalesChannelWaidInterface) {
            return;
        }
        $wa_services = new waServicesApi();
        if (!$wa_services->isConnected()) {
            return;
        }

        list($headless_url, $save_params) = $channel_type->getWaidChannelParams($channel);

        $request = [
            'app_channel_id' => 'shop-'.$channel['id'],
            'app_platform' => $channel['type'],
            'headless_url' => $headless_url,
            'data' => $save_params,
        ];

        $subpath = '';
        $method = waNet::METHOD_POST;
        if (isset($channel['wa_channel_id'])) {
            $subpath = $channel['wa_channel_id'].'/';
            $method = waNet::METHOD_PUT;
        }

        $response = $wa_services->serviceCall('SHOP_CHANNEL', $request, $method, ['request_format' => waNet::FORMAT_JSON], $subpath);

        if (!isset($channel['wa_channel_id'])) {
            $channel['wa_channel_id'] = ifset($response, 'response', 'id', null);
            if (!$channel['wa_channel_id']) {
                throw new waException('Unable to save params to WAID API: '.
                    ifset($response, 'response', 'error_description',
                        ifset($response, 'response', 'error',
                            'unknown error'
                        )
                    )
                );
            }
        }
    }
}
