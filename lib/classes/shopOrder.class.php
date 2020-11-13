<?php

/**
 * Class shopOrder
 *
 * @todo DISCLAIMER: class still under construction!
 *
 * `shipping`, `tax`, `discount`, `discount_description` and `total` fields may have special behaviour
 * if something else was changed that may trigger recalculation. See self::$dependencies.
 *
 * # fields from shop_order table
 * @property-read int $id
 * @property-read int $contact_id           Customer contact
 * @property-read string $create_datetime   Order creation
 * @property-read string $update_datetime   Last order update
 * @property-read string $state_id          Order workflow state id, see Settings - Order states.
 * @property-read double $total             Order total in order currency
 * @property string $currency               ISO3 code - all prices here and in order items use this currency. Note that currency of existing order cannot be changed.
 * @property-read double $rate              Order currency rate relative to default shop currency, as used to be when order were created
 * @property-read double $tax               Total order tax in order currency. Set it into 'calculate' to recalculate tax;
 * @property double $shipping               Total order shipping cost in order currency.
 * @property double $discount               Total order discount in order currency. Set it into `null` or empty to hold previous calculated discount or set into 'calculate' to recalculate.
 * @property string $discount_description   Human-readable text description of how discounts were calculated. Intended for store admin, not customer. If you call without calculating a discount, then you will calculate the discount yourself and return the value without affecting the rest of the order.
 * @property-read string $auth_date         Date when order payment was auth, or NULL if it wasn't
 * @property-read string $paid_date         Date when order was paid, or NULL if it wasn't.
 * @property-read string $paid_year         Part of paid_date used for stats.
 * @property-read string $paid_quarter      Part of paid_date used for stats.
 * @property-read string $paid_month        Part of paid_date used for stats.
 * @property-read bool $is_first            `true` if this order is the first paid order of a customer
 * @property-read bool $unsettled           `true` if this order is unsettled (Created via payment callback and the order was not matched with the existing one)
 * @property string $comment                Text left by a customer during checkout
 * @property-read datetime $shipping_datetime Estimated shipping datetime as may be set by store admin using "Edit shipping details" order action.
 *
 * @property array[] $items                 Order items
 * @property-read array[] $items_extended   Different format for items
 * @property string[] $params               Custom order data from shop_order_params
 * @property-read array[] $log              Order history
 * @property-read array[] $last_action_datetime
 * @property-read double $subtotal          Subtotal is price*quantity for all items. This does not include discounts and certain taxes, and does not include shipping.
 * @property array[] $items_discount        Calculates the discount for each item and generates html. Can return the discount to items without calculating the total discount
 * @property-read array[] $items_tax        Calculate the tax for each item.  Can return the tax to items without calculating the total discount
 *
 * @property-read array[] $products
 *
 * # meta fields related to customer
 * @property-read waContact $contact        Customer contact
 * @property-read array $contact_essentials Customer data in array: id, name, email, phone, registered, photo_50x50.
 * @property-read array $shop_customer      Customer data from shop_customer table
 * @property-read waContact $wa_contact     Same as $this->contact, i.e. customer data
 * @property-read int $customer_id          Same as $this->contact_id, i.e. customer id
 * @property-write array $customer          Data to save to customer contact from customer form (as set up in settings-checkout)
 *
 * # extra view fields
 * @property-read string $id_str            Order id formatted according to rules as set up in store general settings
 * @property-read string $total_str         Order total formatted according to currency rules, with currency symbol included
 * @property-read string $create_datetime_str Human-readable order creation date and time.
 * @property-read string $icon              CSS class depending on order status.
 * @property-read string $style             CSS style depending on order status.
 *
 * @property-read string $shipping_name     Selected shipping plugin human-readable name
 * @property-read string $payment_name      Selected payment plugin human-readable name
 *
 * # Workflow related fields
 * @property-read shopWorkflowState $state  Current order state
 * @property-read shopWorkflowAction[] $actions array action_id => action class
 * @property-read array $workflow_action_elements
 *
 * # meta fields related to shipping
 * @property string[] $shipping_address       Customer shipping address as saved in order params. [address_subfield_id => string]
 * @property-read waShipping $shipping_plugin       Shipping plugin instance if selected
 * @property-read string $shipping_address_text     Customer shipping address as human-readable text in one line
 * @property-read string $shipping_address_html     Customer shipping address as human-readable text with beautiful html-format
 * @property-read array $shipping_custom_fields     Custom fields from shipping plugins
 * @property-read string $tracking                  HTML, contains tracking info or null if it not available
 * @property-read array $courier                    Courier data from shop_api_courier table
 * @property-read string $map                       HTML contains data to display map
 * @property-read array[] $shipping_methods
 * @property-read string $shipping_id
 * @property-read array[] $shipping_plugin_info            Get information about a specific plug-in installation
 *
 * # meta fields related to payment
 *
 * @property-read waPayment $payment_plugin     Payment plugin instance if selected
 * @property string[] $billing_address          Customer billing address as saved in order params. [address_subfield_id => string]
 * @property-read string $billing_address_text  Customer billing address as human-readable text
 *
 * @property-read string $source                Order source used for stats
 *
 * @property-read array $printforms             See shopPrintforms::getOrderPrintforms()
 *
 * @property-read array $coupon                 Discount coupon data if used for this order
 *
 * @todo move `items.selector`, `items._parent_index` and `items._index` into controller code — GUI depends selectors
 */
class shopOrder implements ArrayAccess
{
    protected $options = array(
        'name'                  => 'value',
        'mode'                  => 'read',
        'escape'                => false,
        'items_escape'          => false,
        'items_format'          => 'raw', # one of `raw`,`tree` or `flat`
        'items_extend_round'    => false,
        'shipping_round'        => false,
        'fields'                => '*',
        'product_fields'        => '*',
        'sku_fields'            => '*',
        'ignore_count_validate' => false,
        'return_stock'          => null,
    );
    protected $data = array();
    protected $original_data = array();
    protected $errors = null;

    /**
     * @var array This is fake order containing discounts
     */
    protected $calculated_discounts = null;

    protected $is_changed = array();

    /** @var shopWorkflow */
    protected $workflow;
    /** @var shopConfig */
    protected $config;


    # Models instances

    /** @var shopOrderModel */
    protected $model;

    /** @var  shopCurrencyModel */
    protected $currency_model;

    /**  @var shopProductSkusModel */
    private $product_skus_model;


    protected static $data_storages = array(
        'items'  => true,
        'params' => true,
        'log'    => true,
    );

    protected static $aliases = array(
        'order_id'    => 'id',
        'customer_id' => 'contact_id',
        'wa_contact'  => 'contact',
    );

    protected static $readonly_fields = array(
        'actions',
        'billing_address_text',
        'contact',
        'contact_essentials',
        'coupon',
        'courier',
        'create_datetime',
        'create_datetime_str',
        'customer_id',
        'icon',
        'id',
        'id_str',
        'is_first',
        'items_extended',
        'last_action_datetime',
        'log',
        'map',
        'paid_date',
        'paid_month',
        'paid_quarter',
        'paid_year',
        'payment_name',
        'payment_plugin',
        'printforms',
        'products',
        'rate',
        'shipping_address_text',
        'shipping_datetime',
        'shipping_id',
        'shipping_methods',
        'shipping_name',
        'shipping_plugin',
        'shop_customer',
        'source',
        'state',
        'state_id',
        'style',
        'subtotal',
        'total',
        'total_str',
        'tracking',
        'unsettled',
        'update_datetime',
        'wa_contact',
        'workflow_action_elements',
    );

    protected static $once_edit_fields = array(
        'currency',
        'contact_id',
    );

    protected static $dynamic_fields = array(
        'total',
    );

    /**
     * @var array Invalidation field map field1 => field2 => is_strict
     *
     * When field1 changes, field2 must be recalculated.
     * If dependency is marked as is_strict===true, field2 is always recalculated.
     * is_strict===false will only recalculate when something else has already marked it as is_changed with values `true` or `false`.
     */
    protected static $dependencies = array(
        'items'                => array(
            'items_extended' => true,
            'tax'            => true,
            'subtotal'       => true,
            'total'          => true,
            'shipping'       => false,
            'params'         => array(
                'shipping_name' => true,
            ),
            'discount'       => false,
        ),
        'shipping'             => array(
            'tax'    => true,
            'total'  => true,
            'params' => array(
                'shipping_name' => true,
            ),
        ),
        'discount_description' => array(
            'discount' => false,
        ),
        'payment'              => array(
            'params' => array(
                'payment_name' => true,
            ),
        ),
        'discount'             => array(
            'tax'      => true,
            'total'    => true,
            'subtotal' => true,
            'shipping' => false,
        ),
    );

    private static $product_fields = array(
        'sku_code'       => 'string',
        'price'          => 'float',
        'total_discount' => 'float',
        'quantity'       => 'int',
        'name'           => 'string',
    );

    private static $service_fields = array(
        'price'    => 'float',
        'quantity' => 'int',
        'name'     => 'string',
    );

    private static $cached_products = array();

    /**
     * @var array
     *
     * */
    private static $cached_services = array();

    private $item_ids;

