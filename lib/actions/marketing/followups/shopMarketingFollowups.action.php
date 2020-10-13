<?php

/**
 * Settings form for follow-ups, and submit controller for this form.
 */
class shopMarketingFollowupsAction extends shopMarketingSettingsViewAction
{
    public function execute()
    {
        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();
        $transports = $this->getTransports();

        $fm = new shopFollowupModel();
        $followups = $fm->getAll('id');

        $id = waRequest::param('id');

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


        if ($id == 'create') {
            $followup = null;
        } elseif ($id && !empty($followups[$id])) {
            $followup = $followups[$id];
        } elseif (empty($id) && $followups) {
            reset($followups);
            $id = key($followups);
            $followup = ifempty($followups, $id, null);
        }

        $test_orders = array();

        if (empty($followup)) {
            $id = null;
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

        $all_sources = false;
        $backend_source = false;
        $sources = array();
        if (empty($followup['id'])) {
            $all_sources = true;
        } else {
            $followup_sources_model = new shopFollowupSourcesModel();
            $saved_sources = $followup_sources_model->getByField('followup_id', $followup['id'], true);
            if (empty($saved_sources)) {
                $all_sources = true;
            } else {
                foreach ($saved_sources as $source) {
                    if ($source['source'] == 'backend') {
                        $backend_source = true;
                    } elseif ($source['source'] == 'all_sources' || !isset($source['source'])) {
                        $all_sources = true;
                    } else {
                        $sources[] = $source['source'];
                    }
                }
            }
        }

        $routes = wa()->getRouting()->getByApp('shop');
        $domains = wa()->getRouting()->getAliases();
        $active_domains = array();
        foreach ($domains as $mirror => $domain) {
            if (isset($routes[$domain])) {
                $active_domains[$domain][] = $mirror;
            }
        }

        $this->view->assign('followup', $followup);
        $this->view->assign('followups', $followups);
        $this->view->assign('test_orders', $test_orders);
        $this->view->assign('last_cron', (int)wa()->getSetting('last_followup_cli'));
        $this->view->assign('cron_ok', ((int)wa()->getSetting('last_followup_cli') + 3600*36) > time());
        $this->view->assign('cron_command', 'php '.$config->getRootPath().'/cli.php shop followup');
        $this->view->assign('default_email_from', $config->getGeneralSettings('email'));
        $this->view->assign('all_sources', $all_sources);
        $this->view->assign('backend_source', $backend_source);
        $this->view->assign('sources', $sources);
        $this->view->assign('active_domains', $active_domains);
        $this->view->assign('routes', $routes);
        $this->view->assign('transports', $transports);
        $this->view->assign('sms_from', $this->getSmsFrom());
        $this->view->assign('states', $states);

        $this->view->assign('backend_followup_edit', wa('shop')->event('backend_followup_edit', $followup));
    }

    public static function getTransports()
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
