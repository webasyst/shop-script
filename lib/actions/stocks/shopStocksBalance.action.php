<?php

class shopStocksBalanceAction extends waViewAction
{
    protected $options = array(
        'count_is_not_null' => true
    );
    protected $product_model;

    public function __construct($params = null) {
        parent::__construct($params);
        $this->product_model = new shopProductModel();
    }

    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', 0, waRequest::TYPE_INT);
        $order = waRequest::get('order') == 'asc' ? 'asc' : 'desc';
        $requested_stock_id = $stock_id = waRequest::get('stock_id', null, waRequest::TYPE_INT);
        $sort = (string) waRequest::get('sort');
        if ($stock_id) {
            $sort = 'stock_count_'.$stock_id;
        } else if (!$sort) {
            $sort = 'count';
        } else {
            $prefix = 'stock_count';
            $prefix_len = strlen($prefix);
            if (strpos($sort, $prefix) === 0) {
                $stock_id = (int) substr($sort, $prefix_len + 1);
            }
        }

        if (!$total_count) {
            $total_count = $this->product_model->countProductStocks($this->options);
        }

        $data = $this->getProductStocks(array(
            'offset' => $offset,
            'limit' => $this->getConfig()->getOption('products_per_page'),
            'stock_id' => $requested_stock_id,
            'order' => $order,
            'sort' => $sort
        ));
        $count = count($data);

        $vars = [
            'product_stocks' => $data,
            'stock_id' => $requested_stock_id,
            'total_count' => $total_count,
            'count' => $count,
            'order' => $order,
            'sort' => $sort,
            'progress' => [
                'loaded' => _w('%d product','%d products', $offset + $count),
                'of' => sprintf(_w('of %d'), $total_count),
                'chunk' => _w('%d product','%d products', max(0, min($total_count - ($offset + $count), $count))),
            ],
        ];

        if ($offset === 0) {
            $vars['stocks'] = (new shopStockModel())->getAll('id');
            $stock_worth = $this->calculateOverallValue($requested_stock_id);
            if (isset($stock_worth[''])) {
                $vars += $stock_worth[''];
                unset($stock_worth['']);
            }

            $vars['total_all_stocks'] = [
                'count' => 0,
                'total_market_value' => 0
            ];
            foreach ($vars['stocks'] as $stock_id => &$stock) {
                if (!$requested_stock_id || $requested_stock_id === $stock_id) {
                    $stock += ifset($stock_worth, $stock_id, [
                        'total_market_value' => 0,
                        'total_purchase_value' => 0,
                        'total_count' => 0,
                    ]);

                    $stock['total_market_html'] = shop_currency_html($stock['total_market_value']);
                    $stock['total_count'] = intval($stock['total_count']);

                    if ($requested_stock_id) {
                        $stock['total_purchase_html'] = shop_currency_html($stock['total_purchase_value']);
                        unset($stock['total_market_value'], $stock['total_purchase_value']);
                        break;
                    } else {
                        $vars['total_all_stocks']['count'] += $stock['total_count'];
                        $vars['total_all_stocks']['total_market_value'] += $stock['total_market_value'];
                        unset($stock['total_market_value']);
                    }
                }
            }
            unset($stock);

            if ($vars['total_all_stocks']['count'] > 0) {
                $vars['total_all_stocks']['total_market_html'] = shop_currency_html($vars['total_all_stocks']['total_market_value']);
                unset($vars['total_all_stocks']['total_market_value']);
            }
            $vars['stocks'] = array_values($vars['stocks']);
        } else {
            $is_json = true;
        }