    /**
     * Creates a new order object or a order object corresponding to existing order.
     *
     * @param int|array $data Order id or order data array
     * @param array $options
     * @throws waException
     */
    public function __construct($data = array(), $options = array())
    {
        $this->model = new shopOrderModel();
        $this->currency_model = new shopCurrencyModel();
        $this->workflow = new shopWorkflow();
        $this->config = wa('shop')->getConfig();
        $this->options = array_merge($this->options, $options);

        if (is_array($data)) {

            $id = ifset($data, 'id', null);
            foreach (self::$readonly_fields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
            $data['id'] = $id;

            if (!empty($data['id'])) {
                $this->original_data = $this->model->getById($data['id']);
                if (empty($this->original_data)) {
                    throw new waException('Order not found', 404);
                }
                $this->data = $this->original_data;
                $this->data['id'] = (int)$data['id'];
                foreach (self::$once_edit_fields as $field) {
                    if (isset($data[$field])) {
                        unset($data[$field]);
                    }
                }

                foreach ($this->readOnlyFields() as $field) {
                    if (isset($this->original_data[$field])) {
                        $data[$field] = $this->original_data[$field];
                    }
                }

                $data += array(
                    'params' => array(),
                );

            } else {
                $this->original_data = $this->model->getEmptyRow();
                $data['id'] = null;

                if (!isset($data['currency'])) {
                    $data['currency'] = $this->getCurrency();
                }
            }

            foreach (self::$dynamic_fields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }

            $fields = array('shipping',);
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $this->castPrice($data[$field], $data['currency']);
                }
            }

            uksort($data, array($this, 'sortDataFields'));

            foreach ($data as $name => $value) {
                $this->setData($name, $value);
            }
        } elseif ($data) {
            $order = $this->model->getById($data);
            if (empty($order)) {
                throw new waException('Order not found', 404);
            }
            $this->original_data = $order;

            if (!empty($order['params'])) {
                shopOrderParamsModel::workupParams($order['params']);
            }

            $this->data = $order;
            $this->data += shopHelper::workupOrders($order, true);
        } else {
            throw new waException('Empty order id', 404);
        }
    }

    private function sortDataFields($a, $b)
    {
        $sort = array(
            'id',
            'params',
            'is_first',
            'currency',
            'rate',
            'items',
            'customer',
            'customer_id',
            'discount_description',
            'discount',
            'tax',
        );
        $sort = array_flip($sort);

        $a = ifset($sort, $a, 42);
        $b = ifset($sort, $b, 42);

        return max(-1, min(1, $a - $b));
    }

    /**
     * @param $key
     * @return shopOrderStorageInterface|waModel
     * @throws waException
     */
    private function getStorage($key)
    {
        if (isset(self::$data_storages[$key])) {
            $storage = self::$data_storages[$key];
            if ($storage === true) {
                $storage = "shopOrder".ucfirst($key)."Model";
                $obj = new $storage();
                if (!($obj instanceof shopOrderStorageInterface)) {
                    throw new waException($storage.' must implement shopOrderStorageInterface');
                }
                return self::$data_storages[$key] = $obj;
            } elseif (is_string($storage)) {
                return self::$data_storages[$key] = new $storage();
            } elseif (is_object(self::$data_storages[$key])) {
                return self::$data_storages[$key];
            }
        }
        return null;
    }

    /**
     * Returns order property value.
     *
     * @param string|null $name Value name. If not specified, all properties' values are returned.
     * @return mixed
     */
    public function getData($name = null)
    {
        if ($name) {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        } else {
            return $this->data;
        }
    }

    /**
     * Executed on attempts to change order property values.
     * @see http://www.php.net/manual/en/language.oop5.overloading.php
     *
     * @param string $name Property name
     * @param mixed $value New value
     * @return mixed New value
     */
    public function __set($name, $value)
    {
        return $this->setData($name, $value);
    }

    /**
     * Executed on attempts to retrieve order property values.
     * @see http://www.php.net/manual/en/language.oop5.overloading.php
     *
     * @param string $name Property name
     * @return mixed|null Property value or null on failure
     */
    public function __get($name)
    {
        $result = null;
        if (isset(self::$aliases[$name])) {
            $result = $this->{self::$aliases[$name]};
        } elseif (isset($this->data[$name])) {
            $result = $this->data[$name];
        } else {

            if (method_exists($this, $method = self::camelMethod("read%s", $name))) {
                return $this->$method();
            } elseif (method_exists($this, $method = self::camelMethod("get%s", $name))) {
                $this->data[$name] = $this->$method();
                $result = $this->data[$name];
            } elseif (($storage = $this->getStorage($name))) {
                $this->original_data[$name] = $storage->getData($this);

                $format_method = self::camelMethod('format%s', $name);
                $this->data[$name] = $this->original_data[$name];
                if (method_exists($this, $format_method)) {
                    $result = $this->{$format_method}($this->data[$name]);
                } else {
                    $result = $this->data[$name];
                }
            }
        }
        if ($result && $this->options($name, 'escape')) {
            $result = $this->escape($result, $name);
        }
        return $result;
    }

    /**
     * Changes order property values without saving them to database.
     *
     * @param string $name Property name
     * @param mixed $value New value
     * @return mixed New value
     */
    public function setData($name, $value)
    {
        if (!in_array($name, $this->readOnlyFields())) {
            $parse_method = self::camelMethod('parse%s', $name);

            $is_changed = null;

            /**
             * @uses shopOrder::parseBillingAddress()
             * @uses shopOrder::parseContact()
             * @uses shopOrder::parseCustomer()
             * @uses shopOrder::parseDiscount()
             * @uses shopOrder::parseItems()
             * @uses shopOrder::parseParams()
             * @uses shopOrder::parsePaymentParams()
             * @uses shopOrder::parseShipping()
             * @uses shopOrder::parseShippingParams()
             * @uses shopOrder::parseShippingAddress()
             * @uses shopOrder::parseTax()
             *
             */
            if (method_exists($this, $parse_method)) {
                $this->data[$name] = $this->{$parse_method}($value);

                /**
                 * @uses shopOrder::compareDiscount()
                 * @uses shopOrder::compareItems()
                 * @uses shopOrder::compareParams()
                 * @uses shopOrder::compareShipping()
                 * @uses shopOrder::compareTax()
                 */
                $compare_method = self::camelMethod('compare%s', $name);
                if (method_exists($this, $compare_method)) {
                    $is_changed = $this->{$compare_method}($value);
                } else {
                    $is_changed = true;
                }

            } elseif ($this->getData($name) !== $value) {
                $this->data[$name] = $value;
                $is_changed = true;
            }

            if ($is_changed !== null) {
                $this->is_changed[$name] = $is_changed;
            }

            if (!empty($this->is_changed[$name])) {
                /**
                 * @uses shopOrder::dependenciesDiscount()
                 * @uses shopOrder::dependenciesParams()
                 */
                $dependencies_method = self::camelMethod('dependencies%s', $name);
                if (method_exists($this, $dependencies_method)) {
                    $this->{$dependencies_method}($value);
                } else {
                    $this->handleDependencies($name);
                }
            }
        } else {
            $value = $this->getData($name);
        }
        return $value;
    }

    protected function handleDependencies($name)
    {
        if (isset(self::$dependencies[$name])) {
            foreach (self::$dependencies[$name] as $field => $strict) {
                if (is_array($strict)) {
                    if (isset($this->data[$field])) {
                        $fields = $strict;
                        unset($strict);
                        foreach ($fields as $subfield => $sub_strict) {
                            if ($sub_strict || !empty($this->is_changed[$field])) {
                                unset($this->data[$field][$subfield]);
                            }
                        }
                    }
                } elseif ($strict) {
                    # The field dependency is strict
                    unset($this->data[$field]);
                    unset($this->is_changed[$field]);
                } elseif (!isset($this->is_changed[$field]) || in_array($this->is_changed, array(true, false), true)) {
                    # The field (re)calculate automatically
                    # if it was calculate by request via getMethod (field not is set at is_changed array)
                    # or if was changed without extra logic (`manual`,`hold` and etc) detected via compare%Field% method
                    unset($this->data[$field]);
                }
            }
        }
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset an offset to check for.
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset])
            || $this->model->fieldExists($offset)
            || $this->getStorage($offset)
            || method_exists($this, $method = self::camelMethod("get%s", $offset))
            || method_exists($this, $method = self::camelMethod("read%s", $offset));
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__set($offset, null);
    }

    /**
     * @return array
     */
    public function dataArray()
    {
        $this->contact;
        $this->params;
        $this->items;
        $this->style;
        $this->icon;
        $this->discount;
        $this->shipping;
        $this->state;

        return $this->data;
    }

    ###############################
    # Common section
    ###############################

    public function options($scope = null, $name = null)
    {
        if ($scope) {
            if ($name) {
                $_name = sprintf('%s_%s', $scope, $name);
                if (isset($this->options[$_name])) {
                    $options = $this->options[$_name];
                } elseif (isset($this->options[$name])) {
                    $options = $this->options(null, $name);
                } else {
                    $options = null;
                }
            } else {
                $options = array();
                $pattern = sprintf('@^%s_(.+)$@', preg_quote($scope, '@'));
                foreach ($this->options as $name => $value) {
                    if (preg_match($pattern, $name, $matches)) {
                        $options[$matches[1]] = $value;
                    }
                }
            }
        } elseif ($name) {
            if (isset($this->options[$name])) {
                $options = $this->options[$name];
            } else {
                $options = null;
            }
        } else {
            $options = $this->options;
        }

        return $options;
    }

    /**
     * Returns order id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->getData('id');
    }

    /** @return string ISO3 code */
    protected function getCurrency()
    {
        if (!empty($this->original_data['currency'])) {
            return $this->original_data['currency'];
        } elseif (!isset($this->data['currency'])) {
            return $this->config->getCurrency();
        } else {
            return $this->data['currency'];
        }
    }

    protected function getRate($currency = null)
    {
        $rate = 1;
        if ($this->id) {
            $rate = $this->original_data['rate'];
        } else {
            if (!$currency) {
                $currency = $this->getCurrency();
            }
            if ($currency) {
                $rate = $this->currency_model->getRate($currency);
            }
        }
        return $rate;
    }

    protected function getSubtotal()
    {
        $subtotal = 0.0;

        foreach ($this->items as $i) {
            $subtotal += floatval($i['price']) * intval($i['quantity']);
        }

        return $subtotal;
    }

    /**
     * @return float|int|mixed
     */
    protected function getTotal()
    {
        $total = $this->getSubtotal();

        $this->data['discount'] = $this->castPrice($this->discount, $this->currency);
        $this->data['shipping'] = $this->castPrice($this->shipping, $this->currency);

        $total = $total - $this->data['discount'] + $this->data['shipping'];
        $total += $this->getNotIncludedTax();
        return max(0, $total);
    }

    protected function getPrintforms()
    {
        $this->items;
        $this->params;
        $order = $this->data + $this->original_data;
        return shopPrintforms::getOrderPrintforms($order);
    }

    protected function readLastActionDatetime()
    {
        foreach ($this->log as $l) {
            if ($this['state_id'] == $l['after_state_id']) {
                return $l['datetime'];
            }
        }
    }

    protected function getItemsProductCodes()
    {
        $order_item_codes_model = new shopOrderItemCodesModel();
        return $order_item_codes_model->extendOrderItems($this['items']);
    }

    ###############################
    # Payment & billing section
    ###############################

    /** @return waPayment */
    protected function getPaymentPlugin()
    {
        $plugin = null;
        $params = $this->params;
        if (!empty($params['payment_id'])) {
            try {
                $plugin = shopPayment::getPlugin(null, $params['payment_id']);
            } catch (waException $ex) {
                $this->errorLog($ex->getMessage());
            }
        }
        return $plugin;
    }

    protected function getPaymentName()
    {
        $name = null;
        $params = $this->params;
        if (isset($params['shipping_name'])) {
            $name = $params['shipping_name'];
        } elseif (!empty($params['shipping_id'])) {
            if ($info = $this->pluginInfo($params['shipping_id'], shopPluginModel::TYPE_SHIPPING)) {
                $name = $info['name'];
                $this->data['params']['shipping_name'] = $name;
            }

        }
        return $name;
    }

    /**
     * Change items of this order according to refund or capture operation (possibly partial refund or partial capture).
     * Changes $this->items, and may change $this->discount.
     *
     * Does not save the order. Have to call $this->save() afterwards.
     *
     * $change_items can be `true` which means "everything", or an array [item_id => [ quantity => int ]].
     *
     * There are two possible operations:
     * $mode == waPayment::OPERATION_REFUND means "remove $change_items from this order"
     * $mode == waPayment::OPERATION_CAPTURE means "remove from this order everything except $change_items"
     *
     * @param array|bool $change_items
     * @param string     $mode         waPayment::OPERATION_REFUND or waPayment::OPERATION_CAPTURE
     * @param array      $transaction_data
     * @param bool       $capture_shipping_cost
     * @return array     items removed from the order
     * @throws waException
     */
    public function edit($change_items, $mode = waPayment::OPERATION_REFUND, $transaction_data = null, $capture_shipping_cost = true)
    {
        // items removed from the order - return value of this method
        // will be saved in order params if partial operation
        $refunded_items = array();

        // items to keep in order
        // will be set back into $this->items below if partial operation
        $items = $this->items;

        foreach ($items as $item_id => &$item) {

            $item['quantity'] = intval($item['quantity']);
            if (!$item['quantity']) {
                continue;
            }

            if (!empty($item['parent_id'])) {
                $item_id = $item['parent_id'];
            } else {
                $item_id = $item['id'];
            }

            // $quantity is how many items are removed from the order, refunded (or unpaid)
            // ($item['quantity'] - $quantity) is how many are left in order, paid
            $quantity = 0;
            if ($change_items === true) {
                $quantity = $item['quantity'];
            } elseif (is_array($change_items) && isset($change_items[$item_id]['quantity'])) {
                $quantity = max(0, $change_items[$item_id]['quantity']);
                switch ($mode) {
                    case waPayment::OPERATION_CAPTURE:
                        $quantity = intval(max(0, $item['quantity'] - $quantity));
                        break;
                    case waPayment::OPERATION_REFUND:
                        $quantity = intval(max(0, min($quantity, $item['quantity'])));
                        break;
                    default:
                        throw new waException('Unknown edit payment mode');
                }
            }

            if ($quantity) {
                $item['discount'] = $item['total_discount'] / $item['quantity'];
                $item['quantity'] -= $quantity;
                $item['total_discount'] = $item['discount'] * $item['quantity'];

                $refunded_items[$item['id']] = [
                    'quantity' => $quantity,
                    'total_discount' => $item['discount'] * $quantity,
                ] + $item;
            }

            unset($item);
        }

        // Is it partial operation?
        $is_delivery_cost_removed = false;
        if ($change_items !== true) {

            $shipping = $this->shipping;
            $discount = $this->discount;
            $this->items = $items;

            // This assignment also triggers distribution of discount among items,
            // if any were left undistributed (which it should not have)
            $refunded_discount = array_sum(array_column($refunded_items, 'total_discount'));
            $this->discount = max(0, $discount - max(0, $refunded_discount));

            if (empty($capture_shipping_cost) && !empty($shipping)) {
                $is_delivery_cost_removed = true;
            }
            $this->shipping = $capture_shipping_cost ? $shipping : 0;
        }

        // Make sure params are loaded, then remember what we are returning to stock.
        // 'refund_items' param is written to order log by workflow edit action,
        // then rendered via template at view time.
        $this->params;
        $this->data['params']['refund_items'] = $refunded_items;

        $this->data['params']['is_delivery_cost_removed'] = $is_delivery_cost_removed;

        if ($transaction_data && $this->payment_plugin) {
            $payment_options = array(
                'currency'       => $this->payment_plugin->allowedCurrency(),
                'order_currency' => $this->currency,
            );
            foreach ($refunded_items as $id => $item) {
                if (empty($item['quantity'])) {
                    unset($refunded_items[$id]);
                }
            }
            $refunded_items = shopHelper::workupOrderItems($refunded_items, $payment_options);
        }

        return $refunded_items;
    }

    protected function getBillingAddress()
    {
        $billing_address = shopHelper::getOrderAddress($this->params, 'billing');
        if (!$billing_address) {
            $address = $this->contact->getFirst('address.billing');
            if (!$address) {
                $address = $this->contact->getFirst('address');
            }

            $billing_address = ifset($address, 0, ifset($address, 'data', null));
        }
        return $billing_address;
    }

    protected function getBillingAddressObject()
    {
        $settings = $this->config->getCheckoutSettings();
        $form_fields = ifset($settings['contactinfo']['fields'], array());
        if (isset($form_fields['address.billing'])) {
            $formatter = new waContactAddressSeveralLinesFormatter();
            $params = $this->params;
            $billing_address = shopHelper::getOrderAddress($params, 'billing');
            $billing_address = $formatter->format(array('data' => $billing_address));
            $billing_address = $billing_address['value'];
        } else {
            $billing_address = null;
        }
        return $billing_address;
    }

    /**
     * $this['amount_on_hold']
     */
    protected function getAmountOnHold()
    {
        if (!$this->auth_date || $this->paid_date) {
            return 0;
        }
        $plugin = $this->payment_plugin;
        if (!$plugin) {
            return 0;
        }
        $transactions = $plugin->getAvailableTransactions($this['id']);
        return ifset($transactions, waPayment::TRANSACTION_CAPTURE, 'amount', 0);
    }

    ###############################
    # Workflow section
    ###############################

    /** @return waWorkflowState */
    protected function readState()
    {
        return $this->workflow->getStateById($this['state_id']);
    }

    /**
     * @return shopWorkflowAction[]
     */
    protected function readActions()
    {
        return $this->state->getActions($this);
    }

    /**
     * @param $action_id
     * @param $params
     * @return mixed
     * @throws waException
     */
    public function runAction($action_id, $params)
    {
        $data = array();
        if ($this->id) {
            $data['id'] = $this->id;
        }

        $workflow = new shopWorkflow();
        $result = $workflow->getActionById($action_id)->run($data + $params);

        return $result;
    }

    public function log($text)
    {
        $log = array(
            'order_id'        => $this->id,
            'contact_id'      => wa()->getUser()->getId(),
            'before_state_id' => $this->state_id,
            'after_state_id'  => $this->state_id,
            'text'            => $text,
            'action_id'       => '',
        );
        $order_log_model = new shopOrderLogModel();
        $order_log_model->add($log);
    }

    public function readWorkflowActionElements()
    {
        $bottom_buttons = $top_buttons = $actions_html = $buttons = array();

        $source = 'backend';
        if (isset($this['params']['storefront'])) {
            if (substr($this['params']['storefront'], -1) === '/') {
                $source = $this['params']['storefront'].'*';
            } else {
                $source = $this['params']['storefront'].'/*';
            }
        }
        $notification_model = new shopNotificationModel();
        $transports = $notification_model->getActionTransportsBySource($source);

        foreach ($this->actions as $action) {
            /**
             * @var shopWorkflowAction $action
             */
            if ($action->getOption('top') || $action->getOption('position') == 'top') {
                $top_buttons[] = $action->getButton();
            } elseif ($action->getOption('position') == 'bottom') {
                $bottom_buttons[] = $action->getButton();
            } elseif ($action->getOption('head')) {
                $html = $action->getHTML($this['id']);
                if ($html) {
                    $actions_html[] = $html;
                } else {
                    $buttons[] = $action->getButton();
                }
            } else {
                $icons = array();
                if (!empty($transports[$action->getId()]['email'])) {
                    $icons[] = 'ss notification-bw';
                }
                if (!empty($transports[$action->getId()]['sms'])) {
                    $icons[] = 'ss phone-bw';
                }
                if ($icons) {
                    $action->setOption('icon', $icons);
                }
                $buttons[] = $action->getButton();
            }
        }

        return compact('bottom_buttons', 'top_buttons', 'actions_html', 'buttons');
    }

    ###############################
    # Save & Validate section
    ###############################

    /**
     * Validate internal data before save if not validated already.
     * @return array of errors
     * @throws waException
     */
    public function errors()
    {
        if ($this->errors === null) {
            $this->validate();
        }
        return $this->errors;
    }

    /**
     * @param string $text will be written to order log as comment
     * @return shopOrder
     * @throws waException
     */
    public function save($text = null)
    {
        $this->options['mode'] = 'write';
        $this->validate();
        if ($this->errors) {
            throw new waException('Order validation error');
        }

        $this->prepareParams();

        // Save customer
        $this->saveCustomer();

        $data = $this->dataArray();

        if (!$this->id) {
            $data['skip_description'] = true;
            $action_id = 'create';

            // In case we didn't force stock_id during validation,
            // let workflow.create determine them
            if ($this->options(null, 'ignore_stock_validate')) {
                foreach ($data['items'] as &$item) {
                    unset($item['stock_id']);
                }
                unset($item);
            }

        } else {
            $action_id = 'edit';
            if ($this->options['return_stock'] !== null) {
                $data['params']['return_stock'] = $this->options['return_stock'];
            }

            if ($this->getAmountOnHold() > 0) {
                $data['params']['auth_edit'] = date('Y-m-d H:i:s');
            }
        }
        if ($text !== null) {
            $data['text'] = $text;
        }

        // If discount distribution strategy is set up to split order items into two,
        // apply the strategy here.
        $data = $this->distributeDiscountSplitItem($data);

        //Save order
        $result = $this->runAction($action_id, $data);
        if ($action_id == 'create') {
            $this->data['id'] = $result;
        }

        // Load nice and clean order data from DB
        $order = new self($this->id, $this->options);

        $this->saveDiscountDescription($order);

        return $order;
    }

    /**
     * Force validation of internal data before save.
     * @return array of errors
     * @throws waException
     */
    public function validate()
    {
        $this->errors = array();

        // not zero numeric - edit existing contact
        // zero numeric     - add contact
        $customer_id = $this->contact->getId();
        if (($customer_id === null) && !$this->id) {
            $customer_id = 0;
        }

        // Validation for customer form
        if ($customer_id !== null) {

            $contact = new waContact($customer_id);
            $form = $this->customerForm();

            $customer_validation_disabled = wa()->getSetting('disable_backend_customer_form_validation', '', 'shop');
            if (!$customer_validation_disabled) {
                if (!$form->isValid($contact)) {
                    $this->errors['customer']['html'] = $form->html();
                }
            }
        }

        if (isset($this->data['items'])) {
            if (empty($this->data['items'])) {
                $this->errors['order']['common'] = _w('Please add at least one product to save this order');
            } else {
                foreach ($this->data['items'] as $item) {
                    $fields = array();
                    switch ($item['type']) {
                        case 'service':
                            if (empty($item['service_id'])) {
                                $fields[] = 'service_id';
                            }
                            if (empty($item['service_variant_id'])) {
                                $fields[] = 'service_variant_id';
                            }
                            if (empty($item['product_id'])) {
                                $fields[] = 'product_id';
                            }
                            if (empty($item['sku_id'])) {
                                $fields[] = 'sku_id';
                            }
                            break;
                        case 'product':
                            if (empty($item['product_id'])) {
                                $fields[] = 'product_id';
                            }
                            if (empty($item['sku_id'])) {
                                $fields[] = 'sku_id';
                            }
                            break;
                    }
                    if ($fields) {
                        $key = ifset($item['_index'], ifset($item['id']));
                        $this->errors['order']['items'][$key] = sprintf('Missed required item fields: `%s`', implode($fields, '`, `'));
                    }

                }
            }
        }

        if (($this->subtotal + $this->shipping) < $this->discount) {
            $this->errors['order']['discount'] = _w('Discount is greater than order total.');
        }

        // Additional validation when money are on hold
        $amount_on_hold = $this->getAmountOnHold();
        if ($amount_on_hold > 0) {
            $order_mode = shopOrderMode::getMode($this);
            if ($order_mode['mode'] == shopOrderMode::MODE_TOTAL_LIMITED) {
                if ($this['total'] > $amount_on_hold) {
                    $this->errors['order']['common'] = sprintf_wp(
                        'Order total may not exceed the captured amount of %s.',
                        shop_currency($amount_on_hold, $this->currency, $this->currency)
                    );
                }
            } elseif ($order_mode['mode'] == shopOrderMode::MODE_TOTAL_FIXED) {
                if ($this['total'] != $amount_on_hold) {
                    $this->errors['order']['common'] = sprintf_wp(
                        'Order total must remain equal to the currently captured amount of %s.',
                        shop_currency($amount_on_hold, $this->currency, $this->currency)
                    );
                }
            }
        }

        if (!$this->errors && !$this->options(null, 'ignore_stock_validate')) {
            // Check stocks. This does not happen in frontend checkout because stocks are assigned by WorkflowCreate.
            $this->validateStockSelection();

            if (!wa('shop')->getSetting('ignore_stock_count') && !$this->options(null, 'ignore_count_validate')) {
                $this->validateStockExceed();
            }
        }

        if (!$this->calculated_discounts) {
            $this->calculateDiscount();
        }

        if (!empty($this->calculated_discounts['discount_decreased_by'])) {
            if (wa()->getEnv() == 'frontend') {
                $this->errors['order']['common'] = _w('Unable to create an order with the specified discount. Please contact the store’s support team.');
            } else {
                $this->errors['order']['common'] = _w('Unable to create an order with the specified discount. Please either disable the rounding for discounts or change the currency rounding settings so that the discount becomes lower than the ordered item’s price, or enable splitting of order items in currencies settings.');
            }
        }

        return $this->errors;
    }

    private function getStockUsage($items, &$sku_ids)
    {
        $usage = array();
        foreach ($items as $i) {
            switch ($i['type']) {
                case 'product':
                    $sku_id = intval($i['sku_id']);
                    $stock_id = intval($i['stock_id']);
                    if (!isset($usage[$sku_id][$stock_id])) {
                        $usage[$sku_id][$stock_id] = 0;
                    }
                    $usage[$sku_id][$stock_id] += $i['quantity'];
                    $sku_ids[$sku_id] = $sku_id;
                    break;
            }
        }
        return $usage;
    }

    /**
     * @todo complete & test validation code
     */
    private function validateStockExceed()
    {
        $this->product_skus_model = new shopProductSkusModel();

        $sku_ids = array();

        // calc current quantity usage
        $usage = $this->getStockUsage($this->data['items'], $sku_ids);

        // calc old quantity usage of this order (if order is new, than array will be empty)
        $old_usage = $this->getStockUsage($this->initOriginalItems(), $sku_ids);

        // calc stock counts
        $sku_stocks = $this->stocks();

        $skus = $this->product_skus_model->getByField('id', $sku_ids, 'id');
        $counts = array();

        foreach ($sku_stocks as $sku_id => &$stock) {
            if (!is_array($stock)) {
                $counts[$sku_id][0] = $stock;
            } else {
                foreach ($stock as $stock_id => $st) {
                    $counts[$sku_id][$stock_id] = $st['count'];
                }
            }
        }

        // summarize stock counts with old usage as if temporary return items to stocks
        foreach ($old_usage as $sku_id => $ou) {
            if (!isset($counts[$sku_id])) {
                continue;
            }
            if (!is_array($counts[$sku_id])) {
                $cnt = array_sum((array)$ou);
                if ($counts[$sku_id] !== null) {
                    $counts[$sku_id] += $cnt;
                }
            } else {
                if (is_array($ou)) {
                    foreach ($ou as $stock_id => $cnt) {
                        if (isset($counts[$sku_id][$stock_id])) {
                            $counts[$sku_id][$stock_id] += $cnt;
                        }
                    }
                } else {
                    $stock_ids = array_keys($counts[$sku_id]);
                    $first_stock_id = reset($stock_ids);
                    $counts[$sku_id][$first_stock_id] += $ou;
                }
            }
        }

        // AND NOW check CURRENT USAGE does not exceed COUNT in stocks
        $error_sku_id = array();
        foreach ($usage as $sku_id => $u) {
            if (!isset($counts[$sku_id])) {
                continue;
            }
            if (is_array($u)) {
                foreach ($u as $stock_id => $cnt) {
                    if (isset($old_usage[$sku_id][$stock_id]) && $old_usage[$sku_id][$stock_id] == $cnt) {
                        continue;
                    }
                    if (isset($counts[$sku_id][$stock_id]) && $cnt > $counts[$sku_id][$stock_id]) {
                        $error_sku_id[] = $sku_id;
                        break 2;
                    }
                }
            } else {
                if ($counts[$sku_id] !== null && $u > $counts[$sku_id]) {
                    $error_sku_id[] = $sku_id;
                    break;
                }
            }
        }

        // Error for some sku
        if ($error_sku_id) {
            $product_id_by_sku_id = waUtils::getFieldValues($this->data['items'], 'product_id', 'sku_id');
            $message = _w('The number of items your can add to the order is limited by the stock level');
            foreach ($error_sku_id as $sku_id) {
                $product_id = ifset($product_id_by_sku_id, $sku_id, '');
                $this->errors['order']['product'][$product_id]['sku_id'] = $sku_id;
                $this->errors['order']['product'][$product_id]['quantity'] = $message;
            }
        }
    }

    /**
     * @return bool
     */
    private function validateStockSelection()
    {
        $sku_stocks = $this->stocks();

        $errors = array();
        foreach ($this->items as $index => $item) {
            switch ($item['type']) {
                case 'product':
                    $sku_id = ifset($item['sku_id']);
                    $stock_id = ifempty($item['stock_id']);

                    if (empty($stock_id) && is_array($sku_stocks[$sku_id]) && !empty($sku_stocks[$sku_id])) {
                        # Stock not selected
                        $errors[$index]['stock_id'] = _w('Select stock');// *not selected
                    } elseif ($stock_id !== null) {
                        if ($stock_id) {
                            if (empty($sku_stocks[$sku_id]) || empty($sku_stocks[$sku_id][$stock_id])) {
                                # Stock was deleted
                                $errors[$index]['stock_id'] = _w('Select stock');// *deleted
                            }
                        } else {
                            if (!empty($sku_stocks[$sku_id])) {
                                $errors[$index]['stock_id'] = _w('Select stock');// *not selected common stock'
                            }
                        }
                    }
                    break;
            }
        }
        if (!empty($errors)) {
            $this->errors['order']['items'] = $errors;
        }

        return empty($this->errors);
    }

    private function stocks($skus = null)
    {
        $product_stocks_model = new shopProductStocksModel();
        $this->product_skus_model = new shopProductSkusModel();

        if ($skus === null) {
            $ids = $this->collectItems();
            $skus = $ids['sku'];
        }

        $stocks_count = array_fill_keys($skus, 0);
        $products_count = $this->product_skus_model->getByField('id', $skus, 'id');
        $skus_counts = $product_stocks_model->getBySkuId($skus);

        //If the product has no stocks, put a value from shopProduct
        foreach ($products_count as $sku_id => $info) {
            if (isset($skus_counts[$sku_id])) {
                $stocks_count[$sku_id] = $skus_counts[$sku_id];
            } else {
                $stocks_count[$sku_id] = $info['count'];
            }
        }

        return $stocks_count;
    }

    ###############################
    # Contact section
    ###############################

    /** @return waContact */
    protected function getContact()
    {
        $contact_id = $this->contact_id;
        return new waContact($contact_id ? $contact_id : null);
    }

    protected function getContactEssentials()
    {
        $order_model = new shopOrderModel();
        return $order_model->getOrderContactData($this->data + $this->original_data);
    }

    protected function getShopCustomer()
    {
        $customer_model = new shopCustomerModel();
        return $customer_model->getById($this->contact_id);
    }

    /**
     * @return shopContactForm|waContactForm|shopBackendCustomerForm
     * @throws waException
     */
    public function customerForm()
    {
        $form = ifset($this->options['customer_form']);
        if ($form instanceof shopBackendCustomerForm) {
            if ($this->contact_id > 0) {
                $form->setContact($this->contact);
            } else {
                $namespace = $form->getNamespace();
                if (isset($this->data[$namespace]['contact_type'])) {
                    $form->setContactType($this->data[$namespace]['contact_type']);
                }
            }

            $storefront = isset($this->data['params']['storefront']) ? $this->data['params']['storefront'] : null;
            $form->setStorefront($storefront);

            $this->options['customer_is_company'] = $form->getContactType() == shopCustomer::TYPE_COMPANY;

            return $form;
        }
        return $this->getDefaultCustomerForm();
    }

    /**
     * Return some default customer form
     * @return shopContactForm|waContactForm|null
     * @throws waException
     */
    protected function getDefaultCustomerForm()
    {
        $form = null;
        if ($this->id) { #Existing order
            $client_contact_id = null;
            if ($this->contact_id) {

                try {
                    $c = $this->contact;
                    if ($this->shipping_address) {
                        $c = clone $this->contact;
                        $c['address.shipping'] = $this->shipping_address;
                    }
                    $form = shopHelper::getCustomerForm($c);
                } catch (waException $e) {
                    // Contact does not exist; ignore. When $form is null, customer data saved in order is shown.
                }
            }
        } else { #NEW ORDER

            // This option to pass customer fields is used by new SS8 checkout.
            if (!empty($this->options['customer_form_fields'])) {
                $config_fields = array_fill_keys($this->options['customer_form_fields'], 1);
                $config_fields['address'] = 1;

                $fields = waContactFields::getAll('enabled');
                $fields = array_intersect_key($fields, $config_fields);
                $fields['address.shipping'] = $fields['address'];
                unset($fields['address']);

                return waContactForm::loadConfig($fields, ['namespace' => 'customer']);
            }

            if ($this->contact_id) {
                try {
                    $c = $this->contact;
                    if ($this->shipping_address) {
                        $c = clone $this->contact;
                        $c['address.shipping'] = $this->shipping_address;
                    }
                    $form = shopHelper::getCustomerForm($c);
                } catch (waException $e) {
                    // Contact does not exist
                    $this->data['contact_id'] = null;
                }
            }
            if (!$this->contact_id) {
                $form = shopHelper::getCustomerForm();
            }
        }

        return $form;
    }

    protected function parseShippingAddress($address)
    {
        if (isset($address[0])) {
            $address = $address[0];
        }

        foreach ($address as $k => $v) {
            $this->data['params']['shipping_address.'.$k] = $v;
        }
        return $address;
    }

    protected function parseBillingAddress($address)
    {
        if (isset($address[0])) {
            $address = $address[0];
        }

        foreach ($address as $k => $v) {
            $this->data['params']['billing_address.'.$k] = $v;
        }
        return $address;
    }

    protected function parsePaymentParams($data)
    {
        $parsed = array();
        if ($data) {
            foreach ($data as $k => $v) {
                if ($k && $k[0] !== '_') {
                    $parsed['payment_params_'.$k] = $v;
                    $this->data['params']['payment_params_'.$k] = $v;
                }
            }
        }
        return $parsed;
    }

    protected function parseContact($contact)
    {
        if (!empty($contact) && ($contact instanceof waContact)) {
            // Make sure all old address data is removed
            if ($this->id) {
                foreach ($this->original_data['params'] as $k => $v) {
                    if (preg_match('~^(billing|shipping)_address\.~', $k)) {
                        $this->data['params'][$k] = null;
                    }
                }
            }
            $address = $contact->getFirst('address.shipping');
            if (!$address) {
                $address = $contact->getFirst('address');
            }
            if ($address) {
                if ($address = ifset($address, 0, ifset($address, 'data', null))) {
                    $this->shipping_address = $address;
                }
            }


        } else {
            $contact = null;
        }

        return $contact;
    }

    protected function parseCustomer($data, &$form = null)
    {
        // Used hardcoded value `customer` but actual value can be differ `$form->opt('namespace')`
        // @see shopHelper::getCustomerForm
        if (!$form) {
            $form = $this->customerForm();
            if (!$form) {
                return array();
            }
        }
        if (($form->post === null) && $data) {
            $post = array();
            $fields = $form->fields();
            foreach ((array)$data as $f_id => $value) {
                if (isset($fields[$f_id])) {
                    $multiple = is_array($value) && array_filter(array_keys($value), 'is_int') && $f_id !== 'address.shipping';
                    // birthday data is not string and not array indexed by integer, birthday data presented as associative array with 3 keys: 'year', 'month', 'day'
                    if ($multiple || is_string($value) || $f_id === 'birthday') {
                        $post[$f_id] = $value;
                    } else {
                        $post[$f_id][0] = $value;
                    }
                }
            }

            // Need for shopBackendCustomerForm, to define with contact type we will deal with
            if (isset($data['contact_type'])) {
                $post['contact_type'] = $data['contact_type'];
            }

            if ($post) {
                $form->post = $post;
            }
        }
        if ($form->post === null || !is_array($form->post)) {
            return null;
        }

        //Set address. It is necessary to save the correct delivery parameters in a new order. Or if counted in the old.
        if (!empty($form->post['address.shipping'][0])) {
            $this->shipping_address = $form->post['address.shipping'][0];
        }

        if (!empty($form->post['address.billing'][0])) {
            $this->billing_address = $form->post['address.billing'][0];
        }

        return $form->post;
    }

    private function saveCustomer()
    {
        $form = $this->customerForm();
        if ($this->contact && $form && !empty($this->data['customer'])) {

            $this->parseCustomer($this->data['customer'], $form);
            foreach ((array)$form->post() as $fld_id => $fld_data) {
                //turn off checkbox
                if (!$fld_data && !($form->fields($fld_id) instanceof waContactCheckboxField)) {
                    continue;
                }

                if ($fld_id == 'address.shipping') {
                    $this->saveContactAddress($this->contact, 'shipping', $fld_data);
                    continue;
                } elseif ($fld_id == 'address.billing') {
                    $this->saveContactAddress($this->contact, 'billing', $fld_data);
                    continue;
                }

                // Option to never delete old values saved in contact even when
                // not present in data that came from form
                $add_values_as_new = false;
                if (!empty($this->options['customer_add_multifields'])) {
                    $contact_field = waContactFields::get($fld_id);
                    // Only makes sense for present multi-fields; can't do for composite fields
                    if ($contact_field && $contact_field->isMulti() && !($contact_field instanceof waContactCompositeField)) {
                        $old_values = $this->contact[$fld_id];
                        $add_values_as_new = true;
                    }
                }

                // Whether multiple values came from form
                $multiple = is_array($fld_data) && count(array_filter(array_keys($fld_data), 'is_int')) > 1;

                if ($multiple) {
                    $this->contact[$fld_id] = array();
                    foreach ($fld_data as $v) {
                        $this->contact->set($fld_id, $v, true);
                    }
                } else {
                    if (is_array($fld_data) && isset($fld_data[0])) {
                        $fld_data = $fld_data[0];
                    }
                    $this->contact[$fld_id] = $fld_data;
                }

                // Add back old values saved in contact and then filter out duplicates, if any
                if ($add_values_as_new) {
                    $this->contact[$fld_id] = $this->removeContactDataDuplicates(array_merge($this->contact[$fld_id], $old_values));
                    unset($old_values);
                }
            }

            $customer_validation_disabled = ifset($this->options, 'customer_validation_disabled', false);
            if (!$customer_validation_disabled && ifset($this->options, 'environment', wa()->getEnv()) == 'backend') {
                $customer_validation_disabled = wa()->getSetting('disable_backend_customer_form_validation', '', 'shop');
            }

            if (!$this->contact->getId() && !empty($this->options['customer_is_company'])) {
                $this->contact['is_company'] = 1;
            }

            if (!empty($customer_validation_disabled)) {
                $this->contact->save();
            } else {
                $customer_errors = $this->contact->save(array(), true);
                if ($customer_errors) {

                    // Only consider errors from visible fields
                    $customer_errors = array_intersect_key($customer_errors, $form->fields);

                    if ($customer_errors) {
                        $this->errors['customer'] = $customer_errors;
                    } else {
                        // No errors from visible fields: save anyway
                        $this->contact->save();
                    }
                }
            }
        }

        if ($this->errors) {
            throw new waException('Order validation error');
        }
    }

    protected function removeContactDataDuplicates($data)
    {
        $result = [];
        foreach ($data as $old_row) {
            if (!isset($old_row['value'])) {
                continue;
            }
            if (!isset($result[$old_row['value']])) {
                $result[$old_row['value']] = $old_row;
            } else {
                $new_row =& $result[$old_row['value']];
                // Use newer ext if set
                if (isset($old_row['ext']) && (!isset($new_row['ext']) || !strlen($new_row['ext']))) {
                    $new_row['ext'] = $old_row['ext'];
                }
                // Use confirmed status if anything has confirmed status; otherwise use newer one
                if (ifset($old_row, 'status', '') === 'confirmed' || !isset($new_row['status'])) {
                    $new_row['status'] = ifset($old_row, 'status', null);
                }
                unset($old_row);
            }

        }

        return array_values($result);
    }

    protected function saveContactAddress(waContact $contact, $ext, $new_address)
    {
        // Save address to order.
        // Save address to customer in case old address in order still matches customer's address.

        // Order address editor ignores all addresses except the first one.
        // This is paranoid check. There should be only one address, and no $new_address[0] key
        if (isset($new_address[0])) {
            return $this->saveContactAddress($contact, $ext, $new_address[0]);
        }

        if ($ext == 'shipping') {
            $this->shipping_address = $new_address;
        } elseif ($ext == 'billing') {
            $this->billing_address = $new_address;
        } else {
            throw new waException('Unknown address type '.$ext); // this cannot happen
        }

        // In case old address in order matches one of old customer's addresses,
        // we should update customer's address that matches.
        // Otherwise we add address as the new one (first in list)

        if ($this->id) {
            // This is address from original order data (before save, as in DB)
            $old_order_address = shopHelper::getOrderAddress($this->original_data['params'], $ext);
        } else {
            $old_order_address = $new_address;
        }

        // This is a list of all addresses saved in contact. [ i => array( data => array, ext => string ) ]
        $customer_addresses = $contact['address'];

        // This is a list of all addresses with ext matching $ext
        $old_customer_addresses_ext = array_filter($customer_addresses, wa_lambda('$a', 'return $a["ext"] == '.var_export($ext, 1).';'));

        // Look for $old_order_address in $old_customer_addresses_ext
        $match_index = $this->findAddressInList($old_order_address, $old_customer_addresses_ext);

        // In case old address is not present in contact, we try to find the new one there.
        // This avoids creating duplicates.
        if ($match_index === null) {
            $match_index = $this->findAddressInList($new_address, $old_customer_addresses_ext);
        }

        if ($match_index !== null) {
            // In case we found address in contact, we replace it
            $customer_addresses[$match_index] = array(
                'data' => $new_address,
                'ext'  => $ext,
            );
        } else {
            // ...otherwise we add it as a new one
            array_unshift($customer_addresses, array(
                'data' => $new_address,
                'ext'  => $ext,
            ));
        }
        $contact['address'] = $customer_addresses;
    }

    protected function findAddressInList($address, $list)
    {
        if (isset($address['data']) && (isset($address['ext']) || isset($address['value']))) {
            $address = $address['data'];
        }

        foreach ($list as $index => $old_address) {
            if (isset($old_address['data']) && (isset($old_address['ext']) || isset($old_address['value']))) {
                $old_address = $old_address['data'];
            }
            $match = true;
            foreach ($old_address as $k => $v) {
                // Coordinates in old addresses should not break the match
                if ($k === 'lat' || $k === 'lng') {
                    continue;
                }
                if (!array_key_exists($k, $address) || $v !== $address[$k]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $index;
            }
        }
        return null;
    }

    ###############################
    # Params section
    ###############################

    /** @return string */
    protected function getSource()
    {
        $source = 'backend';
        $params = $this->params;
        if (isset($params['storefront'])) {
            if (substr($params['storefront'], -1) === '/') {
                $source = $params['storefront'].'*';
            } else {
                $source = $params['storefront'].'/*';
            }
        }
        return $source;
    }

    /** @return string|null */
    protected function getStorefrontDecoded()
    {
        $params = $this->params;
        if (!empty($params['storefront'])) {
            $idna = new waIdna();
            return $idna->decode($params['storefront']);
        }
        return null;
    }

    /**
     * @param $params
     * @return mixed
     * @throws waException
     */
    protected function parseParams($params)
    {
        if (!isset($this->original_data['params'])) {
            if ($this->id) {
                $this->original_data['params'] = $this->getStorage('params')->getData($this);
            } else {
                $this->original_data['params'] = array();
            }
        }

        $original_params = &$this->original_data['params'];

        # sales channel & storefront
        if (!empty($params['storefront'])) {    // storefront passed and not empty
            $params['sales_channel'] = 'storefront:'.$params['storefront'];
        } elseif (!empty($original_params['storefront']) && !array_key_exists('storefront', $params)) { // 'storefront' not passed, so restore from original params of order
            $params['storefront'] = $original_params['storefront'];
            if (isset($original_params['sales_channel'])) {
                $params['sales_channel'] = $original_params['sales_channel'];
            } else {
                $params['sales_channel'] = 'storefront:'.$original_params['storefront'];
            }
        } else {
            $params['sales_channel'] = 'backend:';
            $params['storefront'] = null;
        }

        // shipping
        if (!empty($params['shipping_id'])) {
            $shipping_parts = explode('.', $params['shipping_id'], 2);
            $shipping_id = $shipping_parts[0];
            $rate_id = ifset($shipping_parts, 1, ifset($params, 'shipping_rate_id', ''));
            $params['shipping_id'] = $shipping_id;
            $params['shipping_rate_id'] = $rate_id;

            if (!empty($params['shipping_params'])) {
                foreach ($params['shipping_params'] as $key => $value) {
                    if (strpos('_', $key) === 0) {
                        unset($params['shipping_params'][$key]);
                    }
                }
            }
        } elseif (isset($params['shipping_id'])) {
            $this->resetShippingParams($params);
        }


        # payment params
        if (!empty($params['payment_id'])) {
            $params['payment_id'] = (int)$params['payment_id'];
        } elseif (isset($params['payment_id'])) {
            $params['payment_plugin'] = null;
            $params['payment_name'] = null;
            foreach ($original_params as $name => $value) {
                if (preg_match('@payment_params_@', $name)) {
                    unset($original_params[$name]);
                }
            }
        }

        // Coupon
        if (empty($params['coupon_id']) && !empty($params['coupon_code'])) {
            $coupon_model = new shopCouponModel();
            $coupon = $coupon_model->getByField('code', $params['coupon_code']);
            if ($coupon) {
                $params['coupon_id'] = $coupon['id'];
            }
        }

        $params += $original_params;

        #optimized params
        $params += array(
            'coupon_id' => 0,
        );

        unset($original_params);

        if (isset($this->data['shipping_params'])) {
            $params = $this->data['shipping_params'] + $params;
        }
        if (isset($this->data['payment_params'])) {
            $params = $this->data['payment_params'] + $params;
        }

        return $params;
    }

    private function prepareParams()
    {
        $params = &$this->data['params'];
        //shipping
        $plugin_params = array(
            'departure_datetime' => ifset($this, 'params', 'departure_datetime', 0),
        );
        $plugin_params['shipping_params'] = isset($params['shipping_params']) ? $params['shipping_params'] : array();

        $shipping_id = ifset($params, 'shipping_id', null);
        $rate_id = ifset($params, 'shipping_rate_id', null);
        $shipping_address = $this->shipping_address;
        $plugin = $this->shipping_plugin;
        if ($plugin) {
            $units = array(
                'weight'     => $plugin->allowedWeightUnit(),
                'dimensions' => $plugin->allowedLinearUnit(),
            );

            shopShipping::extendItems($this->data['items'], $units);

            $empty_address = $plugin->allowedAddress() === false;
            if (!$empty_address && $shipping_address) {
                foreach ($shipping_address as $k => $v) {
                    $params['shipping_address.'.$k] = $v;
                }
            }

            if ($this->billing_address) {
                foreach ($this->billing_address as $k => $v) {
                    $params['billing_address.'.$k] = $v;
                }
            }

            if (!empty($this->is_changed['items']) || !$this->id) {
                $total = shopShipping::getItemsTotal(ifempty($this->data, 'items', []));
                foreach ($total as $field => $value) {
                    $params['package_'.$field] = $value;
                }
            } else {
                $total = shopShipping::extractItemsTotal($params) + shopShipping::getItemsTotal($this->data['items']);
            }

            $shipping_items = $this->data['items'];
            shopShipping::convertItemsDimensions($shipping_items, $plugin);

            $plugin_params += shopShipping::convertTotalDimensions($total, $units);
            $rates = $plugin->getRates($shipping_items, $shipping_address, $plugin_params);


            $params['shipping_plugin'] = $plugin->getId();
            if (($plugin_info = $this->pluginInfo($shipping_id, shopPluginModel::TYPE_SHIPPING))) {
                $params['shipping_name'] = $plugin_info['name'];
                $params['shipping_tax_id'] = ifset($plugin_info['options']['tax_id']);
            } else {
                $params['shipping_name'] = sprintf('Shipping plugin #%d', $shipping_id);
                $error = "Missed required plugin info.\nDATA: %s";
                $debug_data = compact('shipping_id', 'rate_id');
                $this->errorLog($error, $debug_data);
            }

            if ($rates && is_array($rates)) {
                if ($rate_id === null) {
                    $rate = reset($rates);
                    $params['shipping_rate_id'] = key($rates);
                } elseif (!empty($rates[$rate_id])) {
                    $rate = $rates[$rate_id];
                } else {
                    $error = "Missed required rate.\nDATA: %s";
                    $debug_data = compact('shipping_id', 'rate_id', 'rates', 'plugin_info', 'shipping_address');
                    $this->errorLog($error, $debug_data);
                }

                if (!empty($rate['est_delivery'])) {
                    $params['shipping_est_delivery'] = $rate['est_delivery'];
                }

                if (!empty($rate['name'])) {
                    $params['shipping_name'] .= ' ('.$rate['name'].')';
                }
            } elseif ($rate_id !== null) {
                $error = "Missed required rate.\nDATA: %s";
                $debug_data = compact('shipping_id', 'rate_id', 'rates', 'plugin_info', 'shipping_address');
                $this->errorLog($error, $debug_data);
            }
        }

        // reset previous shipping params?
        if (!empty($params['shipping_params'])) {
            foreach ($params['shipping_params'] as $k => $v) {
                $params['shipping_params_'.$k] = $v;
            }
        }

        //change shipping plugin
        if ($this->id) {
            $original_shipping_id = ifempty($this->original_data, 'params', 'shipping_id', null);
            if ($original_shipping_id && $original_shipping_id != $params['shipping_id']) {
                $action = new shopWorkflowAction(null, $this->workflow);
                $action->setPackageState(waShipping::STATE_CANCELED, $this->id, array('log' => true));
                $this->resetShippingParams($params);
            }
        }

        // payment
        if (!empty($params['payment_id'])) {
            $params['payment_id'] = (int)$params['payment_id'];
            if ($plugin_info = $this->pluginInfo($params['payment_id'], shopPluginModel::TYPE_PAYMENT)) {
                $params['payment_plugin'] = $plugin_info['plugin'];
                $params['payment_name'] = $plugin_info['name'];

                if (isset($this->data['payment'])) {
                    $params += $this->data['payment'];
                }
            } else {
                $params['payment_plugin'] = null;
                $params['payment_name'] = null;
            }
        }
        return $params;
    }

    private function resetShippingParams(&$params)
    {
        $names = array();

        foreach ($this->original_data['params'] as $name => $value) {
            if (strpos($name, 'shipping_data_') === 0) {
                $names[] = $name;
            }
        }
        foreach (array('id', 'rate_id', 'plugin', 'name', 'est_delivery', 'params_') as $k) {
            if (!isset($params['shipping_'.$k])) {
                $names[] = 'shipping_'.$k;
            }
        }
        foreach ($names as $name) {

            if (!isset($params[$name])) {
                $params[$name] = null;
            }
        }
    }

    protected function compareParams($params = null)
    {
        $is_changed = false;
        if ($params === null) {
            $params = $this->data['params'];
        }
        $original_params = $this->original_data['params'];
        foreach ($params as $name => $value) {
            if (!isset($original_params[$name])) {
                $is_changed = true;
                break;
            } elseif ($original_params[$name] != $value) {
                $is_changed = true;
                break;
            } else {
                unset($original_params[$name]);
            }
        }
        if ($original_params) {
            $is_changed = true;
        }
        return $is_changed;
    }

    /**
     * Clear specific dependencies
     * @param null|array $params
     */
    protected function dependenciesParams($params = null)
    {
        $dependencies_map = [
            'shipping_id' => [
                'shipping'
            ]
        ];

        if ($params === null) {
            $params = $this->data['params'];
        }
        $original_params = $this->original_data['params'];

        foreach ($params as $name => $value) {
            $original_param = ifset($original_params, $name, false);

            if ($original_param !== $value && !empty($dependencies_map[$name])) {
                $dependencies = $dependencies_map[$name];

                if (is_array($dependencies)) {
                    foreach ($dependencies as $dependency) {
                        //Check if it is not set manually
                        if (ifset($this->is_changed, $dependency, null) !== 'manual') {
                            unset($this->data[$dependency]);
                        }

                    }
                } elseif (ifset($this->is_changed, $dependencies, null) == 'manual') {
                    unset($this->data[$dependencies]);
                }
            }
        }
    }


    ###############################
    # Discount section
    ###############################

    /**
     * Apply discount distribution strategy that requires splitting order item into two.
     * This is first part of the strategy. Called right after discount calculation.
     * This selects an item to be split later.
     * @param array $order result of discount calculation or distribution.
     */
    protected function distributeDiscountPrepareSplitItem(&$order)
    {
        // When distribution did not change overall discount, nothing to do here
        if (empty($order['discount_increased_by']) || empty($order['items'])) {
            return false;
        }
        // If disabled in settings, do not split any items; allow to change overall discount
        if (!wa('shop')->getSetting('discount_distrbution_split')) {
            // this option is used in unit tests
            if (empty($this->options['force_discount_distrbution_split'])) {
                return false;
            }
        }

        // Select the most expensive item. Sort items by price, desc.
        // Items sorted by discount batch, desc
        $order_items = array_map(function($item_index, $i) {
            return [
                'id' => $item_index,
                'discounted_price' => $i['price'] - $i['total_discount'] / ifempty($i, 'quantity', 1),
            ];
        }, array_keys($order['items']), $order['items']);
        array_multisort(array_column($order_items, 'discounted_price'), SORT_DESC, $order_items);

        // Add discount to order items, most expensive first. This makes at most one item
        // that will have its total_discount not divisible by quantity
        $discount_undistributed = $order['discount_increased_by'];
        foreach(array_column($order_items, 'id') as $item_index) {
            $item =& $order['items'][$item_index];
            if ($item['total_discount'] >= $discount_undistributed) {
                // All undistributed discount fits into current item. We're done.
                $item['total_discount'] -= $discount_undistributed;
                $discount_undistributed = 0;
                break;
            } else {
                // Fit in some undistributed discount, continue to next item.
                $discount_undistributed -= $item['total_discount'];
                $item['total_discount'] = 0;
            }
        }
        unset($item);

        // Pretend discount distributed normally
        if (isset($order['total'])) {
            $order['total'] += $order['discount_increased_by'] - $discount_undistributed;
        }
        if (isset($order['discount'])) {
            $order['discount'] -= $order['discount_increased_by'] - $discount_undistributed;
        }
        unset($order['discount_increased_by']);

        return true;
    }

    /**
     * Apply discount distribution strategy that requires splitting order item into two.
     * This is the second part. Called during order save logic, before passing data array to workflow actions.
     * Splits previously selected order item into two.
     *
     * Algorithm implemented in 'DiscountPrepareSplit' should produce exactly one item (product or service) that
     * has its total_discount not divisible by quantity. Still, algorithm here in 'DiscountPrepareSplit'
     * does not imply that there's only one such item. It will work with any input, possibly splitting more than once.
     * It will split all items where total_discount is not divisible by quantity, also respecting
     * product-service relationship. Hard stuff.
     *
     * @param array
     */
    protected function distributeDiscountSplitItem($data_array)
    {
        //
        // Each item with total_discount not divisible by quantity
        // (taking currency precision into account)
        // must be split into two items.
        //

        $currency_precision = 100;
        if (!empty($data_array['__currency_precision']) && $data_array['__currency_precision'] % 10 == 0) {
            // This is used in unit tests.
            $currency_precision = $data_array['__currency_precision'];
        } else if ( ( $info = waCurrency::getInfo($data_array['currency'])) && isset($info['precision'])) {
            $currency_precision = pow(10, max(0, $info['precision']));
        }

        // Group items by products and services. Each group consists of an item followed
        // by any number of services. Quantity of service items is determined by product.
        $item_groups = [];
        $current_group = [];
        foreach($data_array['items'] as $item) {
            if ($item['type'] == 'product') {
                if ($current_group) {
                    $item_groups[] = $current_group;
                }
                $current_group = [];
            } else {
                if (!$current_group) {
                    // Malformed order: service may not be the first in item list.
                    // This should never happen.
                    return $data_array;
                }
            }
            $current_group[] = $item;
        }
        if ($current_group) {
            $item_groups[] = $current_group;
        }

        if (!$item_groups) {
            // Malformed order: order may not be empty
            // This should never happen.
            return $data_array;
        }

        $result_items = [];

        // Loop over groups one by one.
        // For each group, determine if it should be split.
        // If split is not needed for the group, the whole group goes to $result_items.
        // If group splits, one part goes to $result_items, the other part back to $item_groups.
        while ($item_groups) {
            $current_group = array_shift($item_groups);
            $product_item = reset($current_group);
            $total_quantity = $product_item['quantity'];

            // Determine quantity of new group we split out from $current_group.
            if ($total_quantity <= 0) {
                $safe_quantity = 0;
            } else {
                $safe_quantity = min(array_map(function($item) use ($currency_precision, $total_quantity) {
                    $discount_cents = round($item['total_discount']*$currency_precision);
                    $remainder = $discount_cents % $total_quantity;
                    if ($remainder > 0) {
                        // If we take this remainder as the quantity of new group,
                        // we have at least one item less to worry about,
                        // because both parts of a group we split
                        // will have this item's total_discount divisible by its quantity.
                        return $remainder;
                    } else {
                        return $total_quantity;
                    }
                }, $current_group));
            }

            // If the group does not have to be split, add the whole thing to $result_items.
            if ($safe_quantity >= $total_quantity) {
                foreach($current_group as $item) {
                    $result_items[] = $item;
                }
                continue;
            }

            // Otherwise, split is required for $current_group.
            // Split the group, one part having quantity = $safe_quantity and being safe:
            // all items inside safe group have total_discount divisible by quantity.
            $safe_group = [];
            $unsafe_group = [];
            foreach($current_group as $item) {
                $total_discount_cents = round($item['total_discount']*$currency_precision);
                $remainder = $total_discount_cents % $total_quantity;

                $safe_item = $item;
                $safe_item['quantity'] = $safe_quantity;
                $safe_item_discount_cents = ($total_discount_cents - $remainder) / $total_quantity * $safe_quantity;
                if ($remainder == $safe_quantity || ($remainder > $safe_quantity && $remainder + $safe_quantity > $total_quantity)) {
                    $safe_item_discount_cents += $safe_quantity;
                }
                $safe_item['total_discount'] = $safe_item_discount_cents / $currency_precision;
                $safe_group[] = $safe_item;

                unset($item['id']);
                $item['quantity'] = $total_quantity - $safe_quantity;
                $item_discount_cents = $total_discount_cents - $safe_item_discount_cents;
                $item['total_discount'] = $item_discount_cents / $currency_precision;
                $unsafe_group[] = $item;
            }

            // Append one part (guaranteed to be safe) to $result_items.
            foreach($safe_group as $item) {
                $result_items[] = $item;
            }

            // Put the other part back to $item_groups to process next.
            array_unshift($item_groups, $unsafe_group);
        }

        $data_array['items'] = $result_items;
        return $data_array;
    }

    /**
     * Calculate general discount, items discount, shipping discount and create discount description.
     * Saved info in $this->calculated_discounts.
     * Allows you to receive a discount, regardless of the conditions of the order.
     */
    protected function calculateDiscount()
    {
        if ($this->options('discount', 'mode') == 'write') {
            // Apply (or recalculate) discounts
            if ($this->id) {
                $apply = 'reapply';
            } else {
                $apply = true;
            }
        } else {
            $apply = false;
        }

        if ($apply) {
            $this->items;
            $this->params;
            $order = $this->data;
            $order['total'] = $this->subtotal;
        } else {
            // Prepare order data for discount calculation
            $order = array(
                'id'       => $this->id,
                'currency' => $this->currency,
                'contact'  => $this->contact,
                'params'   => $this->params,
                'items'    => $this->items,
                'total'    => $this->subtotal,
            );
        }

        $order['discount_rounding'] = true;
        $discount = shopDiscounts::calculate($order, $apply, $discount_description);
        if (!empty($order['discount_decreased_by'])) {
            // This means shop is misconfigured. Do not change discount, do not allow to create an order (see validation elsewhere).
            $discount += $order['discount_decreased_by'];
        } else {
            $order['discount'] = &$discount;
            $this->distributeDiscountPrepareSplitItem($order);
        }
        unset($order['total']);

        $this->calculated_discounts = $order;

        $template = array(
            'product' => _w('Total discount for this order item: %s.'),
            'service' => _w('Total discount for this service: %s.'),
        );

        foreach ($this->calculated_discounts['items'] as $id => &$item) {
            $item['total_discount'] = round(ifset($item['total_discount'], 0), 4);

            if (!empty($item['total_discount'])) {
                $html_value = shop_currency_html(-$item['total_discount'], $this->currency, $this->currency);
                $item['discount_description'] = sprintf($template[$item['type']], $html_value);
            }
        }
        unset($item);

        $this->calculated_discounts['discount_description'] = $discount_description;
        $this->calculated_discounts['discount'] = $discount;

        //need save this variable
        $this->calculated_discounts['apply'] = $apply;

        if ($apply) {
            $this->data = $order;
            unset($this->data['total']);
            unset($this->data['subtotal']);
        }

        return $this->calculated_discounts;
    }

    /** @return array */
    protected function getCoupon()
    {
        $coupon = null;
        $params = $this->params;
        if (!empty($params['coupon_id'])) {
            $coupon_model = new shopCouponModel();
            $data = $coupon_model->getById($params['coupon_id']);

            if ($data) {
                $coupon = $data;
            } elseif (!empty($params['coupon_code'])) {
                $coupon = array(
                    'code' => $params['coupon_code'],
                );
            } else {
                $coupon = array();
            }
        }

        return $coupon;
    }

    /**
     * Returns information about discounts and html description
     * @return array
     */
    protected function getItemsDiscount()
    {
        $this->data['items_discount'] = array();

        if (!$this->calculated_discounts) {
            $this->calculateDiscount();
        }

        if ($this->calculated_discounts['items']) {
            //Formatting data for use
            foreach ($this->calculated_discounts['items'] as $id => $item) {
                if (!empty($item['total_discount'])) {
                    switch ($item['type']) {
                        case 'service':
                            $selector = sprintf('%d_%d', ifset($item, '_index', 0), $item['service_id']);
                            break;
                        default:
                            $selector = ifset($item, '_index', 0);
                            break;
                    }
                    $this->data['items_discount'][] = array(
                        'value'              => $item['total_discount'],
                        'html'               => ifset($item, 'discount_description', ''),
                        'selector'           => $selector,
                        'cart_item_id'       => ifset($item, 'cart_item_id', null),
                        'type'               => ifset($item, 'type', isset($item['service_id']) ? 'service' : 'product'),
                        'product_id'         => ifset($item, 'product_id', null),
                        'sku_id'             => ifset($item, 'sku_id', null),
                        'service_id'         => ifset($item, 'service_id', null),
                        'service_variant_id' => ifset($item, 'service_variant_id', null),
                    );
                }
            }

        }

        return $this->data['items_discount'];
    }

    /**
     * Returned discount description html. Provides the ability to receive, even if not discounted
     * @return string;
     */
    protected function getDiscountDescription()
    {
        if (isset($this->data['discount_description'])) {
            $discount_description = $this->data['discount_description'];
        } else {
            if (!$this->calculated_discounts) {
                $this->calculateDiscount();
            }
            $discount_description = ifset($this->calculated_discounts, 'discount_description', '');
        }

        return $discount_description;
    }

    /**
     * Returned discount and set discount description.
     * @return int
     */
    protected function getDiscount()
    {
        if (!isset($this->is_changed['discount']) || ($this->is_changed['discount'] === 'hold')) {
            $this->holdDiscount();
            return max(0, $this->original_data['discount']);
        }

        if (!$this->calculated_discounts) {
            $this->calculateDiscount();
        }

        //set calculate discount items
        $this->data['items'] = $this->calculated_discounts['items'];

        $discount = $this->calculated_discounts['discount'];

        if ($discount) {
            $tmp_discount = 0;
            foreach ($this->data['items'] as $item) {
                $tmp_discount += round(ifset($item['total_discount'], 0), 4);
            }
            if (round($tmp_discount, 4) > round($discount, 4)) {
                $error = 'Discount for items reset because it [%f] more then total discount [%f] for order %d';
                $this->errorLog($error, $tmp_discount, $discount, $this->id);
                foreach ($this->data['items'] as &$item) {
                    $item['total_discount'] = 0;
                    $item['discount_description'] = null;
                }
                unset($item);
            }
        }

        unset($this->data['total']);
        unset($this->data['subtotal']);

        $this->data['discount_description'] = $this->calculated_discounts['discount_description'];

        if (!$this->calculated_discounts['apply'] && isset($this->calculated_discounts['shipping']) && $this->calculated_discounts['shipping'] === 0) {
            $this->shipping = 0;
        }

        return $discount;
    }

    protected function getDiscountParams()
    {
        if (!isset($this->is_changed['discount']) || ($this->is_changed['discount'] === 'hold')) {
            return $this['params'];
        }

        if (!$this->calculated_discounts) {
            $this->calculateDiscount();
        }

        return ifset($this->calculated_discounts, 'params', []);
    }

    protected function parseDiscount($value)
    {
        if ($value === null) {
            // Hold previous calculated discount
            $value = $this->original_data['discount'];

            //If previous discount == 0, not need write to order log info about discounts
            if ($this->original_data['discount'] == 0) {
                $this->data['discount_description'] = null;
            }
        } elseif ($value === 'calculate') {
            // Recalculate discount
            $value = null;
            $this->calculated_discounts = null;
            unset($this->data['discount_description']);
            if (!empty($this->data['items']) && is_array($this->data['items'])) {
                foreach ($this->data['items'] as &$item) {
                    $item['total_discount'] = 0;
                    $item['discount_description'] = null;
                }
                unset($item);
            }
        } else {
            // Setup manually
            $this->data['discount_description'] = null;
            $value = $this->castPrice($value, $this->currency);
        }
        return $value;
    }

    protected function compareDiscount($value)
    {
        if ($value === null) {
            $is_changed = 'hold';
            unset($this->data['discount']);
        } elseif ($value === 'calculate') {
            $is_changed = true;
        } else {
            $is_changed = 'manual';
        }
        return $is_changed;
    }

    private function holdDiscount()
    {
        if (isset($this->data['items'])) {
            foreach ($this->data['items'] as &$item) {
                $item['total_discount'] = 0;
                if (isset($item['id']) && isset($this->original_data['items'][$item['id']])) {
                    $item['total_discount'] = max(0, ifset($this->original_data['items'][$item['id']]['total_discount']));
                }
                unset($item);
            }
        }
    }

    private function dependenciesDiscount()
    {
        $name = 'discount';
        $is_changed = ifset($this->is_changed, $name, null);
        if ($is_changed === 'manual' || $is_changed === 'hold') {
            $this->items;
            $order = array(
                'items' => &$this->data['items'],
            );
            if ($is_changed === 'manual') {
                $orig_discount_manually_set = $this->data['discount'];
            } else { // $is_changed === 'hold'
                $orig_discount_manually_set = $this->data['discount'] = $this->original_data['discount'];
                foreach($order['items'] as &$item) {
                    if (!empty($item['id']) && !empty($this->original_data['items'][$item['id']])) {
                        $item['total_discount'] = $this->original_data['items'][$item['id']]['total_discount'];
                    }
                }
                unset($item);
            }
            shopDiscounts::correctOrderDiscount($order, $this->data['discount_description'], $this->data['discount'], $this->currency);
            if (!empty($order['discount_decreased_by'])) {
                // This means shop is misconfigured. Do not change discount, do not allow to create an order (see validation elsewhere).
                $this->data['discount'] = $order['discount'] = $orig_discount_manually_set;
            } else {
                $order['discount'] = &$this->data['discount'];
                $this->distributeDiscountPrepareSplitItem($order);
            }
            $this->calculated_discounts = $order;
            $this->calculated_discounts['discount_description'] = ifset($this->data, 'discount_description', '');
        }
        $this->handleDependencies($name);
    }

    /**
     * @param shopOrder $order
     */
    private function saveDiscountDescription($order)
    {
        // Get previous discount to only write discount description to order log
        // if something actually changed

        if (empty($this->original_data['id'])) {
            $previous_discount = 0.0;
        } else {
            $previous_discount = (float)$this->original_data['discount'];
        }

        $discount_description = $this->getData('discount_description');

        // Save discount description to order log
        if (($previous_discount != $order['discount']) || (!empty($order['discount']) && $discount_description)) {
            if (empty($discount_description)) {
                if (empty($this->original_data['id'])) {
                    $template = _w('Discount specified manually during order creation: %s');
                } else {
                    $template = _w('Discount modified manually via backend editor: %s');
                }

                $discount_description = sprintf_wp(
                    $template,
                    shop_currency($order['discount'], $order['currency'], $order['currency'])
                );
            } elseif ($this->is_changed['discount'] === 'hold') {
                $discount_description = _w('Previously calculated discount was preserved').$discount_description;
            }
            $order->log($discount_description);
        }
    }

    ###############################
    # Tax section
    ###############################
    protected function calculateTax()
    {
        $shipping_address = $this->shipping_address;
        $billing_address = $this->billing_address;
        $currency = $this->currency;
        $items = $this->items;

        if (empty($billing_address['zip']) && empty($billing_address['country'])) {
            $billing_address['zip'] = ifset($shipping_address, 'zip', null);
            $billing_address['country'] = ifset($shipping_address, 'country', null);
        }

        $taxes_params = array(
            'shipping'      => $shipping_address,
            'billing'       => $billing_address,
            'discount_rate' => $this->subtotal ? ($this->discount / $this->subtotal) : 0,
        );

        //Add shipping
        $shipping_info = $this->shipping_plugin_info;

        if ($shipping_info && !empty($shipping_info['options']['tax_id'])) {
            $items['%shipping%'] = array(
                'type'     => 'shipping',
                'tax_id'   => $shipping_info['options']['tax_id'],
                'quantity' => 1,
                'price'    => $this->shipping,
                'currency' => $currency,
            );
        }

        $taxes = shopTaxes::apply($items, $taxes_params, $currency);

        return array(
            'taxes' => $taxes,
            'items' => $items,
        );
    }

    protected function getTax()
    {
        if (!isset($this->is_changed['tax']) || ($this->is_changed['tax'] === 'hold')) {
            return max(0, $this->original_data['tax']);
        }

        $calculate_tax = $this->calculateTax();
        $taxes = $calculate_tax['taxes'];
        $items = $calculate_tax['items'];

        $total_tax = 0;

        foreach ($taxes as $tax) {
            if (!empty($tax['sum_included'])) {
                $total_tax += $tax['sum_included'];
            }
            if (!empty($tax['sum'])) {
                $total_tax += $tax['sum'];
            }
        }

        foreach ($items as $key => $item) {
            if ($key === '%shipping%') {
                continue;
            }

            if (isset($item['tax'])) {
                $this->data['items'][$key]['tax'] = $item['tax'];
            }
            if (isset($item['tax_included'])) {
                $this->data['items'][$key]['tax_included'] = $item['tax_included'];
            }
        }

        return $total_tax;
    }

    public function getItemsTax()
    {
        $calculate_tax = $this->calculateTax();
        $items = $calculate_tax['items'];
        $this->data['items_tax'] = [];

        if (!empty($items)) {
            foreach ($items as $id => $item) {
                if (!empty($item['tax'])) {
                    switch ($item['type']) {
                        case 'service':
                            $selector = sprintf('%d_%d', ifset($item, '_index', 0), $item['service_id']);
                            break;
                        default:
                            $selector = ifset($item, '_index', 0);
                            break;
                    }
                    $this->data['items_tax'][] = array(
                        'value'    => $item['tax'],
                        'selector' => $selector,
                    );
                }
            }
        }

        return $this->data['items_tax'];
    }

    protected function getNotIncludedTax()
    {
        $not_included = 0;

        //Add to the total cost tax not included in the price of goods
        foreach ($this->items as $item) {
            if (ifset($item, 'tax_included', '1') == 0) {
                $not_included += ifset($item, 'tax', 0);
            }
        }

        $shipping_tax_included = ifset($this->data,'params', 'shipping_tax_included', true);

        if ($shipping_tax_included == 0) {
            $not_included += ifset($this->data,'params', 'shipping_tax', true);
        }

        return $not_included;
    }

    protected function parseTax($value)
    {
        if ($value === null) {
            // Hold previous calculated tax
            $value = $this->original_data['tax'];
        } elseif ($value === 'calculate') {
            $value = null;
            // Recalculate discount
        }

        return $value;
    }

    protected function compareTax($value)
    {
        if ($value === null) {
            $is_changed = 'hold';
        } elseif ($value === 'calculate') {
            $is_changed = true;
        } else {
            $is_changed = false;
        }

        return $is_changed;
    }

    ###############################
    # Items section
    ###############################

    protected function initOriginalItems()
    {
        if (!isset($this->original_data['items'])) {
            if ($this->id) {
                $this->original_data['items'] = $this->getStorage('items')->getData($this);
            } else {
                $this->original_data['items'] = array();
            }
        }

        return $this->original_data['items'];
    }

    /**
     * @param $data
     * @param null $format
     * @return array
     */
    protected function parseItems($data, $format = null)
    {
        $options = $this->options('items');
        if (empty($format)) {
            $format = ifset($options['format'], 'raw');
        }

        $items = null;

        switch ($format) {
            case 'raw': # same as described at db.php
                // there no conversion required
                $items = array();
                $product_item = array();
                foreach ($data as $index => $i) {
                    switch (ifset($i['type'])) {
                        case 'service':
                            $items[] = $this->formatItemService($i, $index, $product_item);
                            break;
                        case 'product':
                        default:
                            $items[] = $product_item = $this->formatItemProduct($i, $index);
                            break;
                    }
                }
                break;
            case 'cart': // as returned by shopCart->items(true); all new; used in new checkout
                $data = array_map(function ($item) {
                    return [
                        'cart_item_id' => $item['id'],
                        'product_id'   => $item['product_id'],
                        'sku_id'       => $item['sku_id'],
                        'quantity'     => $item['quantity'],
                        'price'        => shop_currency($item['price'], $item['currency'], $this->currency, false),
                        'services'     => array_map(function ($service_item) {
                            return [
                                'cart_item_id' => $service_item['id'],
                                'id'           => $service_item['service_id'],
                                'variant_id'   => $service_item['service_variant_id'],
                                'price'        => shop_currency($service_item['price'], $service_item['currency'], $this->currency, false),
                            ];
                        }, ifset($item, 'services', [])),
                    ];
                }, $data);
                $data = array_filter(array_values($data));
            // breakthrough
            case 'tree': # at order edit screen (calculate discounts and list available shipping methods)
                /**
                 * Convert tree-like structure where services are part of products
                 * into flat list where services and products are on the same level.
                 */

                $items = array();
                foreach ($data as $index => $i) {
                    $item_services = ifset($i['services'], array());
                    unset($i['services']);
                    $items[] = $product_item = $this->formatItemProduct($i, $index);
                    if (!isset($product_item['quantity']) || !empty($product_item['quantity'])) {
                        foreach ($item_services as $service_index => $s) {
                            $items[] = $this->formatItemService($s, $service_index, $product_item);
                        }
                    }
                }
                break;
            case 'flat': # at order save screen
                $items = $this->castItemsFlat($data);
                break;
            default:
                throw new waException('Unknown items_format: '.htmlspecialchars($format));
        }

        $this->mergeItems($items);

        if ($this->options('items', 'delta')) {
            $items = $this->deltaItems($items);
        }

        $this->extendItems($items);

        return $items;
    }

    protected function getItemsExtended()
    {
        $order_items_model = new shopOrderItemsModel();
        return $order_items_model->getItems($this->dataArray(), true);
    }

    /**
     * @param null $items
     * @return bool
     */
    protected function compareItems($items = null)
    {
        //Use items that have been parsed
        $items = $this->data['items'];

        $is_changed = false;

        $map = array();

        foreach ($items as $id => $item) {
            if (empty($item['id'])) {
                $is_changed = true;
                break;
            } else {
                $map[$item['id']] = $id;
            }
        }
        $fields = array_merge(self::$product_fields, self::$service_fields);
        $fields['stock_id'] = 'int';
        if (!$is_changed) {
            $original_items = $this->initOriginalItems();
            foreach ($original_items as $original_item) {
                $id = $original_item['id'];
                if (!isset($map[$id])) {
                    $is_changed = true;
                    break;
                } else {
                    $item = $items[$map[$id]];
                    foreach ($fields as $field => $format) {
                        if (isset($item[$field]) && ($item[$field] != $original_item[$field])) {
                            $is_changed = true;
                            break;
                        }
                    }
                }
            }
        }

        return $is_changed;
    }

    /**
     * @param $items
     */
    private function mergeItems(&$items)
    {
        $merge_fields = array(
            'name'            => 'string',
            'sku_code'        => 'string',
            'price'           => 'float',
            'total_discount'  => 'float',
            'purchase_price'  => 'float',
            'quantity'        => 'int',
            'stock_id'        => 'int|null',
            'virtualstock_id' => 'int|null',
            'tax_percent'     => 'float|null',
            'tax_included'    => 'int',
        );

        $original_items = $this->initOriginalItems();

        foreach ($items as &$item) {
            if (!empty($item['id']) && isset($original_items[$item['id']])) {
                $original = array();
                foreach ($merge_fields as $field => $format) {
                    if (!isset($item[$field])) {
                        $original[$field] = $this->formatValue($original_items[$item['id']][$field], $format);
                    }
                }
                $item += $original;
            }
            switch ($item['type']) {
                case 'product':
                    $this->formatValues($item, self::$product_fields);
                    break;
                case 'service':
                    $this->formatValues($item, self::$service_fields);
                    break;
            }
            unset($item);
        }
    }

    private function deltaItems($items)
    {
        $original_items = $this->initOriginalItems();

        $new_items = array();
        foreach ($items as $item) {
            if (!empty($item['id'])) {
                $id = $item['id'];
                if (isset($original_items[$id])) {
                    $original_item = &$original_items[$id];
                    $original_item = $item + $original_item;

                    switch ($original_item['type']) {
                        case 'product':
                            $quantity = (int)ifset($original_item['quantity']);
                            if ($quantity) {
                                foreach ($original_items as &$original_service) {
                                    if ($original_service['parent_id'] == $id) {
                                        $original_service['quantity'] = $quantity;
                                    }
                                    unset($original_service);
                                }
                            } else {
                                unset($original_items[$id]);
                                foreach ($original_items as $_id => $original_service) {
                                    if ($original_service['parent_id'] == $id) {
                                        unset($original_items[$_id]);
                                    }
                                }
                            }
                            break;
                        case 'service':
                            if (empty($original_item['quantity'])) {
                                unset($original_items[$id]);
                            }
                            break;
                    }
                    unset($original_item);
                }
            } else {
                if (!empty($item['parent_id'])) {
                    $id = $item['parent_id'];
                    if (isset($original_items[$id])) {
                        $parent_item = &$original_items[$id];
                        if (empty($item['quantity'])) {
                            $item['quantity'] = $parent_item['quantity'];
                        }
                    }
                }
                $new_items[] = $item;
            }
        }

        $merged_items = array();
        foreach ($original_items as $id => $item) {
            $merged_items[] = $item;
            foreach ($new_items as $new_id => $new_item) {
                if (($new_item['type'] == 'service') && !empty($new_item['parent_id']) && ($new_item['parent_id'] == $id)) {
                    $merged_items[] = $new_item;
                    unset($new_items[$new_id]);
                }
            }
        }

        foreach ($new_items as $new_item) {
            $merged_items[] = $new_item;
        }
        return $merged_items;
    }

    private static function itemUniqueKey($item, $parent = array())
    {
        switch ($item['type']) {
            case 'product@disabled':
                $template = '%d.%d.%d';
                $key = sprintf(
                    $template,
                    $item['product_id'],
                    $item['sku_id'],
                    $item['stock_id']
                );
                break;
            case 'service':
                if (!empty($parent['id'])) {
                    $template = '%d@%d';
                    $key = sprintf(
                        $template,
                        $parent['id'],
                        $item['service_id']
                    );
                } else {
                    $key = null;
                }
                break;
            case 'service@disabled':
                $template = '%d.%d.%d.%d.%d';
                $key = sprintf(
                    $template,
                    $item['product_id'],
                    $item['sku_id'],
                    ifset($parent['stock_id']),
                    $item['service_id'],
                    $item['service_variant_id']
                );
                break;
            default:
                $key = null;
        }

        return $key;
    }

    private function originalItemsMap()
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            $original_items = $this->initOriginalItems();
            foreach ($original_items as $item) {
                if ($item['parent_id']) {
                    $parent = $original_items[$item['parent_id']];
                } else {
                    $parent = array();
                }
                if ($key = self::itemUniqueKey($item, $parent)) {
                    $map[$key] = intval($item['id']);
                }
            }
        }
        return $map;
    }

    private function formatItemProduct($i, $index)
    {
        $key_fields = array(
            'product_id' => 'int',
            'sku_id'     => 'int',
            'stock_id'   => 'int|null',
        );

        if (isset($i['item_id'])) {
            $i['id'] = $this->formatValue($i['item_id'], 'int');
            unset($i['item_id']);
        } elseif (isset($i['id'])) {
            $i['id'] = $this->formatValue($i['id'], 'int');
        }

        $item = array(
            'type'               => 'product',
            'service_id'         => null,
            'service_variant_id' => null,
            '_index'             => sprintf('%d', $index),
        );
        if (isset($i['cart_item_id'])) {
            $item['cart_item_id'] = $this->formatValue($i['cart_item_id'], 'int');
        }

        $item += $this->formatValues($i, self::$product_fields);


        if (empty($i['id'])) {
            $item += $this->formatValues($i, $key_fields, true);
            if ($key = self::itemUniqueKey($item)) {
                $map = $this->originalItemsMap();
                if (isset($map[$key])) {
                    $item['id'] = $map[$key];
                }
            }
            if (empty($i['id'])) {
                $item += array(
                    'quantity' => 1,
                );
            }
        } else {
            $item += $this->formatValues($i, $key_fields);
            $item['id'] = $i['id'];
        }

        if (!empty($item['id'])) {
            $original_items = $this->initOriginalItems();
            if (isset($original_items[$item['id']])) {
                $original_item = $original_items[$item['id']];
                $item += $this->formatValues($original_item, $key_fields, true);
                $fields = self::$product_fields;
                if ($item['sku_id'] !== $original_item['sku_id']) {
                    unset($fields['price']);
                    unset($fields['sku_code']);
                    unset($fields['name']);
                    $item += array(
                        'price'           => null,
                        'stock_id'        => null,
                        'virtualstock_id' => null,
                    );
                }
                $item += $this->formatValues($original_item, $fields);
            } else {
                # Item was deleted from order
                unset($item['id']);
            }
        }

        return $item;
    }

    private function formatItemService($s, $service_index, $product_item)
    {
        $key_fields = array(
            'service_id'         => 'int',
            'service_variant_id' => 'int',
        );

        $item = array(
            '_parent_index' => sprintf('%d', $product_item['_index']),
            '_index'        => sprintf('%d_%d', $product_item['_index'], $service_index),
            'type'          => 'service',
            'stock_id'      => null,
        );
        if (isset($s['cart_item_id'])) {
            $item['cart_item_id'] = $this->formatValue($s['cart_item_id'], 'int');
        }

        $item += $this->formatValues($s, self::$service_fields);

        # remap fields: id=>service_id
        if (isset($s['id']) && empty($s['service_id'])) {
            $s['service_id'] = $this->formatValue($s['id'], 'int');
            unset($s['id']);
        }

        # remap fields: variant_id=>service_variant_id
        if (isset($s['variant_id'])) {
            $s['service_variant_id'] = $this->formatValue($s['variant_id'], 'int');
            unset($s['variant_id']);
        }

        # remap fields item_id => id
        if (isset($s['item_id'])) {
            $item['id'] = $this->formatValue($s['item_id'], 'int');
            unset($s['item_id']);
        }

        #copy parent fields into child
        $parent_fields = array(
            'product_id' => 'product_id',
            'sku_id'     => 'sku_id',
            'quantity'   => 'quantity',
            'id'         => 'parent_id',
        );
        if (isset($item['quantity']) && ($item['quantity'] === 0)) {
            unset($parent_fields['quantity']);
        } else {
            unset($item['quantity']);
        }

        foreach ($parent_fields as $parent => $child) {
            if (isset($product_item[$parent])) {
                $item[$child] = $product_item[$parent];
            }
        }

        if (!empty($item['parent_id'])) {
            if (empty($item['id'])) {
                $item += $this->formatValues($s, $key_fields, true);
                if ($key = self::itemUniqueKey($item, $product_item)) {
                    $map = $this->originalItemsMap();
                    if (isset($map[$key])) {
                        $item['id'] = $map[$key];
                    }
                }
            } else {
                $item += $this->formatValues($s, $key_fields);
            }
            if (!empty($item['id'])) {
                $original_items = $this->initOriginalItems();
                if (isset($original_items[$item['id']])) {
                    $original_item = $original_items[$item['id']];
                    if ($original_item['parent_id'] != $item['parent_id']) {
                        unset($item['id']);
                        $item += $this->formatValues($s, $key_fields, true);
                    } else {
                        $item += $this->formatValues($original_item, $key_fields, true);
                        $fields = self::$service_fields;
                        if ($item['service_variant_id'] != $original_item['service_variant_id']) {
                            unset($fields['price']);
                            unset($fields['name']);
                            $item += array(
                                'price' => null,
                            );
                        }
                        $item += $this->formatValues($original_item, $fields);
                    }
                } else {
                    # Item was deleted from order
                    unset($item['id']);
                }
            }
        } else {
            unset($item['id']);
            $item += $this->formatValues($s, $key_fields, true);
        }

        return $item;
    }

    /**
     * @param null $items
     */
    private function extendItems(&$items = null)
    {
        # collect data to extend order items
        $this->initItems($items);
        $product_item = array();

        if ($items === null) {
            $items = &$this->data['items'];
        }

        foreach ($items as $index => &$item) {
            if (!isset($item['_index'])) {
                $item['_index'] = $index;
            }

            $product = $this->itemProduct($item);
            if ($this->options('items', 'extend_round')) {

                // Apply rounding to product prices
                shopRounding::roundProducts(ref([&$product]), $this->currency);

                if (!empty($product['services']) && shopRounding::isEnabled('services')) {
                    // Note that this does not touch services in currency that matches output currency - this is intended.
                    // This does not touch %-based services too - for this see extendServiceItem() below.
                    shopRounding::roundServices($product['services'], $this->currency);
                }
            }
            switch ($item['type']) {
                case 'product':
                    $item = $product_item = $this->extendProductItem($item, $product);
                    if (empty($item['id'])) {
                        $item['purchase_price'] = shop_currency($item['purchase_price'], $product['currency'], $this->currency, false);
                    }
                    break;
                case 'service':
                    $item = $this->extendServiceItem($item, $product_item, $product);
                    $item['_parent_index'] = $product_item['_index'];
                    $item['purchase_price'] = 0;
                    break;
            }

            # round to currency precision
            $item['price'] = shop_currency($item['price'], $this->currency, $this->currency, false);
            if (!empty($item['total_discount'])) {
                $item['total_discount'] = shop_currency($item['total_discount'], $this->currency, $this->currency, false);
            } else {
                $item['total_discount'] = 0;
            }
            $item['currency'] = '';

            unset($item);
        }

        unset($items);
        //XXX extend new items with name, tax_id, purchase_price & sku_code
        # tax_id & tax_percent^tax_included
    }

    private function collectItems($items = null)
    {
        if ($items === null) {
            $items = $this->items;
        }
        if (empty($this->item_ids)) {

            $this->item_ids = array(
                'product'         => array(),
                'service'         => array(),
                'product_service' => array(),
                'variant'         => array(),
                'sku'             => array(),
            );
        }
        foreach ($items as $index => $item) {
            $item = array_map('intval', $item);
            if (!empty($item['product_id'])) {
                $this->item_ids['product'][$item['product_id']] = $item['product_id'];
            }
            if (!empty($item['sku_id'])) {
                $this->item_ids['sku'][$item['sku_id']] = $item['sku_id'];
            }
            if (!empty($item['service_id'])) {
                $service_id = $item['service_id'];
                $this->item_ids['service'][$service_id] = $service_id;
                if (!empty($item['product_id'])) {
                    $this->item_ids['product_service'][$item['product_id']][$service_id] = $service_id;
                }
            }
            if (!empty($item['service_variant_id'])) {
                $this->item_ids['variant'][$item['service_variant_id']] = $item['service_variant_id'];
            }
        }

        return $this->item_ids;
    }

    private function initItemsProduct(&$product)
    {

        if (empty($product['image_id'])) {
            $product['url_crop_small'] = null;
        } else {
            $size = $this->getCropSize();
            $image = array(
                'id'         => $product['image_id'],
                'filename'   => $product['image_filename'],
                'product_id' => $product['id'],
                'ext'        => $product['ext'],
            );
            $product['url_crop_small'] = shopImage::getUrl($image, $size);
        }
    }

    /**
     * @param $items
     */
    private function initItems($items)
    {
        $ids = $this->collectItems($items);

        $ids['product'] = array_diff($ids['product'], array_keys(self::$cached_products));

        if ($ids['product']) {
            $product_model = new shopProductModel();
            $skus_model = new shopProductSkusModel();

            $fields = $this->options('product', 'fields');

            $products = $product_model
                ->select($fields)
                ->where('`id` IN (i:product)', $ids)
                ->fetchAll('id');
            self::$cached_products += $products;

            foreach ($products as $product) {
                if (!empty($product['sku_id'])) {
                    $ids['sku'][$product['sku_id']] = $product['sku_id'];
                }
            }

            $this->item_ids['sku'] = $ids['sku'];

            # fill product's skus
            $fields = $this->options('sku', 'fields');
            $skus = $skus_model
                ->select($fields)
                ->where('`id` IN (i:sku)', $ids)
                ->fetchAll('id');

            foreach ($skus as $id => $sku) {
                $product_id = $sku['product_id'];
                if (isset(self::$cached_products[$product_id])) {
                    self::$cached_products[$product_id]['skus'][$id] = $sku;
                }
            }

            foreach ($ids['product'] as $id) {
                if (isset(self::$cached_products[$id])) {
                    $this->initItemsProduct(self::$cached_products[$id]);
                }
            }
        }


        if ($ids['service'] = array_diff($ids['service'], array_keys(self::$cached_services))) {

            $service_model = new shopServiceModel();
            $variants_model = new shopServiceVariantsModel();
            self::$cached_services += $service_model->getByField('id', $ids['service'], 'id');
            $variants = $variants_model->getByField('id', $ids['variant'], 'id');

            foreach ($variants as $id => $variant) {
                $service_id = $variant['service_id'];
                if (isset(self::$cached_services[$service_id])) {
                    self::$cached_services[$service_id]['variants'][$id] = $variant;
                }
            }

            foreach ($ids['product_service'] as $product_id => $services) {
                if (isset(self::$cached_products[$product_id])) {
                    $product = &self::$cached_products[$product_id];
                    foreach ($services as $service_id) {
                        if (isset(self::$cached_services[$service_id])) {
                            $product['services'][$service_id] = self::$cached_services[$service_id];
                        }
                    }
                }
            }
        }

        # fill product services
        if ($ids['service']) {
            $search = array(
                'product_id' => $ids['product'],
                'service_id' => $ids['service'],
                'status'     => 1,
            );

            $product_services_model = new shopProductServicesModel();
            $services = $product_services_model->getByField($search, 'id');

            foreach ($services as $service) {
                if ($service['primary_price'] !== null) {
                    $product_id = intval($service['product_id']);
                    $id = intval($service['service_id']);
                    $variant_id = intval($service['service_variant_id']);
                    if (isset(self::$cached_products[$product_id]['services'][$id]['variants'][$variant_id])) {
                        $product_service = &self::$cached_products[$product_id]['services'][$id];
                        $sku_id = intval($service['sku_id']);

                        if ($product_service['currency'] == '%') {
                            $price = $service['price'];
                        } else {
                            $price = $service['primary_price'];
                        }

                        $product_service['variants'][$variant_id]['sku_price'][$sku_id] = $price;

                        unset($variant);
                    }
                }
            }
        }
    }

    private function itemProduct($item)
    {
        $id = $item['product_id'];
        if (isset(self::$cached_products[$id])) {
            $product = self::$cached_products[$id];
        } else {
            $price = ifset($item['price'], 0.0);
            $product = array(
                'fake'      => true,
                'id'        => $id,
                'name'      => ifempty($item['name'], sprintf('product_id=%d', $id)),
                'price'     => $price,
                'currency'  => $this['currency'],
                'type_id'   => null,
                'min_price' => $price,
                'max_price' => $price,
                'skus'      => array(),
            );
        }
        return $product;
    }

    private function extendProductItem($i, $product)
    {
        $sku_id = $i['sku_id'];

        $append = array(
            'type'               => 'product',
            'service_id'         => null,
            'service_variant_id' => null,
            'purchase_price'     => 0,
            'sku_code'           => '',
            'sku_name'           => '',
            'name'               => '',
            'tax_id'             => intval(ifset($product['tax_id'])),
        );
        if (empty($sku_id)) {
            if (!empty($product['sku_id']) && isset($product['skus'][$product['sku_id']])) {
                $sku_id = intval($product['sku_id']);
            } elseif ($product['skus']) {
                reset($product['skus']);
                $sku_id = key($product['skus']);
            }
            $i['sku_id'] = $sku_id;
        }

        if (!empty($product['skus'][$sku_id])) {
            $sku = $product['skus'][$sku_id];
        } else {
            $price = isset($i['price']) ? $i['price'] : null;
            $name = isset($i['name']) ? $i['name'] : sprintf('sku_id=%d', $sku_id);
            $sku = $product['skus'][$sku_id] = array(
                'fake'          => true,
                'name'          => $name,
                'id'            => $sku_id,
                'price'         => $price,
                'primary_price' => $price,
            );
        }

        if (empty($sku['fake'])) {
            if (!empty($sku['name'])) {
                $name = sprintf('%s (%s)', ifempty($product['name'], ifset($i['name'])), $sku['name']);
            } elseif (!empty($product['name'])) {
                $name = $product['name'];
            } else {
                $name = '';
            }
            if (empty($i['id'])) {
                #set data only for new items
                $i['purchase_price'] = $sku['purchase_price'];
            }
            $i['sku_code'] = $sku['sku'];
            $i['sku_name'] = $sku['name'];
            $i['name'] = $name;
        } else {
            $i['deleted'] = true;
        }

        if (!isset($i['price']) && isset($sku['primary_price'])) {
            $i['price'] = (float)$this->currency_model->convertByRate($sku['primary_price'], 1, $this->rate);
        }

        //it's `cached` data
        $i['sku'] = $sku;
        unset($product['skus']);
        $i['product'] = $product;

        return $i + $append;
    }

    private function extendServiceItem($item, $product_item, $product)
    {
        $item['purchase_price'] = 0;
        if (isset($product['services'][$item['service_id']])) {
            $service = $product['services'][$item['service_id']];
            if (isset($service['variants'][$item['service_variant_id']])) {
                $variant = $service['variants'][$item['service_variant_id']];
            } else {
                $variant = array(
                    'fake'  => true,
                    'id'    => $item['service_variant_id'],
                    'price' => $service['price'],
                );
            }
        } else {
            $service = array(
                'fake' => true,
                'id'   => $item['service_id'],
            );
            $variant = array(
                'fake' => true,
                'id'   => $item['service_variant_id'],
            );
        }

        if (!empty($service['fake'])) {
            $item['deleted'] = true;
        } else {
            if ($service['tax_id'] === null) {
                $item['tax_id'] = 0;
            } else {
                $item['tax_id'] = intval($service['tax_id']);
                if (!$item['tax_id']) {
                    $item['tax_id'] = intval(ifset($product_item['tax_id']));
                }
            }
            if (!empty($variant['fake'])) {
                $item['deleted'] = true;
            }
        }

        if (!isset($item['price'])) {
            $sku_id = intval($item['sku_id']);
            if (isset($variant['sku_price'][$sku_id])) {
                $price = $variant['sku_price'][$sku_id];
            } elseif (isset($variant['sku_price'][0])) {
                $price = $variant['sku_price'][0];
            } else {
                $price = $variant['primary_price'];
            }
            if ($service['currency'] == '%') {
                $item['price'] = (float)($price / 100) * $product_item['price'];
            } else {
                $item['price'] = (float)$this->currency_model->convertByRate($price, 1, $this->rate);
            }
            if ($item['price'] > 0 && shopRounding::isEnabled('services')) {
                $item['price'] = shopRounding::roundCurrency($item['price'], $this->currency);
            }
        }

        //Check service variant name. If not found, set main service name
        if (empty($service['fake']) && empty($item['name'])) {
            $item['name'] = $service['name'];
            if (strlen(ifset($variant, 'name', null))) {
                $item['name'] .= sprintf(' (%s)', $variant['name']);
            }
        } elseif (empty($item['name'])) {
            $item['name'] = '';
        }

        #copy parent fields into child
        $parent_fields = array(
            'product_id' => 'product_id',
            'sku_id'     => 'sku_id',
        );

        foreach ($parent_fields as $parent => $child) {
            if (empty($item[$child]) && isset($product_item[$parent])) {
                $item[$child] = $product_item[$parent];
            }
        }

        //it's `cached` data
        $item['service_variant'] = $variant;
        unset($service['variants']);
        $item['service'] = $service;
        unset($product['services']);
        $item['product'] = $product;
        foreach ($product_item as $name => $value) {
            if (is_array($value)) {
                unset($product_item[$name]);
            }
        }
        $item['parent_item'] = $product_item;

        return $item;
    }

    /**
     * @param $data
     * @return array
     * @deprecated move it into shopOrderEditAction
     */
    protected function castItemsFlat($data)
    {
        $type = 'edit';

        $items_map = self::extractValue($data, 'item', $type);
        $products = self::extractValue($data, 'product', $type);
        $skus = self::extractValue($data, 'sku', $type);
        $services = self::extractValue($data, 'service', $type);
        $variants = self::extractValue($data, 'variant', $type);
        $names = self::extractValue($data, 'name', $type);
        $prices = self::extractValue($data, 'price', $type);
        $total_discounts = self::extractValue($data, 'total_discount', $type);
        $quantities = self::extractValue($data, 'quantity', $type);
        $stocks = self::extractValue($data, 'stock', $type);

        $items = array();

        foreach ($items_map as $index => $item_id) {

            $quantity = $quantities[$item_id];
            $items[] = array(
                'id'                 => (int)$item_id,
                'name'               => ifset($names, $item_id, null),
                'product_id'         => (int)ifset($products, $item_id, null),
                'sku_id'             => (int)$skus[$item_id],
                'type'               => 'product',
                'service_id'         => null,
                'service_variant_id' => null,
                'price'              => $this->formatValue($prices[$item_id], 'float'),
                'total_discount'     => $this->formatValue(ifset($total_discounts, $item_id, 0), 'float'),
                'quantity'           => (int)$quantities[$item_id],
                'stock_id'           => !empty($stocks[$item_id]) ? intval($stocks[$item_id]) : null,
                'parent_id'          => null,
                '_index'             => sprintf('%d', $index),
            );

            if (!empty($services[$index])) {
                $service_index = 0;
                foreach ($services[$index] as $group => $services_grouped) {
                    /**@var string $group one of 'new','edit', 'item', 'add' */
                    foreach ($services_grouped as $k => $service_id) {
                        /** @var int $k */
                        $item = array(
                            'name'               => null,
                            'product_id'         => (int)$products[$item_id],
                            'sku_id'             => (int)$skus[$item_id],
                            'type'               => 'service',
                            'service_id'         => (int)$service_id,
                            'price'              => $this->formatValue($prices[$group][$index][$k], 'float'),
                            'total_discount'     => $this->formatValue(ifset($total_discounts, $group, $index, $k, 0), 'float'),
                            'quantity'           => (int)$quantity,
                            'service_variant_id' => null,
                            'stock_id'           => null,
                            'parent_id'          => null,
                            '_parent_index'      => sprintf('%d', $index),
                        );

                        $item['parent_id'] = (int)$item_id;

                        if ($group == 'item') {        // it's item for update: $k is ID of item
                            $item['id'] = (int)$k;
                            if (isset($names[$k])) {
                                $item['name'] = $names[$k];
                            }
                            $item['_index'] = sprintf('%d_%d', $index, $service_index++);
                        } else {
                            $item['_index'] = sprintf('%d_%d', $index, $k);
                        }
                        $item['parent_id'] = (int)$item_id;


                        if (!empty($variants[$index][$service_id])) {
                            $item['service_variant_id'] = (int)$variants[$index][$service_id];
                        }
                        $items[] = $item;
                    }
                }
            }
        }

        $type = 'add';
        $products = self::extractValue($data, 'product', $type);
        if ($products) {

            $skus = self::extractValue($data, 'sku', $type);
            $prices = self::extractValue($data, 'price', $type);
            $total_discounts = self::extractValue($data, 'total_discount', $type);
            $quantities = self::extractValue($data, 'quantity', $type);
            $services = self::extractValue($data, 'service', $type);
            $variants = self::extractValue($data, 'variant', $type);
            $stocks = self::extractValue($data, 'stock', $type);

            foreach ($products as $index => $product_id) {
                $product_id = intval($product_id);
                $sku_id = intval($skus[$index]);
                $quantity = $quantities[$index]['product'];
                $items[] = array(
                    'name'               => null,
                    'product_id'         => $product_id,
                    'sku_id'             => $sku_id,
                    'type'               => 'product',
                    'service_id'         => null,
                    'price'              => $this->formatValue($prices[$index]['product'], 'float'),
                    'total_discount'     => $this->formatValue(ifset($total_discounts, $index, 'product', 0), 'float'),
                    'currency'           => '',
                    'quantity'           => $quantity,
                    'service_variant_id' => null,
                    'stock_id'           => !empty($stocks[$index]['product']) ? $stocks[$index]['product'] : null,
                    'parent_id'          => null,
                );
                if (!empty($services[$index])) {
                    foreach ($services[$index] as $service_id) {
                        $service_id = intval($service_id);
                        $item = array(
                            'name'               => null,
                            'product_id'         => $product_id,
                            'sku_id'             => $skus[$index],
                            'type'               => 'service',
                            'service_id'         => $service_id,
                            'price'              => $this->formatValue(ifset($prices, $index, 'service', $service_id, 0), 'float'),
                            'total_discount'     => $this->formatValue(ifset($total_discounts, $index, 'service', $service_id, 0), 'float'),
                            'currency'           => '',
                            'quantity'           => $quantity,
                            'service_variant_id' => null,
                            'stock_id'           => null,
                            'parent_id'          => null,
                        );
                        if (!empty($variants[$index][$service_id])) {
                            $item['service_variant_id'] = intval($variants[$index][$service_id]);
                        }
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @param string $weight_unit `kg`,`g`, `oz` and etc
     * @return array
     */
    protected function shippingItems($weight_unit = null)
    {
        $currency = $this->currency;

        $shipping_items = array();
        $this->items;

        $units = array();

        if ($weight_unit) {
            $units['weight'] = $weight_unit;
        }

        shopShipping::extendItems($this->data['items']);

        $items = $this->items;

        shopShipping::convertItemsDimensions($items, $units);

        foreach ($items as $i) {
            $shipping_items[] = array(
                'name'     => '',
                'price'    => shop_currency(ifset($i, 'price', 0), $currency, $currency, false),
                'quantity' => ifset($i, 'quantity', 1),
                'weight'   => ifset($i['weight']),
                'height'   => ifset($i['height']),
                'width'    => ifset($i['width']),
                'length'   => ifset($i['length']),
            );
        }
        return $shipping_items;
    }

    ###############################
    # Shipping section
    ###############################

    protected function getShippingAddress()
    {
        $shipping_address = shopHelper::getOrderAddress($this->params, 'shipping');
        if (!$shipping_address) {
            $address = $this->contact->getFirst('address.shipping');
            if (!$address) {
                $address = $this->contact->getFirst('address');
            }
            $shipping_address = ifset($address, 0, ifset($address, 'data', null));
        }

        if (!array_filter($shipping_address)) {
            return array();
        }

        return $shipping_address;
    }

    protected function getShippingAddressObject()
    {
        $formatter = new waContactAddressSeveralLinesFormatter();
        $shipping_address = $formatter->format(array('data' => $this->shipping_address));
        $shipping_address = $shipping_address['value'];
        return $shipping_address;
    }

    protected function getShippingAddressText()
    {
        return $this->getAddressFormatted('shipping', 'for_map');
    }

    protected function getShippingAddressHtml()
    {
        return $this->getAddressFormatted('shipping', 'SeveralLines');
    }

    protected function getBillingAddressText()
    {
        return $this->getAddressFormatted('billing', 'for_map');
    }

    protected function getBillingAddressHtml()
    {
        return $this->getAddressFormatted('billing', 'SeveralLines');
    }

    protected function getAddressFormatted($address_type, $formatter_type)
    {
        $address = shopHelper::getOrderAddress($this->params, $address_type);

        if ($formatter_type == 'for_map') {
            $address_f = array();
            foreach (array('country', 'region', 'zip', 'city', 'street') as $k) {
                if (empty($address[$k])) {
                    continue;
                } elseif ($k == 'country') {
                    $address_f[$k] = waCountryModel::getInstance()->name(ifempty($address['country']));
                } elseif ($k == 'region') {
                    $address_f['region'] = '';
                    if (!empty($address['country']) && !empty($address['region'])) {
                        $model = new waRegionModel();
                        if ($region = $model->get($address['country'], $address['region'])) {
                            $address_f['region'] = $region['name'];
                        }
                    }
                } else {
                    $address_f[$k] = $address[$k];
                }
            }
            return implode(', ', $address_f);
        } elseif ($formatter_type == 'SeveralLines') {
            $formatter = new waContactAddressSeveralLinesFormatter();
            $address = $formatter->format(array('data' => $address));
            return $address['value'];
        } else {
            $formatter = new waContactAddressOneLineFormatter();
            $address = $formatter->format(array('data' => $address));
            return $address['value'];
        }
    }

    protected function getMap($adapter = null)
    {
        try {
            $env = ifset($this->options, 'environment', wa()->getEnv());
            $map_options = array(
                'width'  => '200px',
                'height' => '200px',
                'zoom'   => 13,
                'static' => true,
                'on_error' => $env === 'backend' ? 'show' : ''
            );
            $map = wa()->getMap($adapter)->getHTML($this->shipping_address_text, $map_options);
        } catch (waException $e) {
            $map = '';
        }
        return $map;
    }

    /** @return waShipping */
    protected function getShippingPlugin()
    {
        $plugin = null;
        $params = $this->params;
        if (!empty($params['shipping_id'])) {
            try {
                $plugin = shopShipping::getPlugin(null, $params['shipping_id']);
            } catch (waException $ex) {
                $this->errorLog($ex->getMessage());
            }
        }
        return $plugin;
    }

    protected function getShippingName()
    {
        $name = null;
        $params = $this->params;
        if (isset($params['shipping_name'])) {
            $name = $params['shipping_name'];
        } elseif (!empty($params['shipping_id'])) {
            if ($info = $this->pluginInfo($params['shipping_id'], shopPluginModel::TYPE_SHIPPING)) {
                $name = $info['name'];
                $this->data['params']['shipping_name'] = $name;
            }

        }
        return $name;
    }

    public function getTracking($env = null)
    {
        $tracking = '';
        $params = $this->params;
        if (!empty($params['shipping_id']) && ($plugin = $this->getShippingPlugin())) {
            try {
                if (!empty($params['tracking_number'])) {
                    if ($plugin->getProperties('external_tracking')) {
                        switch ($env) {
                            case 'backend':
                                $id = sprintf('shop_tracking_%s', $this->id);
                                $app_url = wa()->getAppUrl('shop');
                                $tracking = <<<HTML
<i class="icon16 loading" id="{$id}"></i>
<script type="text/javascript">
    (function () {
        $.get('{$app_url}?module=order&action=tracking&order_id={$this->id}', function (data) {
            if (data && data.status === 'ok') {
                $('#{$id}').replaceWith(data.data.tracking);
            }
        });
    })();
</script>
HTML;
                                break;
                            case 'email':
                            case 'sms':
                                break;
                            default:
                                break;
                        }

                    } else {
                        $tracking = $plugin->tracking($params['tracking_number']);
                    }
                }
            } catch (waException $ex) {
                $tracking = $ex->getMessage();
            }
        }

        return $tracking;
    }

    public function getShippingCustomFields()
    {
        $plugin = $this->getShippingPlugin();
        if (!$plugin) {
            return [];
        }

        $custom_fields = $plugin->customFields(new waOrder());
        if (!$custom_fields) {
            return [];
        }

        $params = $this->params;
        foreach ($custom_fields as $k => $v) {
            if (!empty($params['shipping_params_'.$k])) {
                $custom_fields[$k]['value'] = $params['shipping_params_'.$k];
            } else {
                unset($custom_fields[$k]);
            }
        }
        return $custom_fields;
    }

    public function getShippingPluginInfo($shipping_id = null)
    {
        $plugin_info = [];

        if (!$shipping_id) {
            $shipping_id = $this->shipping_id;

            if ($shipping_id) {
                $shipping_id = explode('.', $shipping_id);
                $shipping_id = $shipping_id[0];
            }
        }

        if ($shipping_id) {
            $plugin_info = $this->pluginInfo($shipping_id, shopPluginModel::TYPE_SHIPPING);
        }

        return $plugin_info;
    }

    protected function parseShipping($value)
    {
        if ($value === true) {
            $value = null;
            // Recalculate shipping
        } elseif ($value !== null) {
            // Setup manually
            $value = $this->castPrice($value, $this->currency);
        }
        return $value;
    }

    protected function parseShippingParams($data)
    {
        $parsed = array();
        if ($data) {
            foreach ($data as $k => $v) {
                if ($k && $k[0] !== '_') {
                    $parsed['shipping_params_'.$k] = $v;
                    $this->data['params']['shipping_params_'.$k] = $v;
                }
            }
        }
        return $parsed;
    }

    protected function compareShipping($value)
    {
        if ($value === true) {
            $is_changed = true;
            // Recalculate shipping
        } elseif ($value !== null) {
            // Setup manually
            $is_changed = 'manual';
        } else {
            $is_changed = false;
        }
        return $is_changed;
    }

    protected function readShippingId()
    {
        $params = $this->params;
        if (empty($params['shipping_id'])) {
            return null;
        }
        if (isset($params['shipping_rate_id'])) {
            return sprintf('%d.%s', $params['shipping_id'], $params['shipping_rate_id']);
        } else {
            return $params['shipping_id'];
        }
    }

    /** @return float|null */
    protected function getShipping()
    {
        $shipping = null;
        $params = $this->params;
        if (!empty($params['shipping_id'])) {
            $id = $this->readShippingId();
            $rates = $this->getShippingMethods(true);
            if (isset($rates[$id]) && is_array($rates[$id])) {
                $shipping = $rates[$id]['rate'];
            }
        }

        // Recalculate total after shipping cost is recalculated for any reason
        unset($this->data['total']);

        return $shipping;
    }

    public function getShippingMethods($selected_only = false, $allow_external_for = array())
    {
        $customer_data = $this['customer'];
        if ($customer_data) {
            $contact = new waContact($customer_data);
        } else {
            $contact = $this->contact;
        }
        if ($shipping_address = $contact->getFirst('address.shipping')) {
            $shipping_address = ifset($shipping_address, 0, ifset($shipping_address, 'data', null));
        }

        $method_params = array(
            'currency'           => $this->currency,
            'total_price'        => $this->subtotal - $this->discount,
            //Subtraction of a discount is necessary for the correct calculation of deliveries. This is how the frontend works;
            'no_external'        => true,
            'allow_external_for' => (array) $allow_external_for,
            'custom_html'        => true,
            'shipping_params'    => array(),
            'departure_datetime' => shopDepartureDateTimeFacade::getDeparture(null, ifset($this->data, 'storefront', null)),
        );

        $shipping_id = $this['shipping_id'];
        if (isset($shipping_id)) {
            $method_params['allow_external_for'][] = $shipping_id;
        }

        $params = $this->params;
        if (!empty($params['shipping_id'])) {
            $method_params['allow_external_for'][] = $this->params['shipping_id'];

            if ($selected_only) {
                $method_params[shopPluginModel::TYPE_SHIPPING] = array(
                    'id' => (int)$this->params['shipping_id'],
                );
            }
        }

        if (!empty($params['payment_id'])) {
            try {
                $payment = shopPayment::getPluginInfo($params['payment_id']);
                $method_params['payment_type'] = array_keys(ifset($payment, 'options', 'payment_type', []));
            } catch (waException $ex) {
                ;//Plugin not found;
            }
        }

        $shipping_services = array();

        foreach ($method_params['allow_external_for'] as &$_shipping_id) {
            if (preg_match('@^(\d+)\D(.*)$@', $_shipping_id, $matches)) {
                $service_id = $matches[2];
                $_shipping_id = (int)$matches[1];
                if (!isset($shipping_services[$_shipping_id])) {
                    $shipping_services[$_shipping_id] = array();
                }
                $shipping_services[$_shipping_id][] = $service_id;
            } else {
                $_shipping_id = (int)$_shipping_id;
            }
            unset($_shipping_id);
        }


        if ($this->id) {
            if ($params = $this->params) {
                if (!empty($params['shipping_id'])) {
                    $shipping_id = (int)$params['shipping_id'];

                    if (preg_match('@^(\d+)\D(.*)$@', $params['shipping_id'], $matches)) {
                        $service_id = $matches[2];
                        if (!isset($shipping_services[$shipping_id])) {
                            $shipping_services[$shipping_id] = array();
                        }
                        $shipping_services[$shipping_id][] = $service_id;
                    }

                    foreach ($params as $name => $value) {
                        if (preg_match('@^shipping_params_(.+)$@', $name, $matches)) {
                            if (!isset($method_params['shipping_params'][$shipping_id])) {
                                $method_params['shipping_params'][$shipping_id] = array();
                            }
                            $method_params['shipping_params'][$shipping_id][$matches[1]] = $value;
                        }
                    }
                }
            }
        }

        foreach ($this->data as $name => $value) {
            if (preg_match('@^shipping_(\d+)$@', $name, $matches)) {
                $method_params['shipping_params'][$matches[1]] = $value;
            }
        }

        foreach ($shipping_services as $shipping_id => $services) {
            if (!isset($method_params['shipping_params'][$shipping_id])) {
                $method_params['shipping_params'][$shipping_id] = array();
            }
            $method_params['shipping_params'][$shipping_id]['%service_variant_ids'] = $services;
            $method_params['shipping_params'][$shipping_id]['%service_variant_id'] = reset($services);
        }

        // Calculate discounts because they may affect shipping price
        if (!$this->calculated_discounts) {
            $this->calculateDiscount();
        }

        $shipping_methods = shopHelper::getShippingMethods($shipping_address, $this->shippingItems(), $method_params);

        // Round shipping cost
        if (wa()->getSetting('round_shipping')) {
            foreach ($shipping_methods as &$method) {
                if (isset($method['rate'])) {
                    $method['rate'] = shopRounding::roundCurrency($method['rate'], $method['currency']);
                }
            }
            unset($method);
        }

        # set shipping cost
        if (isset($this->calculated_discounts['shipping']) && ($this->calculated_discounts['shipping'] === 0)) {
            foreach ($shipping_methods as &$m) {
                if (!is_string($m['rate'])) {
                    $m['rate'] = 0;
                }
                unset($m);
            }
        }

        return $shipping_methods;
    }

    /** @return array */
    protected function getCourier()
    {
        $courier = null;
        if (($params = $this->params) && !empty($params['courier_id'])) {
            $courier_model = new shopApiCourierModel();
            $courier = $courier_model->getById($this->params['courier_id']);
        }
        return $courier;
    }


    ###############################
    # Internal utils section
    ###############################

    private function escape($data, $field)
    {
        $escape = false;
        if ($escape) {
            switch ($field) {
                case 'items':
                    foreach ($data as &$product) {

                        if (!empty($product['name'])) {
                            $product['name'] = htmlspecialchars($product['name']);
                        }
                        if (!empty($product['item']['name'])) {
                            $product['item']['name'] = htmlspecialchars($product['item']['name']);
                        }
                        if (!empty($product['skus'])) {
                            foreach ($product['skus'] as &$sku) {
                                if (!empty($sku['name'])) {
                                    $sku['name'] = htmlspecialchars($sku['name']);
                                }
                                unset($sku);
                            }
                        }
                        if (!empty($product['services'])) {
                            foreach ($product['services'] as &$service) {
                                if (!empty($service['name'])) {
                                    $service['name'] = htmlspecialchars($service['name']);
                                }
                                if (!empty($service['item']['name'])) {
                                    $service['item']['name'] = htmlspecialchars($service['item']['name']);
                                }
                                if (!empty($service['variants'])) {
                                    foreach ($service['variants'] as &$variant) {
                                        $variant['name'] = htmlspecialchars($variant['name']);
                                        unset($variant);
                                    }
                                }
                                unset($service);
                            }
                        }
                        unset($product);
                    }
                    break;
                case 'contact':
                    $data['name'] = htmlspecialchars($data['name']);
                    break;
            }

        }
        return $data;
    }

    private static function camelCase($m)
    {
        return strtoupper($m[2]);
    }

    private static function camelMethod($template, $name)
    {
        if (strpos($template, '%s') === 0) {
            $pattern = '@(_)([a-z])@';
        } else {
            $pattern = '@(^|_)([a-z])@';
        }
        $camel_name = preg_replace_callback($pattern, array(__CLASS__, 'camelCase'), $name);
        return sprintf($template, $camel_name);
    }

    private function getCropSize()
    {
        static $crop_size = null;
        if ($crop_size === null) {
            $crop_size = $this->config->getImageSize('crop_small');
        }
        return $crop_size;
    }

    protected function castPrice($value, $currency = null)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        if (!empty($currency)) {
            return waCurrency::round($value, $currency);
        }
        return str_replace(',', '.', (double)$value);
    }

    private static function extractValue($data = array(), $scope = null, $_ = null)
    {
        $args = func_get_args();
        array_shift($args);
        while ($scope = array_shift($args)) {
            $data = isset($data[$scope]) ? $data[$scope] : array();
        }

        return $data;
    }

    private function formatValues(&$array, $fields, $force = false)
    {
        $result = array();
        foreach ($fields as $field => $format) {
            if (isset($array[$field])) {
                $result[$field] = $array[$field] = $this->formatValue($array[$field], $format);
            } elseif ($force) {
                $result[$field] = $array[$field] = $this->formatValue(null, $format);
            }
        }
        return $result;
    }

    private function formatValue($value, $format)
    {
        switch ($format) {
            case 'float':
                $value = max(0.0, floatval(str_replace(',', '.', $value)));
                break;
            case 'float|null':
                $value = ($value !== null) ? max(0.0, floatval(str_replace(',', '.', $value))) : null;
                break;
            case 'int':
                $value = max(0, intval($value));
                break;
            case 'int|null':
                $value = ($value !== null) ? max(0, intval($value)) : null;
                break;
            case 'string|null':
                $value = ($value !== null) ? ((string)$value) : null;
                break;
        }
        return $value;
    }

    private function pluginInfo($id, $type)
    {
        static $model;
        if (!$model) {
            $model = new shopPluginModel();
        }

        return $model->getByField(compact('id', 'type'));
    }

    private function readOnlyFields()
    {
        $result = self::$readonly_fields;
        $result[] = 'id';
        if (!empty($this->original_data['id'])) {
            $result[] = 'currency';
        }
        return $result;
    }

    private function errorLog($message, $_ = null)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $template = array_shift($args);
            foreach ($args as &$arg) {
                if (is_array($arg)) {
                    $arg = var_export($arg, true);
                }
                unset($arg);
            }

            $message = vsprintf($template, $args);
        }

        waLog::log($message, 'shop/error.log');
    }
}
