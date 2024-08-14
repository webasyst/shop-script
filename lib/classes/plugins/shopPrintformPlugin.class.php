<?php

/**
 * Class shopPrintformPlugin
 */
abstract class shopPrintformPlugin extends shopPlugin implements shopPrintformInterface
{
    const TYPE_CHANGED = 'changed';
    const TYPE_ORIGINAL = 'original';

    /**
     * @var shopPrintformTemplate
     */
    private $template;

    private $order_options = array(
        'items' => true,
    );

    public function __construct($info)
    {
        parent::__construct($info);

        $this->template = new shopPrintformTemplate(
            $this->path.'/templates/actions/printform/template.html',
            wa()->getDataPath('plugins/'.$this->id.'/template.html')
        );

        if (!empty($info['emailprintform'])) {
            $this->common_settings_config['emailprintform'] = array(
                'value'        => false,
                'title'        => _w('Email to customer'),
                'description'  => _w('Enable to make this print form automatically emailed to customer when order was placed'),
                'control_type' => waHtmlControl::CHECKBOX,
                'subject'      => 'printform',
            );
        }
    }

    /**
     * Safe for rights getting order data
     * @param int|waOrder|array $order_data order ID | order data-array | waOrder object
     * @param $options
     * @throws waException
     * @return waOrder
     */
    public function getOrder($order_data, $options = array())
    {
        /**
         * @var waOrder $order
         */
        $order = null;

        switch (wa()->getEnv()) {
            case 'backend':
                if (!wa()->getUser()->getRights('shop', !$order_data ? 'settings' : 'orders')) {
                    throw new waException(_w("Access denied"));
                }
                if ($order_data) {
                    $order = $this->getShopPaymentOrder($order_data);
                } else {
                    $config = wa('shop')->getConfig();
                    /**
                     * @var shopConfig $config
                     */
                    $allowed_currencies = $this->allowedCurrency();
                    if ($allowed_currencies !== true) {
                        $allowed_currencies = (array)$allowed_currencies;
                        $currencies = $config->getCurrencies();
                        $matched_currency = array_intersect($allowed_currencies, array_keys($currencies));
                        if (!$matched_currency) {
                            $message = _w('Data cannot be processed because required currency %s is not defined in your store settings.');
                            throw new waException(sprintf($message, implode(', ', $allowed_currencies)));
                        }
                        $currency = reset($matched_currency);

                    } else {
                        $currency = $config->getCurrency();
                    }
                    $dummy_order = array(
                        'contact_id' => wa()->getUser()->getId(),
                        'id'         => 1,
                        'id_str'     => shopHelper::encodeOrderId(1),
                        'currency'   => $currency,
                        'items'      => array(),
                    );
                    $order = waOrder::factory($dummy_order);
                }
                break;
            default:
                // frontend
                $order = $this->getShopPaymentOrder($order_data);
                break;
        }

        if (!$order) {
            throw new waException(_w('Order not found.'), 404);
        }

        if (!empty($options['items'])) {
            $this->extendItems($order, $options['items']);
        }

        return $order;
    }

    /**
     * Helper for getOrder
     *
     * Get shop-payment formalized order
     * Encapsulate input param preparation
     *
     * @param $order int|waOrder|array $order_data order ID | order data-array | waOrder object
     * @return waOrder|null
     * @throws waException
     */
    protected function getShopPaymentOrder($order)
    {
        if (!$order) {
            return null;
        }
        // Order ID case
        if (wa_is_int($order)) {
            // To prevent incorrect order receiving
            // encode into ORDER_STR_ID format, cause shopPayment::getOrderData will be decode it
            $order = shopHelper::encodeOrderId($order, $this);
        }
        return shopPayment::getOrderData($order, $this);
    }



