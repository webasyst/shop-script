<?php

class shopWorkflowFulfillingAction extends shopWorkflowAction
{
    public function __construct($id, waWorkflow $workflow, $options = array())
    {
        parent::__construct($id, $workflow, $options);
        $this->options['html'] = true;
    }

    public function postExecute($order_id = null, $result = null)
    {
        $fulfillment_contact_id = waRequest::post('fulfillment_contact_id', 0, waRequest::TYPE_INT);
        if (is_array($order_id)) {
            $order_id = $order_id['order_id'];
        }

        if (!is_array($result)) {
            $result = [];
        }
        if ($fulfillment_contact_id) {
            $contact_model = new waContactModel();
            $contact = $contact_model->getByField(['id' => $fulfillment_contact_id, 'is_user' => 1]);
            if ($contact) {
                $result['text'] = _w('Responsible for the fulfillment').': '.htmlspecialchars(ifempty($contact, 'name', '('.$fulfillment_contact_id.')'));
            } else {
                $fulfillment_contact_id = null;
            }
            $result['update']['fulfillment_contact_id'] = $fulfillment_contact_id;
            $result['update']['params'] = ['fulfillment_contact_id' => $fulfillment_contact_id];
        }
        if (!$fulfillment_contact_id) {
            $fulfillment_contact_id = null;
            $result['text'] = _w('Responsible for the fulfillment').': '._w('None');
        }

        return parent::postExecute($order_id, $result);
    }

    public function getHTML($order_id)
    {
        $webasyst_users = [];
        $fulfillment_users = [];
        $users = shopRights::getUsers('orders', shopRightConfig::RIGHT_ORDERS_FULFILLMENT);

        foreach ($users as $_user) {
            if (ifset($_user, 'right', 'name', '') === 'orders' && ifset($_user, 'right', 'value', '') == shopRightConfig::RIGHT_ORDERS_FULFILLMENT) {
                $fulfillment_users[] = $_user;
            } else {
                $webasyst_users[] = $_user;
            }
        }

        $this->getView()->assign([
            'order' => $this->getOrder($order_id),
            'fulfillment_users' => $fulfillment_users,
            'webasyst_users' => $webasyst_users
        ]);

        return parent::getHTML($order_id);
    }
}
