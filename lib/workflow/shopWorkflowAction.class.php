<?php

class shopWorkflowAction extends waWorkflowAction
{

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
        $name = htmlspecialchars($this->getName());
        if (func_num_args()) {
            $attrs = func_get_arg(0);
        } else {
            $attrs = '';
        }
        if ($this->getOption('position') || $this->getOption('top')) {
            return '<a '.$attrs.' href="#" class="wf-action '.$this->getOption('button_class').'" data-action-id="'.$this->getId().'"><i class="icon16 '.$this->getOption('icon').'"></i>'.$name.'</a>';
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
            return '<a href="#" '.$attrs.' class="wf-action button '.$this->getOption('button_class').'" data-action-id="'.$this->getId().'" style="'.implode(' ', $style).'">'.$name.$icons.'</a>';
        }
    }

    public function getHTML($order_id)
    {
        if (!$this->getOption('html')) {
            return null;
        }
        $this->getView()->assign(array(
            'order_id' => $order_id,
            'action_id' => $this->getId()
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
            return;
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

        $order_log_model = new shopOrderLogModel();
        $data['id'] = $order_log_model->add($data);

        $update = isset($result['update']) ? $result['update'] : array();
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
        shopNotifications::send('order.'.$this->getId(), array(
            'order' => $order,
            'customer' => new waContact($order['contact_id']),
            'status' => $this->getWorkflow()->getStateById($data['after_state_id'])->getName(),
            'action_data' => $data
        ));

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
         * @param array[string]mixed $data
         * @param array[string]int $data['order_id']
         * @param array[string]int $data['action_id']
         * @param array[string]int $data['before_state_id']
         * @param array[string]int $data['after_state_id']
         * @param array[string]int $data['id'] Order log record id
         */
        wa('shop')->event('order_action.'.$this->getId(), $data);
        return $data;
    }

    /**
     * @param string $template suffix to add to template basename
     * @return string template file basename for this action. Can be overriden in subclasses.
     */
    protected function getTemplateBasename($template='')
    {
        return substr(get_class($this), 12).($template ? '_'.$template : '').$this->getView()->getPostfix();
    }

    /**
     * @return string dir to look template files in (path with trailing slash)
     */
    protected function getTemplateDir()
    {
        return $this->workflow->getPath('/templates/');
    }
}