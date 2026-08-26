<?php
/**
 * Form to create new or edit existing sales channel
 */
class shopChannelsEditorAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        } elseif (wa()->whichUI() == '1.3') {
            $url = wa()->getConfig()->getRootUrl().wa()->getConfig()->getBackendUrl().'/shop/?action=saleschannels';
            $this->redirect($url);
        }

        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel_params_model = new shopSalesChannelParamsModel();

        $id = waRequest::param('id', null, 'string');

        if ($id) {
            if (strpos($id, ':') !== false) {
                list($_, $id) = explode(':', $id, 2);
            }
            $id = (int) $id;
            if ($id > 0) {
                $channel = $sales_channel_model->getById($id);
            }
            if (empty($channel)) {
                throw new waException('Channel not found', 404);
            }
            $channel['params'] = $sales_channel_params_model->get($id);
        } else {
            $channel = [
                'status' => '1',
                'orders' => [],
            ] + $sales_channel_model->getEmptyRow();
            $channel['type'] = waRequest::param('type_id', null, 'string');
            $channel['params'] = [];
            if (!shopLicensing::isPremium()) {
                $by_type = $sales_channel_model->getByField('type', $channel['type'], true);
                $channel['type_count'] = count($by_type);
            }
        }

        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();
        $this->view->assign('currency', $config->getCurrency());

        $this->assignSales($channel);

        try {
            $this->setLayout(new shopBackendChannelsLayout());
            $channel_type = shopSalesChannelType::factory($channel['type']);
            $channel['type_available'] = $channel_type->get('available');
            $channel['fullname'] = $channel_type->get('fullname');
            $channel['demo_link'] = $channel_type->get('demo_link');
            $channel['menu_icon'] = $channel_type->get('menu_icon');
            $this->view->assign([
                'channel' => $channel,
                'channel_form' => $channel_type->getFormHtml($channel),
            ]);
        } catch (Exception $exception) {
            $this->view->assign([
                'channel' => $channel,
                'channel_form' => '<h4>'._w('Unsupported channel type.').'</h4>',
                'channel_orders' => []
            ]);
        }
    }

    private function assignSales(array $channel) {
        $show_sales = false;
        $sales_data = [];
        if ($this->getRights('reports') && !empty($channel['id'])) {
            $start_date = date('Y-m-d', strtotime('-30 day'));
            $sales_channel = $channel['type'].':'.$channel['id'];
            $sales_data = $this->getSalesData($sales_channel, $start_date);
            foreach ($sales_data as $s) {
                if ($s['sales'] > 0) {
                    $show_sales = true;
                    break;
                }
            }
        }
        $this->view->assign('show_sales', $show_sales);
        $this->view->assign('sales_data', $sales_data);
    }

    private function getSalesData($sales_channel, $start_date)
    {
        $graph_data = array();

        $sales_by_day = (new shopOrderModel())->getSalesByChannel($sales_channel, $start_date);

        $date = strtotime($start_date);
        while ($date <= time()) {
            $date = date('Y-m-d', $date);
            $data = ifset($sales_by_day, $date, []);

            $graph_data[] = [
                'date' => str_replace('-', '', $date),
                'sales' => (float)ifset($data, 'sales', 0),
                'profit' => (float)ifset($data, 'profit', 0),
                'loss' => (float)ifset($data, 'profit', 0),
            ];
            $date = strtotime($date." +1 day");
        }

        return $graph_data;
    }
}
