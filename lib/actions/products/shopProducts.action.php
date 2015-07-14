<?php

class shopProductsAction extends shopProductListAction
{
    public function execute()
    {
        $products_per_page = $this->getConfig()->getOption('products_per_page');
        $view = $this->getProductView();

        $columns = self::getEnabledColumns();
        $columns[] = 'image';
        if (waRequest::request('sort') == 'stock_worth') {
            $columns[] = 'stock_worth';
        }
        $products = $this->collection->getProducts('*,'.join(',', $columns), 0, $products_per_page);
        $this->workupProducts($products);

        /*
         * @event backend_products
         */
        $event_params = array('type' => $this->hash[0], 'info' => $this->collection->getInfo());
        $this->view->assign('backend_products', wa()->event('backend_products', $event_params));

        $this->view->assign('products_rights', $this->getUser()->isAdmin('shop') || $this->getUser()->getRights('shop', 'type.%'));

        $stock_model = new shopStockModel();
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
            'stocks' => $stock_model->getAll('id'),
            'additional_columns' => self::getAdditionalColumns(),
        ));

    }
}