        $this->response($vars, ifset($is_json));
    }

    function calculateOverallValue($stock_id=null)
    {
        $stock_where = '';
        if ($stock_id) {
            $stock_where = "AND ps.stock_id=".((int)$stock_id);
        }
        $model = new waModel();

        //
        // Total worth of all products on each stock
        //
        $sql = "
            SELECT ps.stock_id,
                SUM(s.price*c.rate*IF(s.count > 0, ps.count, 0)) AS total_market_value,
                SUM(s.purchase_price*c.rate*IF(ps.count > 0, ps.count, 0)) AS total_purchase_value,
                SUM(IF(ps.count > 0, ps.count, 0)) AS total_count
            FROM shop_product AS p
                JOIN shop_product_skus AS s
                    ON s.product_id=p.id
                JOIN shop_currency AS c
                    ON c.code=p.currency
                JOIN shop_product_stocks AS ps
                    ON ps.sku_id=s.id
            WHERE s.count > 0
                $stock_where
            GROUP BY ps.stock_id
        ";
        $result = $model->query($sql)->fetchAll('stock_id');
        if ($stock_id) {
            return $result;
        }

        //
        // Total worth of all products (including those not belonging to any stock)
        //
        $sql = "
            SELECT
                SUM(s.price*c.rate*IF(s.count > 0, s.count, 0)) AS overall_market_value,
                SUM(s.purchase_price*c.rate*IF(s.count > 0, s.count, 0)) AS overall_purchase_value,
                SUM(IF(s.count > 0, s.count, 0)) AS overall_count
            FROM shop_product AS p
                JOIN shop_product_skus AS s
                    ON s.product_id=p.id
                JOIN shop_currency AS c
                    ON c.code=p.currency
            WHERE s.count > 0
        ";
        $result[''] = $model->query($sql)->fetchAssoc();
        return $result;
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
            $requested_stock_id = ifset($options, 'stock_id', null);
        } else {
            $args = func_get_args();
            $offset = ifset($args[0]);
            $limit = ifset($args[1]);
            $order = ifset($args[2]);
            $requested_stock_id = null;
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

        $processSku = function(&$sku, &$product) use ($requested_stock_id) {
            if ($requested_stock_id) {
                $sku_counts = ifset($product, 'stocks', $requested_stock_id, []);
                $sku['count'] = ifset($sku_counts, $sku['id'], 'count', null);
            }
            $sku['primary_price'] = shop_currency($sku['price'], $product['currency'], null, false);
            $sku['primary_purchase_price'] = shop_currency($sku['purchase_price'], $product['currency'], null, false);
            $sku['count'] = (string)shopFrac::discardZeros($sku['count']);
            $sku['total_market_value_html'] = '';
            $sku['total_purchase_value_html'] = '';
            if ($sku['count'] !== '' && $sku['count'] >= 0) {
                $sku['total_market_value_html'] = shop_currency_html($sku['primary_price'] * $sku['count']);
                $sku['total_purchase_value_html'] = shop_currency_html($sku['purchase_price'] * $sku['count']);
                if ($sku['count'] > 0 && $product['total_market_value'] !== '') {
                    $product['total_market_value'] += $sku['primary_price'] * $sku['count'];
                    $product['total_purchase_value'] += $sku['purchase_price'] * $sku['count'];
                }
            } else if ($sku['count'] === '') {
                // infinity
                $product['total_market_value'] = '';
                $product['total_purchase_value'] = '';
            }
            $sku['icon'] = shopHelper::getStockCountIcon($sku['count']);
        };

        foreach ($data as &$product) {
            $product['has_stock_counts'] = false;
            $product['total_market_value'] = 0;
            $product['total_purchase_value'] = 0;
            $product['total_market_value_html'] = '';
            $product['total_purchase_value_html'] = '';
            $product['count'] = (string)shopFrac::discardZeros($product['count']);
            $product['icon'] = shopHelper::getStockCountIcon($product['count']);
            foreach ($product['skus'] as &$sku) {
                $processSku($sku, $product);
            }
            unset($sku);

            if ($product['total_market_value'] !== '') {
                $product['total_market_value_html'] = shop_currency_html($product['total_market_value']);
                $product['total_purchase_value_html'] = shop_currency_html($product['total_purchase_value']);
            }

            if ($requested_stock_id) {
                $product['stocks'] = [$requested_stock_id => ifset($product, 'stocks', $requested_stock_id, [])];
            }

            $product_copy = $product;
            foreach ($product['stocks'] as $stock_id => &$stock) {
                $stk_id = (string)$stock_id;
                $product['stocks_summary'][$stk_id] = [
                    'count' => 0,
                    'total_market_value' => 0,
                    'total_market_value_html' => ''
                ];
                foreach ($stock as &$sku) {
                    $sku['count'] = (string)shopFrac::discardZeros($sku['count']);
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], $stk_id);
                    $processSku($sku, $product_copy);
                    $sku['stock_id'] = $stk_id;

                    if ($sku['count'] !== '') {
                        $product['has_stock_counts'] = true;
                    }
                    if ($product['stocks_summary'][$stk_id]['count'] !== '' && $sku['count'] !== '') {
                        $product['stocks_summary'][$stk_id]['count'] += intval($sku['count']);
                        if ($sku['count'] > 0) {
                            $product['stocks_summary'][$stk_id]['total_market_value'] += $sku['primary_price'] * $sku['count'];
                        }
                    } else {
                        $product['stocks_summary'][$stk_id]['count'] = '';
                        $product['stocks_summary'][$stk_id]['total_market_value'] = '';
                    }
                }
                if ($product['stocks_summary'][$stk_id]['total_market_value'] !== '') {
                    $product['stocks_summary'][$stk_id]['total_market_value_html'] = shop_currency_html($product['stocks_summary'][$stk_id]['total_market_value'], $product['currency']);
                }
                unset($product['stocks_summary'][$stk_id]['total_market_value']);
            }

            unset($product, $stock, $sku, $product_copy);
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
