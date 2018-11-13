<?php

class shopOrderSaveMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        $post = waRequest::post();

        if ($this->courier && $this->courier['rights_order_edit'] == 0) {
            throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
        }

        // Check courier access rights
        if ($this->courier) {
            $order_params_model = new shopOrderParamsModel();
            $courier_id = $order_params_model->getOne(ifset($post, 'id', null), 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
        }

        $post += array('shipping' => null);
        $post = $this->validate($post);

        if ($post) {
            $so = new shopOrder($post, array(
                'ignore_stock_validate'  => true,
                'items_format' => 'tree',
            ));

            try {
                $so->save();
            } catch (waException $ex) {
                $this->errors = $so->errors();
            }
        };
        $this->response = $this->createResponse();
    }

    protected function validate($post)
    {
        if (empty($post['items'])) {
            $this->errors[] = _w('Items not found');
        }

        //convert discount for shopOrder.
        if ($post['discount'] == 'true') {
            $post['discount'] = 'calculate';
        }

        if ($this->errors) {
            return false;
        } else {
            return $post;
        }
    }
}
