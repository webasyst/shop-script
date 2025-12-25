<?php

class shopOrderSaveMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        $post = waRequest::post();

        if ($this->courier && $this->courier['rights_order_edit'] == 0) {
            throw new waAPIException('access_denied', _w('Access denied for the limited courier token.'), 403);
        }

        // Check courier access rights
        if ($this->courier) {
            $order_params_model = new shopOrderParamsModel();
            $courier_id = $order_params_model->getOne(ifset($post, 'id', null), 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', _w('Access denied for the limited courier token.'), 403);
            }
        }

        $post += array('shipping' => null);
        $post = $this->validate($post);

        if ($post) {
            $form = null;
            if (!empty($post['customer'])) {
                $form = new shopBackendCustomerForm();
                $form->setContactType(ifset($post, 'customer', 'contact_type', null), true);
                unset($post['customer']['contact_type']);
            }

            $so = new shopOrder($post, array(
                'customer_form' => $form,
                'ignore_stock_validate'  => true,
                'items_format' => 'tree',
            ));

            try {
                if (!empty($so->getData('stock_id'))) {
                    $items = (array) $so->getData('items');
                    foreach ($items as $item_id => $item) {
                        $items[$item_id]['stock_id'] = $so->getData('stock_id');
                    }
                    $so->setData('items', $items);
                }
                $so->save();
            } catch (waException $ex) {
                $this->errors = $so->errors();
            }
        };
        $this->response = $this->createResponse();
    }

    protected function validate($post)
    {
        if (empty($post['items']) && empty($post['id'])) {
            $this->errors[] = _w('Order items not found.');
        }

        //convert discount for shopOrder.
        if (ifset($post, 'discount', null) === 'true') {
            $post['discount'] = 'calculate';
        } else if (ifset($post, 'discount', '') === '') {
            if (!empty($post['id'])) {
                $post['discount'] = null; // keep previously saved discount
            }
        }
        if (isset($post['params']['channel_id']) && preg_match('#^([a-z0-9]+):(\d+)$#', $post['params']['channel_id'], $matches)) {
            $sales_channel_model = new shopSalesChannelModel();
            if ($channel = $sales_channel_model->getByField(['id' => $matches[2], 'type' => $matches[1]])) {
                $post['params']['sales_channel'] = $post['params']['channel_id'];
                $post['params']['sales_channel_name'] = ifempty($channel, 'name', null);
                $channel_params = (new shopSalesChannelParamsModel())->get($channel['id']);
                if ($stock_id = ifempty($channel_params, 'stock_id', null)) {
                    $post['stock_id'] = (int) $stock_id;
                }
            }
        }
        unset($post['params']['channel_id']);

        if ($this->errors) {
            return false;
        } else {
            return $post;
        }
    }
}
