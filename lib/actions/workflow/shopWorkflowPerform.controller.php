<?php
/** 
 * Performs workflow actions that can be performed by backend users.
 * Called by forms returned by helpdeskWorkflowPrepareController.
 */
class shopWorkflowPerformController extends waJsonController
{
    public function execute()
    {
        if (! ( $order_id = waRequest::post('id', 0, 'int'))) {
            throw new waException('No order id given.');
        }
        if (! ( $action_id = waRequest::post('action_id'))) {
            throw new waException('No action id given.');
        }

        $workflow = new shopWorkflow();
        // @todo: check action availablity in state
        $action = $workflow->getActionById($action_id);
        $this->response = $action->run($order_id);
    }
}
