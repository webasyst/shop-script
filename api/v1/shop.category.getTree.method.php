<?php

class shopCategoryGetTreeMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $parent_id = waRequest::get('parent_id');
        $category_model = new shopCategoryModel();
        $cats = $category_model->getTree($parent_id, waRequest::get('depth', null, 'int'));

        $stack = array();
        $result = array();
        foreach ($cats as $c) {
            $c['categories'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($result);
                $result[$i] = $c;
                $stack[] = & $result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['categories']);
                $stack[$l - 1]['categories'][$i] = $c;
                $stack[] = & $stack[$l - 1]['categories'][$i];
            }
        }
        $this->response = $result;
        $this->response['_element'] = 'category';
    }
}