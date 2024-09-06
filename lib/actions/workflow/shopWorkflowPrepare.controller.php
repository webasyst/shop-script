<?php

/**
 * Performed when user clicks action button on request page in backend.
 *
 * For actions with form, returns form HTML.
 * For actions without form, performs action and returns <script> tag to reload request page.
 * For actions that cannot be performed by backend users, throws an exception.
 */
class shopWorkflowPrepareController extends waController
{
    public function execute()
    {
        if (!($order_id = waRequest::post('id', 0, 'int'))) {
            throw new waException('No order id given.');
        }
        if (!($action_id = waRequest::post('action_id'))) {
            throw new waException('No action id given.');
        }

        $workflow = new shopWorkflow();
        // @todo: check action availability in state
        /** @var shopWorkflowAction $action */
        $action = $workflow->getActionById($action_id);
        if (!$action) {
            throw new waRightsException('Action not available for user');
        }

        // Check access rights unless action allows form to be viewed even without access rights
        if (!$action->getOption('allow_form_no_rights')) {
            $user = wa()->getUser();
            if (!$user->isAdmin('shop') && !$user->getRights('shop', sprintf('workflow_actions.%s', $action_id))) {
                throw new waRightsException('Action not available for user');
            }
        }

        try {
            $html = $action->getHTML($order_id);
        } catch (SmartyCompilerException $e) {
            $msg = $e->getMessage();
            preg_match('/^Syntax Error in template ".*?" on line ([\d+]) "(.*?)" (.*?)$/i', $msg, $m);
            if ($m) {
                str_replace($m[0], '', $msg);
                $msg = sprintf_wp("Syntax error in the template of action “%s” in line %s “%s”. Reason: %s.", $action->getName(), $m[1], $m[2], $m[3]);
            }
            $msg .= ' ' . sprintf(_w("You can edit the template in <a href=\"?action=settings#/orderStates/\">order states settings</a>."));
            $msg = htmlentities($msg, ENT_QUOTES, 'utf-8');
            $html = <<<HTML
<script>
$.shop.alertError('{$msg}');
</script>
HTML;
        }

        if ($html) {
            // display html
            echo $html;
        } else {

            // perform action and reload
            $result = $action->run($order_id);

            // counters
            $order_model = new shopOrderModel();
            $state_counters = $order_model->getStateCounters();
            $pending_counters =
                (!empty($state_counters['new']) ? $state_counters['new'] : 0) +
                (!empty($state_counters['processing']) ? $state_counters['processing'] : 0) +
                (!empty($state_counters['paid']) ? $state_counters['paid'] : 0);

            $config = wa('shop')->getConfig();
            $currency = $config->getCurrency();
            $total_processing = wa_currency_html($order_model->getTotalSalesByInProcessingStates(), $currency, '%k{h}');
            // update app counter
            $config->setCount($state_counters['new']);

            $data = json_encode(array(
                'state_counters'   => $state_counters,
                'total_processing' => $total_processing,
                'common_counters'  => array(
                    'pending_counters' => $pending_counters,
                ),
            ));

            $script_add_review_widget = '';
            if ($action_id === 'complete') {
                $installer_app = wa()->getView()->getHelper()->installer;
                if ($installer_app && method_exists($installer_app, 'reviewWidget')) {
                    $script_add_review_widget = $installer_app->reviewWidget('app/shop');
                    $script_add_review_widget = '<script>$("#s-content").append('.json_encode($script_add_review_widget).');</script>';
                }
            }

            echo <<<HTML
<script>
    $.order_list.updateCounters({$data});
    $.order.reload();
</script>
{$script_add_review_widget}
HTML;

        }
    }
}
