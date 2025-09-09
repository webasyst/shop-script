<?php
/**
 * 
 */
class shopFrontendApiCategoriesController extends shopFrontApiJsonController
{
    public function get($token = null)
    {
        $parent_id = waRequest::get('parent_id', null, 'int');
        $depth = waRequest::get('depth', null, 'int');
        if ($depth !== null && $depth <= 0) {
            $depth = null;
        }
        $return_tree = waRequest::get('tree', null, 'int');

        $category_model = new shopCategoryModel();
        $cats = $category_model->getTree($parent_id, $depth);
        $cats = $this->formatCategories($cats);

        if ($return_tree) {
            $cats = $this->buildTree($cats);
        }

        $this->response['categories'] = $cats;
    }

    protected function formatCategories($cats)
    {
        $formatter = new shopFrontApiCategoryFormatter([
            'without_meta' => true,
        ]);
        $result = array();
        foreach ($cats as $c) {
            $result[] = $formatter->format($c);
        }
        return $result;
    }

    protected function buildTree($cats)
    {
        $stack = array();
        $result = array();
        foreach ($cats as $c) {
            $c['categories'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while ($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
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
                if ($stack[$l - 1]['id'] == $c['parent_id']) {
                    $stack[$l - 1]['categories'][$i] = $c;
                    $stack[] = & $stack[$l - 1]['categories'][$i];
                }
            }
        }
        return $result;
    }
}