    /**
     *
     * @param waOrder $order
     * @param mixed[] $params
     * @todo allow setup tax & discount calculation via $params
     * @return array
     */
    private function extendItems(&$order, $params)
    {
        $product_model = new shopProductModel();

        $items = $order->items;
        foreach ($items as & $item) {
            $data = $product_model->getById($item['product_id']);
            $item['tax_id'] = ifset($data['tax_id']);
            $item['currency'] = $order->currency;
            unset($item);
        }

        $taxes_params = array(
            'billing'  => $order->billing_address,
            'shipping' => $order->shipping_address,
        );

        shopTaxes::apply($items, $taxes_params, $order->currency);

        $discount = $order->discount;
        foreach ($items as & $item) {
            if (!empty($item['total_discount'])) {
                $discount -= $item['total_discount'];
                $item['total'] -= $item['total_discount'];
                $item['price'] -= $item['total_discount'] / $item['quantity'];
            }
        }

        unset($item);

        if ($discount) {
            #calculate discount as part of price
            if ($order->total + $discount - $order->shipping > 0) {
                $k = 1.0 - ($discount) / ($order->total + $discount - $order->shipping);
            } else {
                $k = 0.0;
            }

            foreach ($items as & $item) {
                if ($item['tax_included']) {
                    $item['tax'] = round($k * $item['tax'], 4);
                }
                $item['price'] = round($k * $item['price'], 4);
                $item['total'] = round($k * $item['total'], 4);
            }
            unset($item);
        }
        $order->items = $items;
    }

    /**
     * @return bool|string[]
     */
    public function allowedCurrency()
    {
        return true;
    }

    /**
     * @deprecated
     * @param waOrder|int|array $order
     * @return string HTML form
     * @throws waException
     */
    public function renderForm($order)
    {
        if (!($order instanceof waOrder)) {
            $order = $this->getOrder($order, $this->order_options);
        }
        $view = $this->template->getView();
        $view->assign(array(
            'settings' => $this->getSettings(),
            'order'    => $order,
        ));
        $this->prepareForm($order, $view);
        return $this->template->display();
    }

    protected function setOrderOption($name, $value = null)
    {
        $this->order_options[$name] = $value;
    }

    /**
     * @deprecated
     * @param waOrder $order
     * @param waView $view
     */
    protected function prepareForm(waOrder &$order, waView &$view)
    {

    }

    public function sendForm($order, $force = false)
    {
        $result = false;
        if ($force || $this->getSettings('emailprintform')) {
            $order = $this->getOrder($order, array('items' => true));
            if ($email = $order->getContactField('email', 'default')) {
                $subject = sprintf("%s %s", $this->getName(), $order->id_str);
                $message = new waMailMessage($subject, $this->renderPrintform($order));
                $message->setTo(array($email));

                $general = wa('shop')->getConfig()->getGeneralSettings();
                if (!empty($general['email'])) {
                    $message->setFrom($general['email'], $general['name']);
                }
                if ($message->send()) {
                    $result = true;
                    $log = sprintf(_w("Printable form <strong>%s</strong> sent to customer."), $this->getName());
                    $order_log_model = new shopOrderLogModel();
                    $order_log_model->add(array(
                        'order_id'        => $order->id,
                        'contact_id'      => null,
                        'action_id'       => '',
                        'text'            => '<i class="icon16 email fas fa-envelope text-gray custom-mr-4"></i>'.$log,
                        'before_state_id' => $order->state_id,
                        'after_state_id'  => $order->state_id,
                    ));
                }
            } elseif ($force) {
                throw new waException('Empty email');
            }
        }
        return $result;

    }

    /**
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->template->getPath();
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template->getContent();
    }

    /**
     * @return bool
     */
    public function isTemplateChanged()
    {
        return $this->template->isChanged();
    }

    /**
     * @return mixed
     */
    public function resetTemplate()
    {
        return $this->template->reset();
    }

    /**
     * @param string $html
     * @return bool
     */
    public function saveTemplate($html)
    {
        return $this->template->save($html);
    }

    /**
     * @param waOrder|int|array $data
     * @return string
     */
    public function renderPrintform($data)
    {
        $order = $data;
        if (!($order instanceof waOrder)) {
            $order = $this->getOrder($order, $this->order_options);
        }
        $view = $this->template->getView();

        $data = array(
            'settings' => $this->getSettings(),
            'order'    => $order,
        );
        $data = $this->preparePrintform($data, $view);
        $view->assign($data);

        return $this->template->display();
    }

    /**
     * @param array $data Associative array of data with keys:
     *   waOrder $data['order'] order
     *   array $data['settings'] settings of plugin
     * @param waView $view
     * @return mixed
     */
    public function preparePrintform($data, waView $view)
    {
        return $data;
    }
}
