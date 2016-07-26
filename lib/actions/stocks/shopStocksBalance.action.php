<?php

class shopStocksBalanceAction extends waViewAction
{
    protected $options = array(
        'count_is_not_null' => true
    );

    public function __construct($params = null) {
        parent::__construct($params);
        $this->product_model = new shopProductModel();
    }
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', 0, waRequest::TYPE_INT);
        $order = waRequest::get('order') == 'desc' ? 'desc' : 'asc';
        $sort = (string) waRequest::get('sort');
        if (!$sort) {
            $sort = 'count';
        }

        if (!$total_count) {
            $total_count = $this->product_model->countProductStocks($this->options);
        }
        
        $data = $this->getProductStocks(array(
            'offset' => $offset,
            'limit' => $this->getConfig()->getOption('products_per_page'),
            'order' => $order,
            'sort' => $sort
        ));
        $count = count($data);
        if ($offset === 0) {
            $stock_model = new shopStockModel();
            $this->response(array(
                'product_stocks' => $data,
                'total_count' => $total_count,
                'count' => $count,
                'stocks' => $stock_model->getAll(),
                'order' => $order,
                'sort' => $sort
            ));
        } else {
            $this->product_model = new shopProductModel();
            $this->response(array(
                'product_stocks' => $data,
                'total_count' => $total_count,
                'count' => $count,
                'order' => $order,
                'progress' => array(
                    'loaded' => _w('%d product','%d products', $offset + $count),
                    'of' => sprintf(_w('of %d'), $total_count),
                    'chunk' => _w('%d product','%d products', max(0, min($total_count - ($offset + $count), $count))),
                )
            ), true);
        }
    }

    /**
     * @param $options or $offset, $count, $order_by
     * @return array
     */
    public function getProductStocks($options)
    {
        if (is_array($options)) {
            $offset = ifset($options['offset']);
            $limit = ifset($options['limit']);
            $order = ifset($options['order']);
            $sort = ifset($options['sort']);
        } else {
            $args = func_get_args();
            $offset = ifset($args[0]);
            $limit = ifset($args[1]);
            $order = ifset($args[2]);
            $sort = '';
        }

        $offset = (int) $offset;
        $limit = (int) $limit;
        $order = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';

        //$offset, $count, $order_by
        $options = $this->options;
        $options['offset'] = $offset;
        $options['limit'] = $limit;
        $options['order'] = $order;
        $options['sort'] = $sort;
        $data = $this->product_model->getProductStocks($options);

        foreach ($data as &$product) {
            $product['icon'] = shopHelper::getStockCountIcon($product['count']);
            foreach ($product['skus'] as &$sku) {
                $sku['icon'] = shopHelper::getStockCountIcon($sku['count']);
            }
            unset($sku);

            foreach ($product['stocks'] as $stock_id => &$stock) {
                foreach ($stock as &$sku) {
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], $stock_id);
                }
            }
            unset($product, $stock, $sku);
        }

        // because javascript doesn't guarantee any particular order for the keys in object
        // make hash-table to array converting
        $data = array_values($data);
        foreach ($data as &$product) {
            $product['skus']   = array_values($product['skus']);
            $product['stocks'] = array_values($product['stocks']);
            foreach ($product['stocks'] as &$stock) {
                $stock = array_values($stock);
            }
            unset($product, $stock);
        }

        return $data;
    }

    public function response($data, $json = false) {
        if (!$json) {
            $this->view->assign($data);
        } else {
            echo json_encode(array('status' => 'ok', 'data' => $data)); exit;
        }
    }
}
