<?php
/**
 * Used on Kanban view to determine which columns can a particular order be dragged into,
 * and which workflow action to perform in order to move the order.
 */
class shopOrdersAvailableActionsController extends waJsonController
{
    public function execute()
    {
        try {
            $order_id = waRequest::post('id', null, 'int');
            $this->response = $this->getStateActions($order_id);
        } catch (waException $e) {
            $this->response = [];
        }
    }

    protected function actionUsesForm($action, $order_id)
    {
        // Some actions can safely be used without additional data even though they use form
        switch ($action->getId()) {
            case 'delete':
            case 'ship':
            case 'pay':
            case 'cancel':
            case 'refund':
            //case 'capture': // untested
                return false;
        }

        if ($action->getOption('html')) {
            return true;
        }
        return !!$action->getHTML($order_id);
    }

    protected function getStateActions($order_id)
    {
        if (!$order_id) {
            throw new waException();
        }

        $order = new shopOrder($order_id);

        $workflow = new shopWorkflow();
        $actions_data = $workflow->getAvailableActions();
        $state_action_with_form = [];
        $state_action_no_form = [];

        foreach ($order->actions as $action) {
            $state_id = ifset($actions_data, $action->getId(), 'state', null);
            if (!$state_id || !empty($state_action_no_form[$state_id])) {
                continue;
            }
            try {
                if ($this->actionUsesForm($action, $order_id)) {
                    $state_action_with_form[$state_id] = [
                        'action_id' => $action->getId(),
                        'use_form' => true,
                    ];
                } else {
                    $state_action_no_form[$state_id] = [
                        'action_id' => $action->getId(),
                        'use_form' => false,
                    ];
                }
            } catch (Exception $e) {
                // ignore actions that err
                continue;
            }
        }

        return $state_action_no_form + $state_action_with_form;
    }
}
