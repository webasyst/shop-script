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
        $lazy_loading = $this->getConfig()->getOption('lazy_loading');
        if ($lazy_loading) {
            $offset = 0;
        } else {
            $page = waRequest::get('page', 1, 'int');
            if ($page < 1) {
                $page = 1;
            }
            $offset = ($page - 1) * $products_per_page;
            $this->view->assign('page', $page);
        }
        $products = $this->collection->getProducts('*,'.join(',', $columns), $offset, $products_per_page);
        $this->workupProducts($products);

        /*
         * @event backend_products
         */
        $event_params = array('type' => $this->hash[0], 'info' => $this->collection->getInfo());
        $this->view->assign('backend_products', wa()->event('backend_products', $event_params));

        $this->view->assign('products_rights', $this->getUser()->isAdmin('shop') || $this->getUser()->getRights('shop', 'type.%'));

        $total_count = $this->collection->count();
        $stock_model = new shopStockModel();
        $this->assign(array(
            'lazy_loading' => $lazy_loading,
            'products' => array_values($products),
            'total_count' => $total_count,
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

        if (!$lazy_loading) {
            $pages_count = ceil((float)$total_count / $products_per_page);
            $this->view->assign('pages_count', $pages_count);
        }
    }
}

