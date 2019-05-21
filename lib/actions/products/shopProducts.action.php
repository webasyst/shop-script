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

        /*
         * @event backend_products
         */
        $event_params = array('type' => $this->hash[0], 'info' => $this->collection->getInfo());
        $this->view->assign('backend_products', wa()->event('backend_products', $event_params));

        $this->view->assign('products_rights', $this->getUser()->isAdmin('shop') || $this->getUser()->getRights('shop', 'type.%'));

        /**
         * @var $config shopConfig
         */
        $config = $this->getConfig();

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');

        $products = $this->getProducts(array(
            'fields'            => '*,'.join(',', $columns),
            'offset'            => $offset,
            'products_per_page' => $products_per_page,
            'view'              => $view
        ));

        $total_count = $this->collection->count();
        $this->assign(array(
            'lazy_loading'                    => $lazy_loading,
            'products'                        => $products,
            'total_count'                     => $total_count,
            'count'                           => count($products),
            'sort'                            => $this->sort,
            'order'                           => $this->order,
            'text'                            => $this->text,
            'title'                           => $this->hash[0] != 'search' ? $this->collection->getTitle() : $this->text,
            'info'                            => $this->collection->getInfo(),
            'view'                            => $view,
            'stocks'                          => array_values($stocks),
            'additional_columns'              => self::getAdditionalColumns(),
            'additional_columns_autocomplete' => self::isColumnsAutocomplete(),
            'primary_currency'                => $config->getCurrency(),
            /*
            'use_product_currency' => wa()->getSetting('use_product_currency'),
            'currencies' => $config->getCurrencies()*/
        ));

        if (!$lazy_loading) {
            $pages_count = ceil((float)$total_count / $products_per_page);
            $this->view->assign('pages_count', $pages_count);
        }
    }

    private function getProducts($options)
    {
        $fields = $options['fields'];
        if ($options['view'] === 'skus') {
            $fields .= ',skus,stock_counts';
        }
        $products = $this->collection->getProducts($fields, $options['offset'], $options['products_per_page']);
        $this->workupProducts($products);
        $products = array_values($products);
        return $products;
    }
}

