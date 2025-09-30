<?php

/**
 * Class shopFrontendApiProductController
 */
class shopFrontendApiProductController extends shopFrontendApiProductsSearchController
{
    // handles both GET and POST requests
    public function get()
    {
        parent::get();
        if (empty($this->response['products'])) {
            throw new waAPIException('not_found', _w('Product not found.'), 404);
        }
        $product = reset($this->response['products']);

        if (wa('shop')->getConfig()->getOption('can_use_smarty') && !empty($product['description'])) {
            $view = wa('shop')->getView();
            $view->assign('product', $product);
            $product['description'] = $view->fetch('string:'.$product['description']);
        }

        $this->response = $product;
    }

    // used by parent::get()
    protected function getCollectionHash()
    {
        $id = $this->getRequest()->param('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            throw new waAPIException('invalid_param', _w('An “id” parameter value is required.'));
        }
        return 'id/'.$id;
    }

    // used by parent::get()
    protected function getFilters()
    {
        return [];
    }

    // used by parent::get()
    protected function getOffsetLimit()
    {
        return [0, 1];
    }

    // used by parent::get()
    protected function getCollectionFields()
    {
        $fields = array_fill_keys(parent::getCollectionFields(), 1);
        $fields += [
            'description' => 1,
            'meta_title' => 1,
            'meta_keywords' => 1,
            'meta_description' => 1,
            'images' => 1,
            'skus' => 1,
            'stock_counts' => 1,
        ];
        return array_keys($fields);
    }
}
