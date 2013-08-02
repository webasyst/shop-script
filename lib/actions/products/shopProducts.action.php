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
        $event_params = array('type' => $this->hash[0], 'info' => $this->collection->getInfo());
        $this->view->assign('backend_products', wa()->event('backend_products', $event_params));
        
        $include_path = 
                $this->getConfig()->getAppPath() . 
                '/templates/actions/products/product_list_' . $view . '.html';
        if (!file_exists($include_path)) {
            $view = $default_view;
        }
        
        $this->assign(array(
            'products' => array_values($products),
            'total_count' => $this->collection->count(),
            'count' => count($products),
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