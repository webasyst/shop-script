<?php
/**
 * Implements sales channel type 'pos:<id>'
 * (point of sale)
 */
class shopPosSalesChannel extends shopSalesChannelType
{
    protected function getFormFieldsConfig($values = []): array
    {
        return [
            'stock_id' => array(
                'value'        => '',
                'title'        => _w('Stock'),
                'description'  => _w('Required. The selected stock is used for all orders processed via this point of sale; i.e., those with payment via the mobile POS and with in-store pickup.'),
                'control_type' => waHtmlControl::SELECT,
                'options'      => array_map(function ($s) {
                    if (!empty($s['substocks'])) {
                        $s['id'] = 'v'.$s['id'];
                    }
                    return ['value' => $s['id'], 'title' => $s['name']];
                },  ['' => ['id' => '', 'name' => _w('Select stock')]] + shopHelper::getStocks()),
            ),
        ];
    }

    protected function getPaymentMethods($values)
    {
        $default_params = [
            'namespace'       => 'data[params]',
            'control_wrapper' => '<div>%s %s %s</div>',
            'class'           => 'custom-ml-24',
        ];

        $payment_items = [];
        foreach (shopHelper::getPaymentMethods() as $p) {
            $params = [
                'name'    => 'payment_id_'.$p['id'],
                'label'   => $p['name'],
                'value'   => $p['id'],
                'checked' => isset($values['payment_id_'.$p['id']]),
            ];
            $payment_items[] = waHtmlControl::getControl(waHtmlControl::CHECKBOX, '', $params + $default_params);
        }

        return implode($payment_items);
    }

    protected function getStorefronts($values)
    {
        $default_params = [
            'namespace'       => 'data[params]',
            'control_wrapper' => '<div>%s %s %s</div>',
            'class'           => 'custom-ml-24',
        ];

        $storefronts = [];
        $list = new shopStorefrontList();
        $storefront_list = $list->fetchAll(['contact_type']);
        foreach ($storefront_list as $s) {
            $params = [
                'name'     => 'pickup_storefront_'.$s['url'],
                'label'    => $s['url'],
                'value'    => $s['url'],
                'checked'  => isset($values['pickup_storefront_'.$s['url']]),
                'disabled' => empty($values['pickup'])
            ];
            $storefronts[] = waHtmlControl::getControl(waHtmlControl::CHECKBOX, '', $params + $default_params);
        }

        return implode($storefronts);
    }

    public function sanitizeAndValidateParams(?int $id, array &$params, $params_mode): array
    {
        $errors = [];
        if ($params_mode == 'set' && empty($params['stock_id'])) {
            $errors['stock_id'] = [
                'error_description' => _w('This field is required'),
                'field' => 'data[params][stock_id]',
            ];
        }

        if (isset($params['stock_id'])) {
            $stocks = (array) shopHelper::getStocks();
            if (count($stocks) < 1) {
                $errors['stock_id'] = [
                    'error_description' => sprintf_wp("If you don't have any stock locations yet, create one in <a %s>Settings > Stocks</a>.", 'href="'.wa()->getAppUrl('shop/?action=settings#/stock/').'"'),
                    'field' => 'data[params][stock_id]',
                ];
            } elseif (!isset($stocks[$params['stock_id']])) {
                $errors['stock_id'] = [
                    'error_description' => _w('This field is required'),
                    'field' => 'data[params][stock_id]',
                ];
            }
        }

        return array_values($errors);
    }

    public function getFormHtml(array $channel): string
    {
        $model = new waAppSettingsModel();
        $map_adapter = $model->get('webasyst', 'backend_map_adapter', 'disabled');
        if ($channel['params']['map_adapter_disabled'] = $map_adapter !== 'disabled') {
            $map_options = [
                'width'   => '500px',
                'height'  => '150px',
                'zoom'    => 13,
                'static'  => true,
                'on_error' => '',
            ];
            if (!empty($channel['params']['latitude']) && !empty($channel['params']['longitude'])) {
                try {
                    $channel['params']['map_html'] = wa()->getMap($map_adapter)->getHTML([$channel['params']['latitude'], $channel['params']['longitude']], $map_options);
                } catch (Exception $e) {
                }
            } elseif (!empty($channel['params']['address'])) {
                try {
                    $channel['params']['map_html'] = wa()->getMap($map_adapter)->getHTML($channel['params']['address'], $map_options);
                } catch (Exception $e) {
                }
            }
        }

        $view = wa('shop')->getView();
        $view->assign([
            'channel' => $channel,
            'form_fields' => $this->getFormFields($channel),
            'storefronts' => $this->getStorefronts($channel['params']),
            'payment_methods' => $this->getPaymentMethods($channel['params']),
        ]);

        return $view->fetch('file:templates/actions/channels/pos_channel.include.html');
    }
}
