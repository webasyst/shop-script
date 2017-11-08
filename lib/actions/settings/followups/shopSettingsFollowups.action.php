<?php

/**
 * Settings form for follow-ups, and submit controller for this form.
 */
class shopSettingsFollowupsAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id');
        $fm = new shopFollowupModel();

        $transports = $this->getTransports();

        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */

        // Save data when POST came
        if ($id && waRequest::post()) {
            if (waRequest::post('delete')) {

                $f = $fm->getById($id);
                if ($f) {
                    /**
                     * @event followup_delete
                     *
                     * Notify plugins about deleted followup
                     *
                     * @param array[string]int $params['id'] followup_id
                     * @return void
                     */
                    wa()->event('followup_delete', $f);
                    $fm->deleteById($id);
                }
                exit;
            }

            $followup = waRequest::post('followup');
            if ($followup && is_array($followup)) {
                $empty_row = $fm->getEmptyRow();
                $followup = array_intersect_key($followup, $empty_row) + $empty_row;
                unset($followup['id']);
                $followup['delay'] = ((float)str_replace(array(' ', ','), array('', '.'), ifset($followup['delay'], '3'))) * 3600;
                if (empty($followup['name'])) {
                    $followup['name'] = _w('<no name>');
                }

                $followup['from']    = $followup['from'] ? $followup['from'] : null;
                $followup['source'] = $followup['source'] ? $followup['source'] : null;

                if ($followup['from'] === 'other') {
                    $followup['from'] = waRequest::post('from');
                }

                // In restricted mail mode it's only allowed to use notifications
                // with default text. This is useful for demo and trial accounts.
                if ($config->getOption('restricted_mail')) {
                    if (isset($transports[$followup['transport']]['template'])) {
                        $followup['body'] = $transports[$followup['transport']]['template'];
                    } else {
                        throw new waRightsException();
                    }
                }

                if ($id && $id !== 'new') {
                    unset($followup['last_cron_time']);
                    $fm->updateById($id, $followup);
                    $just_created = false;
                } else {
                    $followup['last_cron_time'] = date('Y-m-d H:i:s');
                    $id = $fm->insert($followup);
                    $just_created = true;
                }

                $f = $fm->getById($id);
                if ($f) {
                    $f['just_created'] = $just_created;

                    /**
                     * Notify plugins about created or modified followup
                     * @event followup_save
                     * @param array[string]int $params['id'] followup_id
                     * @param array[string]bool $params['just_created']
                     * @return void
                     */
                    wa()->event('followup_save', $f);
                }
            }
        }

        // List of all follow-ups
        $followups = $fm->getAll('id');

        // Get data to show in form
        $followup = null;
        if ($id) {
            if (empty($followups[$id])) {
                if ($followups) {
                    $followup = reset($followups);
                }
            } else {
                $followup = $followups[$id];
            }
        }

        $test_orders = array();

        if (empty($followup)) {
            $followup = $fm->getEmptyRow();
            $followup['status'] = 1;
            if (isset($transports[$followup['transport']]['template'])) {
                $followup['body'] = $transports[$followup['transport']]['template'];
            }
        } else {
            // Orders used as sample data for testing
            $olm = new shopOrderLogModel();
            $order_ids = $olm
                ->select('DISTINCT order_id, datetime')
                ->where('after_state_id=s:state_id AND after_state_id != before_state_id', $followup)
                ->order('datetime DESC')
                ->limit(10)
                ->fetchAll('order_id');

            $om = new shopOrderModel();
            $test_orders = $om->getById(array_keys($order_ids));

            shopHelper::workupOrders($test_orders);
            $im = new shopOrderItemsModel();
            foreach ($im->getByField('order_id', array_keys($test_orders), true) as $i) {
                $test_orders[$i['order_id']]['items'][] = $i;
            }
            foreach ($test_orders as &$o) {
                $o['items'] = ifset($o['items'], array());
                $o['total_formatted'] = waCurrency::format('%{h}', $o['total'], $o['currency']);
            }
        }

        $workflow = new shopWorkflow();
        $states = $workflow->getAllStates();

        $this->view->assign('followup', $followup);
        $this->view->assign('followups', $followups);
        $this->view->assign('test_orders', $test_orders);
        $this->view->assign('last_cron', (int)wa()->getSetting('last_followup_cli'));
        $this->view->assign('cron_ok', ((int)wa()->getSetting('last_followup_cli') + 3600*36) > time());
        $this->view->assign('cron_command', 'php '.$config->getRootPath().'/cli.php shop followup');
        $this->view->assign('default_email_from', $config->getGeneralSettings('email'));
        $this->view->assign('routes', wa()->getRouting()->getByApp('shop'));
        $this->view->assign('transports', $transports);
        $this->view->assign('sms_from', $this->getSmsFrom());
        $this->view->assign('states', $states);

        $this->view->assign('backend_followup_edit', wa()->event('backend_followup_edit', $followup));
    }

    public function getTransports()
    {
        return array(
            'email' => array(
                'name' => _w('Email'),
                'icon' => 'email',
                'template' => '<p>'.sprintf( _w('Hi %s'), '{$customer->get("name", "html")}').'</p>

<p>'.sprintf( _w('Thank you for your recent order %s!'), '{$order.id_str}').'</p>

<p>'._w('We hope that you are happy with your purchase, and that the overall shopping experience was pleasant. Please let us know if you have any questions on your order, or if there is anything we can assist you with. We will be glad working with you again!').'</p>

<p>--<br>
{$wa->shop->settings("name")}<br>
<a href="mailto:{$wa->shop->settings("email")}">{$wa->shop->settings("email")}</a><br>
{$wa->shop->settings("phone")}<br></p>'
            ),
            'sms' => array(
                'name' => _w('SMS'),
                'icon' => 'mobile',
                'template' => sprintf( _w('Thank you for your recent order %s!'), '{$order.id_str}'). "\n" .
                    _w('We will be glad working with you again!'). "\n" .
                    '{$wa->shop->settings("name")}, {$wa->shop->settings("phone")}'
            )
        );
    }

    public function getSmsFrom()
    {
        $sms_config = wa()->getConfig()->getConfigFile('sms');
        $sms_from = array();
        foreach ($sms_config as $from => $options) {
            $sms_from[$from] = $from.' ('.$options['adapter'].')';
        }
        return $sms_from;
    }
}
