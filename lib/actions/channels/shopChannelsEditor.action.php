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
            $paid_date = date('Y-m-d', strtotime('-30 day'));
            $orders_collection = new shopOrdersCollection('search/params.sales_channel='.$channel['type'].':'.$channel['id']);
            $orders_collection->addWhere("o.paid_date >= '$paid_date'");
            $channel['orders'] = $orders_collection->getOrders();
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

        try {
            $this->setLayout(new shopBackendChannelsLayout());
            $channel_type = shopSalesChannelType::factory($channel['type']);
            $channel['type_available'] = $channel_type->get('available');
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
}
