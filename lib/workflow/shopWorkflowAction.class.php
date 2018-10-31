<?php

class shopWorkflowAction extends waWorkflowAction
{
    public $original = false;
    protected $state_id;
    protected $extends;
    /**
     * @var shopOrderModel
     */
    protected $order_model;

    /**
     * @var shopOrderParamsModel
     */
    protected $order_params_model;

    /**
     * @var shopOrderLogModel
     */
    protected $order_log_model;

    /**
     * @var shopOrderItemsModel
     */
    protected $order_items_model;

    /**
     * @param string $id id as stored in database
     * @param waWorkflow $workflow
     * @param array $options option => value
     */
    public function __construct($id, waWorkflow $workflow, $options = array())
    {
        parent::__construct($id, $workflow, isset($options['options']) ? $options['options'] : array());

        $this->order_model = new shopOrderModel();
        $this->order_params_model = new shopOrderParamsModel();
        $this->order_log_model = new shopOrderLogModel();
        $this->order_items_model = new shopOrderItemsModel();

        if (isset($options['name'])) {
            $this->name = waLocale::fromArray($options['name']);
        }
        if (isset($options['state'])) {
            $this->state_id = $options['state'];
        }
        if (isset($options['extends'])) {
            $this->extends = $options['extends'];
        }

        if (empty($this->options['log_record'])) {
            $this->options['log_record'] = $this->getName();
        }
    }

    public function isAvailable($order)
    {
        return true;
    }

    public function getDefaultOptions()
    {
        return array(
            'html' => false,
        );
    }

