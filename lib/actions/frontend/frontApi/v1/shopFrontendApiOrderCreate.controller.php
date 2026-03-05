<?php
/**
 * /order/create
 */
class shopFrontendApiOrderCreateController extends shopFrontendApiOrderCalculateController
{
    public function post()
    {
        $channel_id = waRequest::request('channel_id', '', waRequest::TYPE_STRING_TRIM);
        $customer_token = waRequest::request('customer_token', '', waRequest::TYPE_STRING_TRIM);

        $config = $this->getCheckoutConfig();
        $data = $this->processCalculate();

        $contact_field_values = $data['contact']->getCache();

        $order_data = [
            'id'              => null,
            'contact_id'      => ifset($data, 'contact', 'id', null),
            'currency'        => $data['order']['currency'],
            'payment_params'  => ifempty($data, 'payment', 'params', null),
            'shipping_params' => ifempty($data, 'shipping', 'params', null),
            'params'          => [
                    'shipping_id' => ifset($data, 'shipping', 'selected_variant', 'variant_id', null),
                    'payment_id'  => ifset($data, 'payment', 'id', null),
                    'departure_datetime' => shopDepartureDateTimeFacade::getDeparture($config['schedule'])->getDepartureDateTime(),
                ] + $this->getOrderParamsFromOrder($data['order']) + $this->getOrderParamsFromRequest() + ifempty($data, 'order', 'params', []),

            'comment'  => ifset($data, 'result', 'confirm', 'comment', ''),
            'shipping' => $data['order']['shipping'],

            'customer' => $contact_field_values,
            'items'    => $data['order']['items'],
            'discount' => 'calculate',
            'tax'      => 'calculate',
        ];

        $options = [
            'customer_validation_disabled' => true,
            'customer_is_company'          => ifset($data, 'contact', 'is_company', false),
            'customer_form_fields'         => array_keys($contact_field_values),
            'customer_add_multifields'     => true,
            'ignore_stock_validate'        => true,
        ];

        if ($channel_id) {
            $sales_channel_model = new shopSalesChannelModel();
            if (preg_match('#^([a-z0-9]+):(\d+)$#', $channel_id, $matches)) {
                if ($channel = $sales_channel_model->getByField(['id' => $matches[2], 'type' => $matches[1]])) {
                    $order_data['params']['sales_channel'] = $channel_id;
                    $order_data['params']['sales_channel_name'] = ifempty($channel, 'name', null);
                }
            } else if (wa_is_int($channel_id)) {
                $channel = $sales_channel_model->getById($channel_id);
                if ($channel) {
                    $order_data['params']['sales_channel'] = $channel['type'].':'.$channel['id'];
                    $order_data['params']['sales_channel_name'] = ifempty($channel, 'name', null);
                }
            }
        }

        try {
            $order = new shopOrder($order_data, $options);
        } catch (Throwable $ex) {
            waLog::log([
                'Unable to create order via Front API',
                $ex->getMessage(),
                $ex->getCode(),
                $ex instanceof waException ? $ex->getFullTraceAsString() : $ex->getTraceAsString(),
            ]);
            throw new waAPIException('checkout_error', _w('Failed to create an order.'), 500);
        }

        list($stock_id, $virtualstock_id) = shopFrontendCheckoutAction::determineStockIds($order);
        if ($stock_id || $virtualstock_id) {
            $order['params'] = [
                    'stock_id'        => $stock_id,
                    'virtualstock_id' => $virtualstock_id,
                ] + $order['params'];
        }

        try {
            $saved_order = $order->save();
            $this->logAgreementAcceptance($saved_order, $config);
        } catch (waException $ex) {
            $errors = $order->errors();
            throw new waAPIException('checkout_error', _w('Failed to create an order.'), 400, [
                'data' => $errors,
            ]);
        }

        try {
            $this->updateCustomerPhoto($saved_order);
        } catch (Throwable $ex) {
            // ignore
        }
        
        (new shopApiCart($customer_token))->clear();

        $this->response = [
            'order_id' => (int) $saved_order->getId(),
            'order_id_str' => $saved_order['id_str'],
            'order_total' => (float) $saved_order['total'],
            'order_currency' => $saved_order['currency'],
            'order_total_str' => shop_currency_html($saved_order['total'], $saved_order['currency'], $saved_order['currency']),
            'code' => $saved_order->getPaymentLinkHash(),
        ];
    }

    protected function logAgreementAcceptance($order, $config)
    {
        if (ifempty($config, 'customer', 'service_agreement', false)) {
            wa('webasyst');
            webasystHelper::logAgreementAcceptance(
                'service_agreement',
                ifset($config, 'customer', 'service_agreement_hint', ''),
                ifset($config, 'customer', 'service_agreement', 'notice'),
                ifset($order, 'contact_id', null),
                'shop'
            );
        }
        if (ifempty($config, 'confirmation', 'terms', false)) {
            wa('webasyst');
            webasystHelper::logAgreementAcceptance(
                'terms',
                ifset($config, 'confirmation', 'terms_text', ''),
                'checkbox',
                ifset($order, 'contact_id', null),
                'checkout'
            );
        }
    }

    protected function updateCustomerPhoto(shopOrder $order)
    {
        $customer_input_data = waRequest::request('customer');
        if (empty($customer_input_data['photo_url'])) {
            return;
        }

        $path_to_tmp_image_file = $this->obtainPhoto($customer_input_data['photo_url']);
        if (!$path_to_tmp_image_file) {
            return;
        }

        $order['contact']->setPhoto($path_to_tmp_image_file);
        waFiles::delete($path_to_tmp_image_file);
    }

    protected function obtainPhoto(string $url): string
    {
        if (substr($url, 0, 5) === 'data:') {
            // save data-URL to file
            [$mime_type, $image_contents] = $this->decodeDataUrl($url);
        } else if (strtolower(substr($url, 0, 6)) === 'https:' || strtolower(substr($url, 0, 5)) === 'http:') {
            // fetch image from URL and save to file
            $image_contents = @file_get_contents($url);
            if (!$image_contents) {
                return '';
            }
        }

        $file_path = tempnam(sys_get_temp_dir(), 'imgwacontact');
        file_put_contents($file_path, $image_contents);
        return $file_path;
    }

    protected function decodeDataUrl(string $url)
    {
        $data = substr($url, 5);
        [$meta, $data] = explode(',', $data, 2);
        [$mime_type, $encoding] = explode(';', $meta, 2) + ['', ''];
        switch ($encoding) {
            case '':
            case 'base64':
                $data = base64_decode(str_replace(' ', '+', $data));
                break;
            default:
                throw new waException('Unknown encoding in data URL '.$encoding);
        }

        return [$mime_type, $data];
    }

    protected function getOrderParamsFromOrder($order)
    {
        $result = $order['params'];
        foreach($result as $k => $v) {
            if (substr($k, 0, 9) == 'shipping_' || substr($k, 0, 8) == 'payment_') {
                unset($result[$k]);
            }
        }
        return $result;
    }

    protected function getOrderParamsFromRequest()
    {
        $params = [
            'ip'         => waRequest::getIp(),
            'user_agent' => waRequest::getUserAgent(),
        ];

        $routing_url = wa()->getRouting()->getRootUrl();
        $params['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');

        $utm = waRequest::request('utm', null, 'array');
        if ($utm) {
            foreach ($utm as $k => $v) {
                $params['utm_'.$k] = $v;
            }
        }

        return $params;
    }
}
