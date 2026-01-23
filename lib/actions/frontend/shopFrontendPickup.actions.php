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
            $routing = wa()->getRouting();
            $current_storefront = $routing->getDomain().'/'.rtrim((string) $routing->getRoute('url'), '/*');

            $channels = (new shopSalesChannelModel())->getAllWithParams();
            $channels = array_filter($channels, function ($_channel) use ($current_storefront) {
                if (
                    empty($_channel['params']['pickup'])
                    || empty($_channel['params']['pickup_storefronts'])
                    || !in_array($current_storefront, $_channel['params']['pickup_storefronts'])
                ) {
                    return false;
                }
                return true;
            });
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

                $result = $this->display([
                    'stocks' => $stocks,
                    'form' => $this->getForm(),
                    'product' => $product,
                    'selected_sku_id' => ifempty($selected_sku_id, $product['sku_id']),
                    'available_pickup'=> $channels,
                    'stocks_product_count' => $stocks_count,
                    'map' => $map,
                ], $template_path, true);
            }
        }

        print $result;
    }

    protected function createAction()
    {
        $pickup_id = waRequest::post('pickup_id');
        $customer = waRequest::post('customer');
        $customer = array_combine(array_column($customer, 'name'), array_column($customer, 'value'));


        $item = $this->getItem();
        $channel = (new shopSalesChannelModel())->getById($pickup_id);
        $stock_id = (int) ifempty($channel, 'params', 'stock_id', null);
        if ($stock_id) {
            $item['stock_id'] = $stock_id;
        }

        $customer = new waContact($customer);

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

    private function getForm()
    {
        return [
            _w('Фамилия') =>  '<input title="'._w('Фамилия').'" type="text" name="lastname" value="">',
            _w('Имя') =>  '<input title="'._w('Имя').'" type="text" name="firstname" value="">',
            _w('Номер телефона') =>  '<input title="'._w('Номер телефона').'" type="text" name="phone" value="">',
            _w('Email-адрес') =>  '<input title="'._w('Email-адрес').'" type="text" name="email" value="">',
        ];
    }
}
