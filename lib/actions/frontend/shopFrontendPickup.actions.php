<?php

class shopFrontendPickupActions extends waActions
{
    protected function execute($action)
    {
        if (!waRequest::isXMLHttpRequest()) {
            $this->redirect(wa()->getRouteUrl('shop/frontend/'));
        }
        parent::execute($action);
    }

    protected function defaultAction()
    {
        $this->redirect(wa()->getRouteUrl('shop/frontend/'));
    }

    protected function dialogAction()
    {
        $product_id = waRequest::post('product_id');
        $selected_sku_id = waRequest::post('sku_id');
        $title = waRequest::post('title');
        if (empty($product_id)) {
            print _w('Product not found.');
            return;
        }
        $product = new shopProduct($product_id);
        if (!$product->id) {
            print _w('Product not found.');
            return;
        }

        $result = null;
        $theme = new waTheme(waRequest::getTheme(), 'shop');
        $theme_path = $theme->getPath();
        $template_path = "$theme_path/pickup.html";
        if (!file_exists($template_path)) {
            $template_path = wa()->getAppPath('templates/actions/channels/pickup.include.html', 'shop');
        }
        if (!empty($product)) {
            $channels = shopViewHelper::getPickupChannels();
            if (!empty($channels)) {
                $virtual_stock = [];
                $available_stock_ids = [];
                $stocks = shopHelper::getStocks(true);
                foreach ($channels as $_channel) {
                    $stock_id = ifempty($_channel, 'params', 'stock_id', null);
                    if ($stock_id && !empty($stocks[$stock_id])) {
                        if (isset($stocks[$stock_id]['substocks'])) {
                            $available_stock_ids = array_merge($available_stock_ids, $stocks[$stock_id]['substocks']);
                            foreach ($stocks[$stock_id]['substocks'] as $_substock_id) {
                                $virtual_stock[$_substock_id][] = $stock_id;
                            }
                        } else {
                            $available_stock_ids[] = $stock_id;
                        }
                    }
                }

                $stocks_count = [];
                $stocks_default = [];
                $stocks_product_count = (new shopProductStocksModel())->getByField(['stock_id' => array_unique($available_stock_ids), 'product_id' => $product['id']], true);
                foreach (array_keys($stocks) as $_stock_id) {
                    $stocks_default[$_stock_id] = ['stock_id' => $_stock_id, 'count' => 0];
                }
                if (isset($product['skus'])) {
                    foreach ($product['skus'] as $_sku) {
                        $sku_stocks = $stocks_default;
                        foreach ($stocks_product_count as $key => $_stock_data) {
                            if (
                                $product['id'] == $_stock_data['product_id']
                                && $_sku['id'] == $_stock_data['sku_id']
                            ) {
                                if (isset($sku_stocks[$_stock_data['stock_id']])) {
                                    $sku_stocks[$_stock_data['stock_id']]['count'] = $_stock_data['count'];
                                    unset($stocks_product_count[$key]);
                                }
                                if ($virt_stocks = ifempty($virtual_stock, $_stock_data['stock_id'], [])) {
                                    foreach ($virt_stocks as $_virt_stock) {
                                        $sku_stocks[$_virt_stock]['count'] += $_stock_data['count'];
                                    }
                                    unset($stocks_product_count[$key]);
                                }
                            }
                        }

                        $stocks_count[] = [
                            'product_id' => $product['id'],
                            'sku_id' => $_sku['id'],
                            'stocks' => array_values($sku_stocks)
                        ];
                    }
                }

                // These are used by map that shows pickpoint locations
                $adapter = null;
                $api_key = null;
                try {
                    $adapter = wa()->getMap()->getId();
                    if ($adapter === 'yandex') {
                        $api_key = 'apikey';
                    } else {
                        $api_key = 'key';
                    }
                    $api_key = wa()->getMap()->getSettings($api_key);
                } catch (Exception $e) {
                }
                $map = compact('adapter', 'api_key');

                $customer = [];
                $user = wa()->getUser();
                $contact = new waContact($user->getId());
                if ($contact->getId()) {
                    $userpic = $user->getPhoto2x(20);
                    $customer = [
                        'firstname' => $contact->get('firstname') ? $contact->get('firstname') : $user->getName(),
                        'lastname' => $contact->get('lastname'),
                        'phone' => $contact->get('phone', 'default'),
                        'email' => $contact->get('email', 'default'),
                        'userpic' => $userpic,
                    ];
                }

                $result = $this->display([
                    'title' => $title,
                    'stocks' => $stocks,
                    'form' => $this->getForm($customer),
                    'product' => $product,
                    'selected_sku_id' => ifempty($selected_sku_id, $product['sku_id']),
                    'available_pickup'=> $channels,
                    'stocks_product_count' => $stocks_count,
                    'map' => $map,
                    'customer' => $customer,
                ], $template_path, true);
            }
        }

        print $result;
    }