    /**
     * @return string
     */
    public function getButton()
    {
        $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'utf-8');
        if (func_num_args()) {
            $attrs = func_get_arg(0);
        } else {
            $attrs = '';
        }
        $description = htmlspecialchars($this->getOption('description'), ENT_QUOTES, 'utf-8');
        if ($this->getOption('position') || $this->getOption('top')) {
            return <<<HTML
<a {$attrs} href="#" class="wf-action {$this->getOption('button_class')}" data-action-id="{$this->getId()}" title="{$description}">
    <i class="icon16 {$this->getOption('icon')}"></i>{$name}
</a>
HTML;
        } else {
            if ($this->getOption('icon')) {
                $icons = (array)$this->getOption('icon');
                foreach ($icons as &$icon) {
                    $icon = '<i class="icon16 '.$icon.'"></i>';
                }
                unset($icon);
                $icons = implode('', $icons);
            } else {
                $icons = '';
            }
            $style = array();
            if ($this->getOption('border_color')) {
                $style[] = 'border-color: #'.$this->getOption('border_color');
            }
            $style = implode(' ', $style);
            $class = array(
                'wf-action',
                'button',
                $this->getOption('button_class'),
            );
            $class = implode(' ', array_filter($class));
            return <<<HTML
<a href="#" {$attrs} class="{$class}" data-action-id="{$this->getId()}" style="{$style}" title="{$description}">{$name}{$icons}</a>
HTML;
        }
    }

    public function getHTML($order_id)
    {
        $action_id = $this->getId();
        $data = array();
        $data['order_id'] = $order_id;
        $data['action_id'] = $this->getId();
        /**
         * @event order_action_form.callback
         * @event order_action_form.pay
         * @event order_action_form.ship
         * @event order_action_form.process
         * @event order_action_form.delete
         * @event order_action_form.restore
         * @event order_action_form.complete
         * @event order_action_form.comment
         *
         * @param array [string]mixed $data
         * @param array [string]int $data['order_id']
         * @param array [string]int $data['action_id']
         *
         * @return array[string]string $return[%plugin_id%] html output
         */
        $html = wa('shop')->event('order_action_form.'.$action_id, $data);

        if (empty($html) && !$this->getOption('html')) {
            return null;
        }
        $this->getView()->assign(array(
            'order_id'     => $order_id,
            'action_id'    => $action_id,
            'plugins_html' => $html,
        ));

        return $this->display();
    }

    /**
     * Does all the actual work this action needs to do.
     * (declared as public for historical reasons only)
     * @param mixed $params implementation-specific parameter passed to $this->run()
     * @return mixed null if this action failed; any data to pass to $this->postExecute() if completed successfully.
     */
    public function execute($params = null)
    {
        return true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        if (!$result) {
            return null;
        }

        $order = $this->getOrder($order_id);

        $data = is_array($result) ? $result : array();
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $data['order_id'] = $order_id;
        $data['action_id'] = $this->getId();

        $data['before_state_id'] = $order['state_id'];
        if ($this->state_id) {
            $data['after_state_id'] = $this->state_id;
        } else {
            $data['after_state_id'] = $order['state_id'];
        }

        if (wa()->getEnv() == 'api' && waRequest::param('api_courier')) {
            $courier = waRequest::param('api_courier');
            $data['contact_id'] = ifempty($courier['contact_id'], 0);
            $data['params']['actor_courier_name'] = $courier['name'];
            $data['params']['actor_courier_id'] = $courier['id'];
        }

        $data['id'] = $this->order_log_model->add($data);

        $update = isset($result['update']) ? $result['update'] : array();

        if (!empty($order['unsettled']) && (wa()->getEnv() == 'backend')) {
            $update['unsettled'] = 0;
        }

        $update['update_datetime'] = date('Y-m-d H:i:s');
        $data['update'] = $update;

        if ($this->state_id) {
            $update['state_id'] = $this->state_id;
        }
        $this->order_model->updateById($order['id'], $update);
        $order = $update + $order;

        if (isset($update['params'])) {
            $this->order_params_model->set($order['id'], $update['params'], false);
        }
        $order['params'] = $this->order_params_model->get($order_id);

        // send notifications
        $silent = false;
        if ((wa()->getEnv() == 'backend') && (waRequest::post('notifications') == 'silent')) {
            $silent = true;
        }

        if (!$silent) {
            shopNotifications::send('order.'.$this->getId(), array(
                'order'       => $order,
                'customer'    => new waContact($order['contact_id']),
                'status'      => $this->getWorkflow()->getStateById($data['after_state_id'])->getName(),
                'action_data' => $data
            ));
        }

        // Clear sales report cache if anything happens to a paid order
        if (!empty($order['paid_date'])) {
            $sales_model = new shopSalesModel();
            if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'paid') {
                $sales_model->deletePeriod($order['paid_date']);
            } else {
                !empty($order['create_datetime']) && $sales_model->deletePeriod($order['create_datetime']);
            }
        }

        /**
         * @event order_action.callback
         * @event order_action.pay
         * @event order_action.ship
         * @event order_action.process
         * @event order_action.delete
         * @event order_action.restore
         * @event order_action.complete
         * @event order_action.comment
         *
         * @param array [string]mixed $data
         * @param array [string]int $data['order_id']
         * @param array [string]int $data['action_id']
         * @param array [string]int $data['before_state_id']
         * @param array [string]int $data['after_state_id']
         * @param array [string]int $data['id'] Order log record id
         * @param array [string]mixed $data['callback_transaction_data'] payment gateway callback formalized data for order_action.callback event
         * @param array [string][string]string $data['callback_transaction_data']['plugin']
         * @param array [string][string]mixed $data['callback_transaction_data']['merchant_id']
         * @param array [string][string]string $data['callback_transaction_data']['date_time'] datetime in 'Y-m-d H:i:s' format
         * @param array [string][string]string $data['callback_transaction_data']['update_datetime'] datetime in 'Y-m-d H:i:s' format
         * @param array [string][string]mixed $data['callback_transaction_data']['order_id']
         * @param array [string][string]string $data['callback_transaction_data']['type'] callback operation type waPayment::OPERATION_*
         * @param array [string][string]string $data['callback_transaction_data']['state'] callback operation state waPayment::STATE_*
         *
         */
        wa('shop')->event('order_action.'.$this->getId(), $data);
        return $data;
    }

    /**
     * @param string $template suffix to add to template basename
     * @return string template file basename for this action. Can be overridden in subclasses.
     */
    protected function getTemplateBasename($template = '')
    {
        $name = substr(get_class($this), 12).($template ? '_'.$template : '').$this->getView()->getPostfix();
        $template_path = $this->getTemplateDir().$name;
        if (!$this->getView()->templateExists($template_path)) {
            $name = 'Action'.$this->getView()->getPostfix();
        }
        return $name;
    }

    /**
     * @return string dir to look template files in (path with trailing slash)
     */
    protected function getTemplateDir()
    {
        return $this->workflow->getPath('/templates/');
    }

    /**
     * @return shopConfig
     */
    protected function getConfig()
    {
        static $config;
        if (empty($config)) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
        }
        return $config;
    }

    /**
     * @param $order_id
     * @return null|waPayment|waIPaymentRefund
     * @throws waException
     */
    protected function getPaymentPlugin($order_id)
    {
        $plugin = null;
        $payment_id = $this->order_params_model->getOne($order_id, 'payment_id');
        if ($payment_id) {
            try {
                $plugin = shopPayment::getPlugin(null, $payment_id);
            } catch (waException $ex) {
                //log it
            }
        }
        return $plugin;
    }

    /**
     * @param int|array $order
     * @return null|waShipping
     * @throws waException
     */
    protected function getShippingPlugin($order)
    {
        $plugin = null;
        if (is_array($order)) {
            if (isset($order['params'])) {
                $shipping_id = ifset($order['params']['shipping_id']);
            } else {
                $order_id = $order['id'];
            }
        } else {
            $order_id = $order;
        }
        if (!empty($order_id) && empty($shipping_id)) {
            $shipping_id = $this->order_params_model->getOne($order_id, 'shipping_id');
        }
        if (!empty($shipping_id)) {
            try {
                $plugin = shopShipping::getPlugin(null, $shipping_id);
            } catch (waException $ex) {
                //TODO log it
            }
        }
        return $plugin;
    }

    protected function getOrder(&$order_id, $extend = false)
    {
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];

            if ($extend) {
                $order += $this->order_model->getById($order_id);
            }
        } else {
            $order = $this->order_model->getById($order_id);
        }
        return $order;
    }

    protected function waLog($action, $order_id, $subject_contact_id = null, $contact_id = null)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add($action, $order_id, $subject_contact_id, $contact_id);
    }

    /**
     * @param string $state
     * @param array|int $order_id
     * @param array $params
     * @return array
     */
    public function setPackageState($state, $order_id, $params = array())
    {
        $order = $this->getOrder($order_id, true);

        if ($order && ($shipping_plugin = $this->getShippingPlugin($order))) {

            $order['items'] = $this->order_items_model->getItems($order['id']);
            $order['params'] = $this->order_params_model->get($order['id']);

            $wa_order = shopShipping::getOrderData($order, $shipping_plugin);
            if (method_exists($shipping_plugin, 'setPackageState')) {
                try {

                    $data = $shipping_plugin->setPackageState($wa_order, $state, $params);
                    if ($data !== null) {
                        if (is_array($data)) {
                            switch ($state) {
                                case waShipping::STATE_CANCELED:
                                    $icon = '<i class="icon16 trash"></i> ';
                                    $template = _w("Order shipping via <strong>%s</strong> service was canceled.");
                                    break;
                                case waShipping::STATE_DRAFT:
                                    $icon = '<i class="icon16 cheatsheet"></i> ';
                                    $template = _w("Order is being prepared for shipping via <strong>%s</strong> service.");
                                    break;
                                case waShipping::STATE_READY:
                                    $icon = '<i class="icon16 agreement"></i> ';
                                    $template = _w("Order is ready for shipping via <strong>%s</strong> service.");
                                    break;
                                default:
                                    $icon = '<i class="icon16 export"></i> ';
                                    $template = _w("Order details were sent to shipping service <strong>%s</strong>.");
                                    break;
                            }

                            $text = $icon.' '.sprintf($template, $wa_order->shipping_name);

                            if (isset($data['view_data'])) {
                                $text .= '<br/>'.$data['view_data'];
                                unset($data['view_data']);
                            }

                            $order_params = array();
                            foreach ($data as $key => $value) {
                                if ($key === 'tracking_number') {
                                    $order_params['tracking_number'] = $value;
                                }
                                $order_params['shipping_data_'.$key] = $value;
                            }

                            if ($order_params) {
                                $this->order_params_model->set($order['id'], $order_params, false);
                            }
                        } else {
                            if ($data === false) {

                            } else {
                                $text = '<i class="icon16 no"></i> ';
                                $template = _w("An error has occurred during the interaction with shipping service <strong>%s</strong>.");

                                $text .= sprintf($template, $wa_order->shipping_name);
                                $text .= "\n";
                                $text .= $data;
                            }
                        }


                    }
                } catch (Exception $ex) {
                    waLog::log($ex->getMessage(), 'shop/workflow.log');
                }
            }
        }
        $result = compact('text');

        if (!empty($params['log']) && $result) {
            $result['order_id'] = $order_id;
            $result['action_id'] = $this->getId();

            $result['before_state_id'] = $order['state_id'];

            $result['after_state_id'] = $order['state_id'];

            if (wa()->getEnv() == 'api' && waRequest::param('api_courier')) {
                $courier = waRequest::param('api_courier');
                $result['contact_id'] = ifempty($courier['contact_id'], 0);
                $result['params']['actor_courier_name'] = $courier['name'];
                $result['params']['actor_courier_id'] = $courier['id'];
            }

            return $this->order_log_model->add($result);
        }
        return $result;
    }

    protected function getShippingFields($order_id, $state)
    {
        $controls = array();
        if ($shipping_plugin = $this->getShippingPlugin($order_id)) {
            $order = $this->getOrder($order_id, true);

            $order['items'] = $this->order_items_model->getItems($order['id']);
            $order['params'] = $this->order_params_model->get($order['id']);

            $params = array();
            $params['namespace'] = 'shipping_data';
            $params['title_wrapper'] = '%s';
            $params['description_wrapper'] = '<br/><span class="hint">%s</span>';
            $params['control_wrapper'] = <<<HTML
<div class="field">
    <div class="name">%s</div>
    <div class="value">%s %s</div>
</div>
HTML;
            $params['control_separator'] = '</div><div class="value">';

            $wa_order = shopShipping::getOrderData($order, $shipping_plugin);
            if ($shipping_fields = $shipping_plugin->getStateFields($state, $wa_order)) {
                foreach ($shipping_fields as $name => $control) {
                    if (!empty($order['params']['shipping_data_'.$name])) {
                        $control['value'] = $order['params']['shipping_data_'.$name];
                    }
                    $control = array_merge($control, $params);
                    $controls[$name] = waHtmlControl::getControl($control['control_type'], $name, $control);
                }
            }
        }
        return $controls;
    }
}
