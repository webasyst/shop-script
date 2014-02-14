<?php

class shopProductsAction extends shopProductListAction
{
    public function execute()
    {
        $products_per_page = $this->getConfig()->getOption('products_per_page');
        $view = $this->getProductView();

        $products = $this->collection->getProducts('*, image', 0, $products_per_page);
        $this->workupProducts($products);

        /*
         * @event backend_products
         */
        $event_params = array('type' => $this->hash[0], 'info' => $this->collection->getInfo());
        $this->view->assign('backend_products', wa()->event('backend_products', $event_params));
        
        $this->assign(array(
            'products' => array_values($products),
            'total_count' => $this->collection->count(),
            'count' => count($products),
            'sort' => $this->sort,
            'order' => $this->order,
            'text' => $this->text,
            'title' => $this->hash[0] != 'search' ? $this->collection->getTitle() : $this->text,
            'info' => $this->collection->getInfo(),
            'view' => $view
        ));

    }
}