<?php

class shopWorkflowCreateAction extends shopWorkflowAction
{
    /**
     * @param array $data
     * @return waContact
     */
    protected function getContact($data)
    {
        if (isset($data['contact'])) {
            if (is_numeric($data['contact'])) {
                return new waContact($data['contact']);
            } else {
                /**
                 * @var waContact $contact
                 */
                $contact = $data['contact'];
                if (!$contact->getId()) {
                    $auth_config = wa()->getAuthConfig();
                    if (!empty($auth_config['params']['confirm_email']) &&
                        $this->getConfig()->getGeneralSettings('guest_checkout') == 'merge_email' &&
                        $contact->get('email', 'default')
                    ) {
                        // try find exists contact by email
                        $contact_emails_model = new waContactEmailsModel();
                        $contact_id = $contact_emails_model->getMainContactMyEmail($contact->get('email', 'default'));
                        if ($contact_id) {
                            $contact_data = $contact->load();
                            $contact = new waContact($contact_id);
                            $this->mergeContactData($contact, $contact_data);
                        }
                        $contact->save();
                    } else {
                        // create new contact
                        $contact->save();
                    }
                    // if user has been created
                    if ($contact['password'] && (empty($contact_id) || empty($is_password))) {

                        $this->waLog('signup', wa()->getEnv(), $contact->getId(), $contact->getId());

                        $signup_action = new shopSignupAction();
                        $signup_action->send($contact);

                        /**
                         * @event signup
                         * @param waContact $contact
                         */
                        wa('shop')->event('signup', $contact);
                    }
                }
                return $contact;
            }
        } else {
            return wa()->getUser();
        }
    }

    /**
     * Subroutine for merge contact data into found contact when 'guest_checkout' setting is 'merge_email'
     * @param waContact $contact
     * @param array $contact_data
     */
    protected function mergeContactData($contact, $contact_data)
    {
        $is_password = $contact['password']; // if the user has previously created an order, but did not create an account

        foreach ($contact_data as $field_id => $data) {
            $field = waContactFields::get($field_id);

            $is_multi = $field && $field->isMulti();

            if ($is_multi) {
                if (!$this->isAllIntKeys($data)) {
                    $data = array($data);
                } else {
                    $data = array_values($data);
                }
            }


            if ($field_id === 'email' || $field_id === 'phone') {
                foreach ($data as $sort => &$value) {
                    if (is_array($value)) {
                        unset($value['status']);
                    }
                }
                unset($value);
            }

            // if user registers
            if ($field_id == 'password' && !$is_password && $data) {
                $contact->setPassword($data, true);
                continue;
            }

            if ($is_multi) {
                foreach ($data as $value) {
                    $ext = is_array($value) && isset($value['ext']) ? $value['ext'] : null;
                    $clean_value = $this->extractCleanValue($value, $field);

                    if ($ext !== null && strlen($ext) > 0) {
                        $contact_value = $contact[$field_id . '.' . $ext];
                    } else {
                        $contact_value = $contact[$field_id];
                    }

                    $found_index = $this->findMultiFieldValueInList($clean_value, $contact_value, $field);

                    if ($found_index === null) {
                        $value = $this->prepareValueForAdd($clean_value, $ext, $field);
                        $contact->add($field_id, $value);
                    }
                }
            } else {
                $contact->set($field_id, $data);
            }
        }
    }

