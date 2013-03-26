<?php

class shopStocksAction extends waViewAction
{
    public function __construct($params = null) {
        parent::__construct($params);
        $this->product_model = new shopProductModel();
    }
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', 0, waRequest::TYPE_INT);
        $order = waRequest::get('order', 'desc') == 'desc' ? 'desc' : 'asc';

        if (!$total_count) {
            $total_count = $this->product_model->countProductStocks();
        }

        $data = $this->getProductStocks($offset, $this->getConfig()->getOption('products_per_page'), $order);
        $count = count($data);
        if ($offset === 0) {
            $stock_model = new shopStockModel();
            $this->response(array(
                'product_stocks' => $data,
                'total_count' => $total_count,
                'count' => $count,
                'stocks' => $stock_model->getAll(),
                'order' => $order
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

    public function getProductStocks($offset, $count, $order_by)
    {
        $data = $this->product_model->getProductStocks($offset, $count, $order_by);

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
