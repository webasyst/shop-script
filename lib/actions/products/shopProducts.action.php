<?php

class shopProductsAction extends shopProductListAction
{
    public function execute()
    {
        $config = $this->getConfig();
        $default_view = $config->getOption('products_default_view');
        $products_per_page = $config->getOption('products_per_page');
        $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);

        $products = $this->collection->getProducts('*, image', 0, $products_per_page);
        $this->workupProducts($products);

        /*
         * @event backend_products
         */
        $this->view->assign('backend_products', wa()->event('backend_products'));

        $this->assign(array(
            'products' => array_values($products),
            'total_count' => $this->collection->count(),
            'count' => count($products),
            'collection_hash' => $this->hash,
            'collection_param' => $this->collection_param,
            'sort' => $this->sort,
            'order' => $this->order,
            'text' => $this->text,
            'title' => $this->hash[0] != 'search' ? $this->collection->getTitle() : $this->text,
            'info' => $this->collection->getInfo(),
            'view' => $view,
            'default_view' => $default_view,
        ));
    }
}