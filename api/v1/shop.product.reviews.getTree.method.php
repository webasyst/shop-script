<?php

class shopProductReviewsGetTreeMethod extends waAPIMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);

        if (!$product) {
            throw new waAPIException('invalid_param', 'Product not found', 404);
        }

        $parent_id = waRequest::get('parent_id');

        $reviews_model = new shopProductReviewsModel();
        $reviews = $reviews_model->getTree($parent_id, waRequest::get('depth', null, 'int'), 'product_id = '.(int)$product_id);

        $stack = array();
        $result = array();
        foreach ($reviews as $r) {
            $r['comments'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while($l > 0 && $stack[$l - 1]['depth'] >= $r['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($result);
                $result[$i] = $r;
                $stack[] = & $result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['comments']);
                $stack[$l - 1]['comments'][$i] = $r;
                $stack[] = & $stack[$l - 1]['comments'][$i];
            }
        }

        $this->response = $result;
        $this->response['_element'] = 'review';
    }
}
