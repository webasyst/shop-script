<?php

class shopOrderCalculateMethod extends shopApiMethod
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

        $data = null;
        $post += array('shipping' => null);
        $post = $this->validate($post);

        if ($post) {
            $so = new shopOrder($post, array(
                'items_format' => 'tree',
                'items_extend_round' => true,
            ));

            $data['items'] = $this->formalize($so->items);
            $data['discount_description'] = $so->discount_description;
            $data['discount'] = $so->discount;
            $data['shipping'] = $so->shipping;
            $data['subtotal'] = $so->subtotal;
            $data['total'] = $so->total;
        };

        $this->response = $this->createResponse($data);
    }

    /**
     * @param $items
     * @return array
     */
    protected function formalize($items)
    {
        $m = new shopOrderItemsModel();
        $fields = $m->getMetadata();
        $fields[] = 'discount_description';

        // Keep only keys that exist in shop_order_items table
        $data = array();
        foreach ($items as $item) {
            $data[] = array_intersect_key($item, $fields);
        }

        return $data;
    }

    /**
     * @param $post
     * @return bool
     */
    protected function validate($post)
    {
        if (empty($post['id'])) {
            $this->errors[] = _w('Order id not found');
        }

        // 'true' is the legacy version of the API
        if (ifset($post, 'discount', null) == 'true') {
            $post['discount'] = 'calculate';
        }

        if ($this->errors) {
            return false;
        } else {
            return $post;
        }
    }
}
