<?php
/**
 * /order/create
 */
class shopFrontendApiOrderCreateController extends shopFrontendApiOrderCalculateController
{
    public function post()
    {
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
                    // stock_id, virtualstock_id see below
                ] + $this->getOrderParamsFromOrder($data['order']) + $this->getOrderParamsFromRequest(),

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

        (new shopApiCart($customer_token))->clear();

        $this->response = [
            'order_id' => $saved_order->getId(),
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
