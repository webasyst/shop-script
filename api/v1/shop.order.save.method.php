<?php

class shopOrderSaveMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        if ($this->courier && $this->courier['rights_order_edit'] == 0) {
            throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
        }

        $post = waRequest::post();
        $post += array('shipping' => null);
        $post = $this->validate($post);
        if ($post) {
            $so = new shopOrder($post, array(
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

        //convert discount for shopOrder (sting to bool)
        if ($post['discount'] == 'true') {
            $post['discount'] = true;
        }

        if ($this->errors) {
            return false;
        } else {
            return $post;
        }
    }
}
