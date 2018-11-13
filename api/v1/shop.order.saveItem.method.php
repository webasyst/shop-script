<?php

class shopOrderSaveItemMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        if ($this->courier && $this->courier['rights_order_edit'] == 0) {
            throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
        }

        $post = waRequest::post();
        if ($this->validate($post)) {
            $soim = new shopOrderItemsModel();
            $item = $soim->getById($post['item_id']);

            if (isset($item['order_id'])) {

                // Check courier access rights
                if ($this->courier) {
                    $order_params_model = new shopOrderParamsModel();
                    $courier_id = $order_params_model->getOne($item['order_id'], 'courier_id');
                    if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                        throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
                    }
                }

                $data['id'] = $item['order_id'];
                //formatting for shopOrder
                if ($item['type'] == 'product') {
                    $data['items'][] = array(
                        'item_id'  => $post['item_id'],
                        'quantity' => $post['quantity'],
                    );
                } else {
                    //if need delete service
                    $product = $soim->getById($item['parent_id']);
                    $data['items'][] = array(
                        'item_id'  => $product['id'],
                        'quantity' => $product['quantity'],
                        'services' => array(
                            array(
                                'item_id'  => $post['item_id'],
                                'quantity' => $post['quantity'],
                            ),
                        ),
                    );
                }
                $data['discount'] = 'calculate';
                $data += array('shipping' => null);
                $so = new shopOrder($data, array(
                    'ignore_stock_validate'  => true,
                    'items_format' => 'tree',
                    'items_delta'  => true,
                ));

                try {
                    $so->save();
                } catch (waException $ex) {
                    $this->errors = $so->errors();
                }
            }
        };
        $this->response = $this->createResponse();
    }

    /**
     * @param array $post
     * @return bool
     */
    protected function validate($post)
    {
        if (empty($post['item_id'])) {
            $this->errors[] = _w('No items in the request');
        }

        if ($this->errors) {
            return false;
        } else {
            return true;
        }
    }
}
