<?php

class shopWorkflowAction extends waWorkflowAction
{

    public $original = false;
    protected $state_id;

    /**
     * @param string $id id as stored in database
     * @param waWorkflow $workflow
     * @param array $options option => value
     */
    public function __construct($id, waWorkflow $workflow, $options = array())
    {
        parent::__construct($id, $workflow, $options['options']);
        if (isset($options['name'])) {
            $this->name = waLocale::fromArray($options['name']);
        }
        if (isset($options['state'])) {
            $this->state_id = $options['state'];
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

    public function getButton()
    {
        $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'utf-8');
        if (func_num_args()) {
            $attrs = func_get_arg(0);
        } else {
            $attrs = '';
        }
        if ($this->getOption('position') || $this->getOption('top')) {
            return <<<HTML
<a {$attrs} href="#" class="wf-action {$this->getOption('button_class')}" data-action-id="{$this->getId()}">
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
<a href="#" {$attrs} class="{$class}" data-action-id="{$this->getId()}" style="{$style}">{$name}{$icons}</a>
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
    public function execute($oder_id = null)
    {
        return true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        if (!$result) {
            return null;
        }
        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        $data = is_array($result) ? $result : array();
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
        $order_log_model = new shopOrderLogModel();
        $data['id'] = $order_log_model->add($data);

        $update = isset($result['update']) ? $result['update'] : array();

        if (!empty($order['unsettled']) && (wa()->getEnv() == 'backend')) {
            $update['unsettled'] = 0;
        }

        $update['update_datetime'] = date('Y-m-d H:i:s');
        $data['update'] = $update;

        if ($this->state_id) {
            $update['state_id'] = $this->state_id;
        }
        $order_model->updateById($order['id'], $update);
        $order = $update + $order;

        $order_params_model = new shopOrderParamsModel();
        if (isset($update['params'])) {
            $order_params_model->set($order['id'], $update['params'], false);
        }
        $order['params'] = $order_params_model->get($order_id);

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
        }
        return $config;
    }
}
