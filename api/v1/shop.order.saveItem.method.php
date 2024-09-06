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
                $order_id = $item['order_id'];

                // Check courier access rights
                if ($this->courier) {
                    $order_params_model = new shopOrderParamsModel();
                    $courier_id = $order_params_model->getOne($item['order_id'], 'courier_id');
                    if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                        throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
                    }
                }

                //formatting for shopOrder
                if ($item['type'] == 'product') {
                    $item_data = array(
                        'item_id'  => $post['item_id'],
                        'quantity' => $post['quantity'],
                    );
                    if (isset($post['codes']) && is_array($post['codes'])) {
                        $item_data['codes'] = $post['codes'];
                    }
                } else {
                    //if need delete service
                    $product_item = $soim->getById($item['parent_id']);
                    $item_data = array(
                        'item_id'  => $product_item['id'],
                        'quantity' => $product_item['quantity'],
                        'services' => array(
                            array(
                                'item_id'  => $post['item_id'],
                                'quantity' => $post['quantity'],
                            ),
                        ),
                    );
                }
                $data = [
                    'id' => $order_id,
                    'items' => [$item_data],
                    'discount' => 'calculate',
                    'shipping' => null,
                ];
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
            $this->errors[] = _w('No order items specified.');
        }

        if ($this->errors) {
            return false;
        } else {
            return true;
        }
    }
}
