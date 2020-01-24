<?php

/**
 * Performs workflow actions that can be performed by backend users.
 * Called by forms returned by shopWorkflowPrepareController.
 */
class shopWorkflowPerformController extends waJsonController
{
    public function execute()
    {
        if (!($order_id = waRequest::post('id', 0, 'int'))) {
            throw new waException('No order id given.');
        }
        if (!($action_id = waRequest::post('action_id'))) {
            throw new waException('No action id given.');
        }

        $user = wa()->getUser();
        if (!$user->isAdmin('shop') && !$user->getRights('shop', sprintf('workflow_actions.%s', $action_id))) {
            throw new waRightsException('Action not available for user');
        }

        $workflow = new shopWorkflow();
        // @todo: check action availability in state
        // @todo: run data validation
        /** @var shopWorkflowAction $action */
        $action = $workflow->getActionById($action_id);
        try {
            $this->response = $action->run($order_id);
        } catch (waException $ex) {
            $data = array();
            if ($action instanceof shopWorkflowAction) {
                $data = $action->getErrorData();
            }
            $this->setError($ex->getMessage(), $data);
        }
    }
}