    protected function createAction()
    {
        $pickup_id = waRequest::post('pickup_id');
        $customer = waRequest::post('customer', [], waRequest::TYPE_ARRAY);
        $customer = array_combine(array_column($customer, 'name'), array_column($customer, 'value'));
        unset($customer['id']);

        $item = $this->getItem();
        $channel = (new shopSalesChannelModel())->getById($pickup_id);
        $stock_id = (int) ifempty($channel, 'params', 'stock_id', null);
        if ($stock_id) {
            $item['stock_id'] = $stock_id;
        }

        $user = wa()->getUser();
        if ($user->exists()) {
            $customer = $user;
        } else {
            $customer = new waContact($customer);
        }

        $routing_url = wa()->getRouting()->getRootUrl();
        $storefront = wa()->getConfig()->getDomain().($routing_url ? "/$routing_url" : '');
        $order = [
            'contact' => $customer,
            'items' => [$item],
            'currency' => wa('shop')->getConfig()->getCurrency(false),
            'shipping' => 0,
            'discount' => '',
            'state_id' => 'pickup',
            'notifications_silent' => true,
            'params' => [
                'ip' => waRequest::getIp(),
                'user_agent' => waRequest::getUserAgent(),
                'sales_channel' => 'pos:'.ifset($channel, 'id', ''),
                'sales_channel_name' => ifset($channel, 'name', ''),
                'storefront' => $storefront
            ],
        ];
        if ($stock_id) {
            $order['params']['stock_id'] = $stock_id;
        }


        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {
            $saved_order = new shopOrder($order_id);
            $this->displayJson([
                'id' => (int) $order_id,
                'code' => $saved_order->getPaymentLinkHash(),
            ]);
        } else {
            $this->displayJson([], ['server_error' => _w('An error has occurred.')]);
        }
    }

    private function getItem()
    {
        $sku_id = waRequest::post('sku_id');

        $sku = (new shopProductSkusModel())->getById($sku_id);
        $product = (new shopProductModel())->getById($sku['product_id']);

        $item = [
            'type' => 'product',
            'product_id' => $sku['product_id'],
            'sku_id' => $sku_id,

            'sku_code' => $sku['sku'],
            'name' => $product['name'],

            'price' => $sku['price'],
            'purchase_price' => $sku['purchase_price'],
            'currency' => $product['currency'],

            'quantity' => 1,
            'stock_unit_id' => $product['stock_unit_id'],
            'quantity_denominator' => $product['count_denominator'],
            'services' => [],
            'codes' => [],
        ];
        if ($sku['name']) {
            $item['name'] .= ' ('.$sku['name'].')';
        }

        return $item;
    }

    private function getForm($customer = [])
    {
        $disabled_attr = $customer ? ' readonly' : '';
        return [
            _w('First name') => '<input title="'._w('First name').'" type="text" name="firstname" value="'.htmlentities(ifset($customer, 'firstname', '')).'"'.$disabled_attr.'>',
            _w('Last name') => '<input title="'._w('Last name').'" type="text" name="lastname" value="'.htmlentities(ifset($customer, 'lastname', '')).'"'.$disabled_attr.'>',
            _w('Phone') => '<input title="'._w('Phone').'" type="text" name="phone" value="'.htmlentities(ifset($customer, 'phone', '')).'"'.$disabled_attr.'>',
            _w('Email') => '<input title="'._w('Email').'" type="text" name="email" value="'.htmlentities(ifset($customer, 'email', '')).'"'.$disabled_attr.'>',
        ];
    }
}
