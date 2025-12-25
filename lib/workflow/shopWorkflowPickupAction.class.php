<?php

class shopWorkflowPickupAction extends shopWorkflowCompleteAction
{
    private $pickup_require_pin;

    public function __construct($id, waWorkflow $workflow, $options = array())
    {
        parent::__construct($id, $workflow, $options);
        $this->state_id = 'completed';
        $this->options['html'] = true;
        $this->pickup_require_pin = wa()->getSetting('pickup_require_pin', null, 'shop');
    }

    protected function preExecute($params = null)
    {
        if ($this->pickup_require_pin) {
            $pin = waRequest::post('pin', 0, waRequest::TYPE_INT);
            $pickup_pin = $this->order_params_model->getOne($params, 'auth_pin');

            if (empty($pin) || $pin != $pickup_pin) {
                throw new waException(_w('PIN is not valid'));
            }
        }

        return parent::preExecute($params);
    }

    protected function getTemplateBasename($template = '')
    {
        return 'PickupAction.html';
    }

    public function getHTML($order_id)
    {
        $this->getView()->assign([
            'order' => $this->getOrder($order_id),
            'pickup_require_pin' => $this->pickup_require_pin,
        ]);

        return parent::getHTML($order_id);
    }
}
