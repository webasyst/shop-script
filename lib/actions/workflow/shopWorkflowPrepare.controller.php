<?php
/**
  * Performed when user clicks action button on request page in backend.
  *
  * For actions with form, returns form HTML.
  * For actions without form, performs action and returns <script> tag to reload request page.
  * For actions that cannot be performed by backend users, throws an excepton.
  */
class shopWorkflowPrepareController extends waController
{
    public function execute() {
        if (! ( $order_id = waRequest::post('id', 0, 'int'))) {
            throw new waException('No order id given.');
        }
        if (! ( $action_id = waRequest::post('action_id'))) {
            throw new waException('No action id given.');
        }

        $workflow = new shopWorkflow();
        // @todo: check action availablity in state
        $action = $workflow->getActionById($action_id);
        if ($html = $action->getHTML($order_id)) {
            // display html
            echo $html;
        } else {
            // perform action and reload
            $result = $action->run($order_id);
            echo "<script>";
            if ($result['before_state_id'] != $result['after_state_id']) {
                echo "$.order_list.updateCounters({
    state_counters: { '".$result['before_state_id']."' : '-1', '".$result['after_state_id']."': '+1'}
});\n";
            }
            echo "$.order.reload();</script>";
        }
    }
}
