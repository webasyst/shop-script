<?php

/**
 * Class shopPrintformPlugin
 */
abstract class shopPrintformPlugin extends shopPlugin
{
    const TYPE_CHANGED = 'changed';
    const TYPE_ORIGINAL = 'original';
    private $templates = array();

    public function __construct($info)
    {
        parent::__construct($info);
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

    private function getTemplatePaths($type = null)
    {
        if (!$this->templates) {
            $this->templates = array(
                self::TYPE_CHANGED  => wa()->getDataPath('plugins/'.$this->id.'/template.html'),
                self::TYPE_ORIGINAL => $this->path.'/templates/actions/printform/template.html'
            );
        }
        return $type ? $this->templates[$type] : $this->templates;
    }

    public function getTemplatePath()
    {
        foreach ($this->getTemplatePaths() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }

    /**
     * @return string HTML code of template
     */
    public function getTemplate()
    {
        return ($path = $this->getTemplatePath()) ? file_get_contents($path) : '';
    }

    /**
     * @return bool
     */
    public function isTemplateChanged()
    {
        return file_exists($this->getTemplatePaths(self::TYPE_CHANGED));
    }

    public function resetTemplate()
    {
        waFiles::delete(dirname($this->getTemplatePaths(self::TYPE_CHANGED)));
        return $this->getTemplate();
    }

    /**
     * @param $html
     */
    public function saveTemplate($html)
    {
        $paths = $this->getTemplatePaths();
        $exclude = array(
            '@PrintformDisplay\.html$@',
            '@\.js$@'
        );
        waFiles::copy(dirname($paths['original']), dirname($paths['changed']), $exclude);
        file_put_contents($paths['changed'], $html);
    }

    /**
     * Safe for rights getting order data
     * @param $order_id
     * @param $options
     * @throws waException
     * @return waOrder
     */
    public function getOrder($order_id, $options = array())
    {
        switch (wa()->getEnv()) {
            case 'backend':
                if (!wa()->getUser()->getRights('shop', !$order_id ? 'settings' : 'orders')) {
                    throw new waException(_w("Access denied"));
                }
                if ($order_id) {
                    $order = shopPayment::getOrderData($order_id, $this);
                    if (!$order) {
                        throw new waException('Order not found', 404);
                    }
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
                //frontend
                if ($order_id) {
                    $order = shopPayment::getOrderData($order_id, $this);
                }
                if (empty($order)) {
                    throw new waException('Order not found', 404);
                }
                break;
        }
        if (!empty($options['items'])) {
            $this->extendItems($order, $options['items']);
        }
        return $order;
    }


    /**
     *
     * @param waOrder $order
     * @param $params
     * @return array
     */
    private function extendItems(&$order, $params)
    {
        $items = $order->items;
        $product_model = new shopProductModel();
        foreach ($items as & $item) {
            $data = $product_model->getById($item['product_id']);
            $item['tax_id'] = ifset($data['tax_id']);
            $item['currency'] = $order->currency;
        }

        unset($item);
        shopTaxes::apply($items, array(
            'billing'  => $order->billing_address,
            'shipping' => $order->shipping_address,
        ), $order->currency);

        if ($order->discount) {
            if ($order->total + $order->discount - $order->shipping > 0) {
                $k = 1.0 - ($order->discount) / ($order->total + $order->discount - $order->shipping);
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
     * @param waOrder|int $order
     * @return string HTML form
     * @throws waException
     */
    public function renderForm($order)
    {
        if (!($order instanceof waOrder)) {
            $order = $this->getOrder($order, array('items' => true));
        }
        $view = wa()->getView();
        $view->assign(array(
            'settings' => $this->getSettings(),
            'order'    => $order,
        ));
        $this->prepareForm($order, $view);
        return $view->fetch($this->getTemplatePath());
    }

    /**
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
                $subject = sprintf(_w("Printform %s"), $this->getName());
                $message = new waMailMessage($subject, $this->renderForm($order));
                $message->setTo(array($email));

                $general = wa('shop')->getConfig()->getGeneralSettings();
                if (!empty($general['email'])) {
                    $message->setFrom($general['email'], $general['name']);
                }
                if ($message->send()) {
                    $result = true;
                    $log = sprintf(_w("Printform <strong>%s</strong> sent to customer."), $this->getName());
                    $order_log_model = new shopOrderLogModel();
                    $order_log_model->add(array(
                        'order_id'        => $order->id,
                        'contact_id'      => null,
                        'action_id'       => '',
                        'text'            => '<i class="icon16 email"></i> '.$log,
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
}