    /**
     * Check is array is kinda list, i.e. array that indexed only numeric keys
     * @param $array
     * @return bool
     */
    protected function isAllIntKeys($array)
    {
        if (!is_array($array)) {
            return false;
        }
        $keys = array_keys($array);
        foreach ($keys as $key) {
            if (!wa_is_int($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $value - already have typecast single value of multi field values
     * @see extractCleanValue
     * @param $list
     * @param waContactField $field
     * @return int|string|null
     */
    protected function findMultiFieldValueInList($value, $list, $field)
    {
        $is_composite = $field instanceof waContactCompositeField;
        foreach ($list as $index => $list_value) {
            $list_value = $this->extractCleanValue($list_value, $field);
            if ($is_composite) {
                // filter for keys (subfields)
                $keys_filter = function($k) use($field) {
                    if ($field->getId() === 'address' && ($k === 'lat' || $k === 'lng')) {
                        return false;
                    }
                    return true;
                };

                $list_value = array_filter($list_value, $keys_filter, ARRAY_FILTER_USE_KEY);
                $value = array_filter($value, $keys_filter, ARRAY_FILTER_USE_KEY);

                ksort($list_value);
                ksort($value);

                $match = $list_value === $value;
            } else {
                $match = $value == $list_value;
            }
            if ($match) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Works only for field that is isMulti()
     * Return clean value without 'ext', 'status', etc.
     * @param array|scalar $value
     * @param waContactField $field
     * @return string|array
     */
    protected function extractCleanValue($value, $field)
    {
        $is_composite = $field instanceof waContactCompositeField;
        if ($is_composite) {
            if (isset($value['data']) && (isset($value['ext']) || isset($value['value']))) {
                $value = $value['data'];
            }
        } else {
            if (isset($value['value'])) {
                $value = $value['value'];
            } elseif ($field->getId() === 'email' && isset($value['email'])) {
                $value = $value['email'];
            }
            if (!is_scalar($value)) {
                $value = '';
            }
        }
        return $value;
    }

    /**
     * Works only for field that is isMulti()
     * Prepare value for waContact->add method
     * @param array|scalar $value
     * @param string|null $ext
     * @param waContactField $field
     * @return array
     */
    protected function prepareValueForAdd($value, $ext, $field)
    {
        $is_composite = $field instanceof waContactCompositeField;
        if ($is_composite) {
            return array(
                'data' => $value,
                'ext' => $ext
            );
        } else {
            return array(
                'value' => $value,
                'ext' => $ext
            );
        }
    }

    public function execute($data = null)
    {
        if (wa()->getEnv() == 'frontend') {
            // Save final stock and virtual stock in order params
            list($virtualstock, $stock) = self::determineOrderStocks($data);
            $data['params']['virtualstock_id'] = $virtualstock ? $virtualstock['id'] : null;
            $data['params']['stock_id'] = $stock ? $stock['id'] : null;
            self::fillItemsStockIds($data['items'], $virtualstock, $stock);
        }
        if (!empty($data['currency'])) {
            $currency = $data['currency'];
        } else {
            $currency = $this->getConfig()->getCurrency(false);
            $data['currency'] = $currency;
        }
        $rate_model = new shopCurrencyModel();
        $row = $rate_model->getById($currency);
        $rate = $row['rate'];

        // Save contact
        $contact = $this->getContact($data);

        // Calculate subtotal, taking currency conversion into account
        $subtotal = 0;
        foreach ($data['items'] as &$item) {
            $item['currency'] = ifempty($item, 'currency', $currency);
            if ($currency != $item['currency']) {
                $item['price'] = shop_currency($item['price'], $item['currency'], null, false);
                if (!empty($item['purchase_price'])) {
                    $item['purchase_price'] = shop_currency($item['purchase_price'], $item['currency'], null, false);
                }
                $item['currency'] = $currency;
            } else {
                $item['price'] = shop_currency($item['price'], $item['currency'], $currency, false);
            }
            $subtotal += $item['price'] * $item['quantity'];
        }
        unset($item);

        $data['shipping'] = shop_currency($data['shipping'], $currency, $currency, false);

        // Calculate discount, unless already set
        if ($data['discount'] === '') {
            $data['total'] = $subtotal;
            $data['discount_description'] = null;
            $data['discount'] = shopDiscounts::apply($data, $data['discount_description']);
        } else {
            if (empty($data['discount_description']) && !empty($data['discount'])) {
                $data['discount_description'] = sprintf_wp('Discount specified manually during order creation: %s', shop_currency($data['discount'], $currency, $currency));
            }
        }

        // Calculate taxes
        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = $contact->getFirst('address');
        }
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = $contact->getFirst('address');
        }

        $discount_rate = $subtotal ? ($data['discount'] / $subtotal) : 0;
        $taxes_params = array(
            'shipping'      => isset($shipping_address['data']) ? $shipping_address['data'] : array(),
            'billing'       => isset($billing_address['data']) ? $billing_address['data'] : array(),
            'discount_rate' => $discount_rate,
        );
        if (!empty($data['params']['shipping_tax_id'])) {
            $data['items']['%shipping%'] = array(
                'type'     => 'shipping',
                'tax_id'   => $data['params']['shipping_tax_id'],
                'quantity' => 1,
                'price'    => $data['shipping'],
                'currency' => $currency,
            );
        }
        $taxes = shopTaxes::apply($data['items'], $taxes_params, $currency);
        if (isset($data['items']['%shipping%']['tax'])) {
            $data['params']['shipping_tax'] = $data['items']['%shipping%']['tax'];
            $data['params']['shipping_tax_percent'] = $data['items']['%shipping%']['tax_percent'];
            $data['params']['shipping_tax_included'] = $data['items']['%shipping%']['tax_included'];
        }
        unset($data['items']['%shipping%']);

        $tax = $tax_included = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
            if (isset($t['sum_included'])) {
                $tax_included += $t['sum_included'];
            }
        }

        $order = array(
            'state_id'  => 'new',
            'total'     => $subtotal - $data['discount'] + $data['shipping'] + $tax,
            'currency'  => $currency,
            'rate'      => $rate,
            'tax'       => $tax_included + $tax,
            'discount'  => $data['discount'],
            'shipping'  => $data['shipping'],
            'comment'   => isset($data['comment']) ? $data['comment'] : '',
            'unsettled' => !empty($data['unsettled']) ? 1 : 0,

        );
        $order['contact_id'] = $contact->getId();

        // Add contact to 'shop' category
        $contact->addToCategory('shop');

        // Save order
        $order_id = $this->order_model->insert($order);

        // Create record in shop_customer, or update existing record
        $scm = new shopCustomerModel();
        $scm->updateFromNewOrder($order['contact_id'], $order_id, ifset($data['params']['referer_host']));

        // save items
        $parent_id = null;
        foreach ($data['items'] as $item) {
            $item['order_id'] = $order_id;
            if ($item['type'] == 'product') {
                $parent_id = $this->order_items_model->insert($item);
            } elseif ($item['type'] == 'service') {
                $item['parent_id'] = $parent_id;
                $this->order_items_model->insert($item);
            }
        }

        // Order params
        if (empty($data['params'])) {
            $data['params'] = array();
        }

        if (!empty($data['params']['shipping_id'])) {
            try {
                if ($shipping_plugin = shopShipping::getPlugin(null, $data['params']['shipping_id'])) {
                    $shipping_currency = $shipping_plugin->allowedCurrency();
                    $data['params']['shipping_currency'] = $shipping_currency;
                    if ($row = $rate_model->getById($shipping_currency)) {
                        $data['params']['shipping_currency_rate'] = str_replace(',', '.',$row['rate'] );
                    }
                }

            } catch (waException $ex) {

            }
        }

        $data['params']['auth_code'] = self::generateAuthCode($order_id);
        $data['params']['auth_pin'] = self::generateAuthPin();
        if (empty($data['params']['sales_channel'])) {
            if (empty($data['params']['storefront'])) {
                $data['params']['sales_channel'] = 'other:';
            } else {
                $data['params']['sales_channel'] = 'storefront:'.$data['params']['storefront'];
            }
        }

        if (!empty($data['params']['storefront'])) {
            $data['params']['signup_url'] = shopHelper::generateSignupUrl($contact, $data['params']['storefront']);
        }

        // Save params
        $this->order_params_model->set($order_id, $data['params']);

        // Write discounts description to order log
        if (!empty($data['discount_description']) && !empty($data['discount']) && empty($data['skip_description'])) {
            $this->order_log_model->add(array(
                'order_id'        => $order_id,
                'contact_id'      => $order['contact_id'],
                'before_state_id' => $order['state_id'],
                'after_state_id'  => $order['state_id'],
                'text'            => $data['discount_description'],
                'action_id'       => '',
            ));
        }

        $this->waLog('order_create', $order_id, null, $order['contact_id']);

        $this->marketingPromoWorkflowRun($order_id);

        return array(
            'order_id'   => $order_id,
            'contact_id' => wa()->getEnv() == 'frontend' ? $contact->getId() : wa()->getUser()->getId()
        );
    }

    // Fill stock_id for order items when order is created in frontend.
    protected static function fillItemsStockIds(&$items, $virtualstock, $stock)
    {
        if (!$virtualstock && !$stock) {
            return;
        }

        // Not all SKUs have stocks enabled. Figure out which ones don't.
        $sku_ids = array();
        foreach ($items as $item) {
            if ($item['type'] == 'product') {
                $sku_ids[(int)$item['sku_id']] = true;
            }
        }
        $product_stocks_model = new shopProductStocksModel();
        $sku_ids = $product_stocks_model->filterSkusByNoStocks(array_keys($sku_ids));
        $sku_ids_map = array_fill_keys($sku_ids, true);

        // Set stock_id and virtualstock_id for $items where applicable
        foreach ($items as &$item) {
            if ($item['type'] != 'product' || isset($sku_ids_map[$item['sku_id']])) {
                // Ignore services and SKUs that do not use stocks
                continue;
            }
            if (array_key_exists('stock_id', $item)) {
                // Do not overwrite SKU stock if already specified
                continue;
            }

            $item['stock_id'] = $stock ? $stock['id'] : null;
            if ($virtualstock) {
                $item['virtualstock_id'] = $virtualstock['id'];
                if (!$item['stock_id']) {
                    // Determine real stock_id from virtual stock
                    $sku_stock = $product_stocks_model->getCounts($item['sku_id']);
                    $item['stock_id'] = self::getItemSubstockId($item['quantity'], $sku_stock, $virtualstock['substocks']);
                }
            }
        }
        unset($item);
    }

    public static function getItemSubstockId($ordered_quantity, $sku_stock, $substocks)
    {
        $candidates = array();
        foreach ($substocks as $substock_id) {
            if (!isset($sku_stock[$substock_id]) || $sku_stock[$substock_id] >= $ordered_quantity) {
                return $substock_id;
            } elseif ($sku_stock[$substock_id] > 0) {
                $candidates[] = $substock_id;
            }
        }
        if ($candidates) {
            return reset($candidates);
        } else {
            return reset($substocks);
        }
    }

    // Determine virtual stock and/or stock applicable for the order from order params and routing params
    protected static function determineOrderStocks($data)
    {
        if (!empty($data['params']['virtualstock_id']) && wa_is_int($data['params']['virtualstock_id'])) {
            $virtualstock_id = $data['params']['virtualstock_id'];
        } else {
            $virtualstock_id = null;
        }

        if (!empty($data['params']['stock_id'])) {
            $stock_id = $data['params']['stock_id'];
        } else {
            $stock_id = waRequest::param('stock_id');
        }
        if (!wa_is_int($stock_id)) {
            if ($stock_id && empty($virtualstock_id) && is_string($stock_id) && $stock_id[0] == 'v') {
                $virtualstock_id = (int)substr($stock_id, 1);
                $virtualstock_id = ifempty($virtualstock_id);
            }
            $stock_id = null;
        }

        // Make sure specified stocks exist
        $stocks = shopHelper::getStocks();
        if ($virtualstock_id && empty($stocks['v'.$virtualstock_id]['substocks'])) {
            $virtualstock_id = null;
        }
        if ($stock_id && empty($stocks[$stock_id])) {
            $stock_id = null;
        }

        // If we couldn't determine the stock, use first available
        if (!$virtualstock_id && !$stock_id) {
            $stock_ids = array_filter(array_keys($stocks), 'wa_is_int');
            $stock_id = key($stock_ids);
        }

        return array(ifset($stocks['v'.$virtualstock_id]), ifset($stocks[$stock_id]));
    }

    public function postExecute($order_id = null, $result = null)
    {
        $order_id = $result['order_id'];

        $data = is_array($result) ? $result : array();
        $data['order_id'] = $order_id;
        $data['action_id'] = $this->getId();

        $data['before_state_id'] = '';
        $data['after_state_id'] = 'new';

        $data['id'] = $this->order_log_model->add($data);

        /**
         * @event order_action.create
         */
        wa('shop')->event('order_action.create', $data);

        $order = $this->order_model->getOrder($order_id);
        $customer = new waContact($order['contact_id']);
        // send notifications
        shopNotifications::send('order.'.$this->getId(), array(
            'order'       => $order,
            'customer'    => $customer,
            'status'      => $this->getWorkflow()->getStateById($data['after_state_id'])->getName(),
            'action_data' => $data
        ));

        // Update stock count, but take into account 'update_stock_count_on_create_order'-setting
        $app_settings_model = new waAppSettingsModel();
        if (!$app_settings_model->get('shop', 'disable_stock_count') && $app_settings_model->get('shop', 'update_stock_count_on_create_order')) {
            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                shopProductStocksLogModel::TYPE_ORDER,
                /*_w*/('Order %s was placed'),
                array(
                    'order_id' => $order_id
                )
            );
            $this->order_model->reduceProductsFromStocks($order_id);

            shopProductStocksLogModel::clearContext();
        }

        $email = $customer->get('email', 'default');
        if ($email) {
            $this->sendPrintforms($order, $email, $data);
        }

        $this->setPackageState(waShipping::STATE_DRAFT, $order_id, array('log' => true));

        return $order_id;
    }

    /**
     * Random string to authorize user from email link
     * @param $order_id
     * @return string
     */
    public static function generateAuthCode($order_id)
    {
        $md5 = md5(uniqid($order_id, true).mt_rand().mt_rand().mt_rand());
        return substr($md5, 0, 16).$order_id.substr($md5, 16);
    }

    /** Random 4-digit code to authorize user from email link */
    public static function generateAuthPin()
    {
        return (string)mt_rand(1000, 9999);
    }

    /**
     * @param $order
     * @param string $email
     * @param array
     */
    private function sendPrintforms($order, $email, $data)
    {
        $queue = array();
        $forms = shopPrintforms::getOrderPrintforms($order);
        foreach ($forms as $id => $form) {
            if (!empty($form['emailprintform'])) {
                if (strpos($id, '.')) {
                    list($type, $form_id) = explode('.', $id, 2);
                } else {
                    $type = 'shop';
                    $form_id = $id;
                }
                try {
                    switch ($type) {
                        case 'shipping':
                            $key = ifempty($order['params'][$type.'_id']);
                            $plugin = shopShipping::getPlugin(null, $key);
                            $html = $plugin->displayPrintForm($form_id, shopPayment::getOrderData($order, $plugin));
                            break;
                        case 'payment':
                            $key = ifempty($order['params'][$type.'_id']);
                            $plugin = shopPayment::getPlugin(null, $key);
                            $html = $plugin->displayPrintForm($form_id, shopPayment::getOrderData($order, $plugin));
                            break;
                        default: # it's shop plugin
                            $plugin = wa('shop')->getPlugin($id);
                            $html = null;
                            if (($plugin instanceof shopPrintformPlugin) && $plugin->getSettings('emailprintform')) {
                                $html = $plugin->renderPrintform($order);
                            }
                            break;
                    }

                    if (!empty($html)) {
                        $queue[$id] = array(
                            'html' => $html,
                            'name' => ifset($form['name'], $id),
                        );


                    }
                } catch (Exception $ex) {
                    waLog::log($ex->getMessage(), 'shop/workflow.log');
                }
            }
        }

        if (!empty($queue)) {
            $store_email = shopHelper::getStoreEmail(ifempty($order['params']['storefront']));

            $order_id_str = shopHelper::encodeOrderId($order['id']);

            foreach ($queue as $form) {
                try {
                    $message = new waMailMessage(sprintf("%s %s", $form['name'], $order_id_str), $form['html']);
                    $message->setTo(array($email));
                    $message->setFrom($store_email);

                    if ($message->send()) {
                        $log = sprintf(_w("Printform <strong>%s</strong> sent to customer."), $form['name']);

                        $this->order_log_model->add(array(
                            'order_id'        => $data['order_id'],
                            'contact_id'      => $order['contact_id'],
                            'action_id'       => '',
                            'text'            => '<i class="icon16 email"></i> '.$log,
                            'before_state_id' => $data['after_state_id'],
                            'after_state_id'  => $data['after_state_id'],
                        ));
                    }
                } catch (Exception $ex) {
                    waLog::log($ex->getMessage(), 'shop/workflow.log');
                }
            }
        }
    }

    /**
     * Runner marketing promo workflow
     * @param int $order_id
     * @throws waException
     */
    private function marketingPromoWorkflowRun($order_id)
    {
        $order = new shopOrder($order_id);
        $workflow = new shopMarketingPromoWorkflow($order);
        $workflow->run();
    }
}
