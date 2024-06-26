<?php

class shopProductStocksLogModel extends waModel
{

    protected $table = 'shop_product_stocks_log';

    const TYPE_PRODUCT = 'product';
    const TYPE_IMPORT = 'import';
    const TYPE_STOCK = 'stock';
    const TYPE_ORDER = 'order';
    const TYPE_TRANSFER = 'transfer';

    private static $transaction_type = '';
    private static $description;
    private static $params = array();

    /**
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }

    public static function getIcon($type)
    {
        $isWA2 = wa()->whichUI() == '2.0';
        switch ($type) {
            case self::TYPE_PRODUCT:
                $icon = '<i class="'.($isWA2 ? 'fas fa-pencil-alt' : 'icon16 ss pencil-bw').'" title="'._w('Stock information was edited').'"></i>';
                break;
            case self::TYPE_IMPORT:
                $icon = '<i class="'.($isWA2 ? 'fas fa-file-alt' : 'icon16 ss file-bw').'" title="'._w('Stock information was updated during bulk product import').'"></i>';
                break;
            case self::TYPE_ORDER:
                $icon = '<i class="'.($isWA2 ? 'fas fa-shopping-cart' : 'icon16 ss cart-bw').'" title="'._w('Stock information was updated when processing an order').'"></i>';
                break;
            case self::TYPE_STOCK:
                $icon = '<i class="'.($isWA2 ? 'fas fa-exchange-alt' : 'icon16 ss transfer-bw').'" title="'._w('Inventory transferred from one stock to another').'"></i>';
                break;
            case self::TYPE_TRANSFER:
                $icon = '<i class="'.($isWA2 ? 'fas fa-exchange-alt' : 'icon16 ss transfer-bw').'" title="'._w('Inventory transferred to warehouse').'"></i>';
                break;
            default:
                $icon = '<i class="'.($isWA2 ? 'fas fa-pencil-alt' : 'icon16 ss pencil-bw').'" title="'._w('Unrecognized stock operation').'"></i>';
                break;
        }
        return $icon;
    }

    public static function setContext($type, $description = '', $params = array())
    {
        self::$description = $description;
        self::$transaction_type = $type;
        self::$params = $params;
    }

    public static function getContext()
    {
        return array(
            'type' => self::$transaction_type,
            'description' => self::$description,
            'params' => self::$params
        );
    }

    public static function clearContext()
    {
        self::$description = null;
        self::$transaction_type = '';
        self::$params = array();
    }

    protected function getDefaultOptions()
    {
        return array(
            'offset' => 0,
            'limit'  => 30,
            'order' => 'datetime DESC, id DESC',
            'where'  => array()
        );
    }


    public function getList($fields = '*,stock_name,sku_name,product_name', $options = array())
    {
        $options += $this->getDefaultOptions();

        $main_fields = array();
        $post_fields = array();
        foreach (explode(',', $fields) as $name) {
            if ($this->fieldExists($name) || $name == '*') {
                $main_fields[]= $name;
            } else {
                $post_fields[]= $name;
            }
        }

        $where = $this->getWhereByField($options['where']);

        $limit_str = '';
        if ($options['limit'] !== false) {
            $limit_str = " LIMIT ".($options['offset'] ? $options['offset'].',' : '').(int)$options['limit'];
        }

        $sql = "SELECT ".implode(',', $main_fields)." FROM `{$this->table}`".
                ($where ? " WHERE $where" : "").
                " ORDER BY ".$options['order'].
                $limit_str;

        $data = $this->query($sql)->fetchAll('id');
        if (!$data) {
            return $data;
        }

        $this->workupList($data, $post_fields);

        return $data;

    }

    private function workupList(&$list, $fields)
    {
        if (!$list) {
            return;
        }

        $transfers = array();
        foreach ($list as $v) {
            if ($v['type'] == self::TYPE_TRANSFER) {
                $transfers[] = (int) $v['transfer_id'];
            }
        }

        $transfer_id_string_id_map = array();
        if ($transfers) {
            $tm = new shopTransferModel();
            $transfer_id_string_id_map = $tm->mapIdToStringId($transfers);
        }

        foreach ($list as &$v) {
            $v['icon'] = shopProductStocksLogModel::getIcon($v['type']);
            if (!$v['description']) {
                if ($v['after_count'] === null) {
                    $v['description'] = _w('In stock value updated to ∞');
                } else {
                    $v['description'] = sprintf(_w('In stock value updated to %s'), shopFrac::discardZeros($v['after_count']));
                }
            } elseif ($v['type'] == self::TYPE_ORDER) {
                $v['description'] = sprintf(
                    _w($v['description']),
                    '<a href="?action=orders#/order/'.$v['order_id'].'/">'.shopHelper::encodeOrderId($v['order_id']).'</a>'
                );
            } elseif ($v['type'] == self::TYPE_TRANSFER) {
                $transfer_string_id = ifset($transfer_id_string_id_map[$v['transfer_id']], $v['transfer_id']);
                $v['description'] = sprintf(
                    _w($v['description']),
                    '<a class="s-transfer-log-link" data-transfer-id="'.$v['transfer_id'].'" href="?action=products#/stockslog/transfer_id=' . $v['transfer_id'] . '/">'.$transfer_string_id.'</a>'
                );
            }
        }
        unset($v);

        $stock_ids = array();
        foreach ($list as $v) {
            $stock_ids[] = $v['stock_id'];
        }
        $model = new shopStockModel();
        $stocks = $model->getByField('id', array_unique($stock_ids), 'id');
        foreach ($list as &$v) {
            if (isset($stocks[$v['stock_id']])) {
                $v['stock_name'] = $stocks[$v['stock_id']]['name'];
            }
        }
        unset($v);

        foreach ($fields as $f) {
            if ($f == 'sku_name') {
                $sku_ids = array();
                foreach ($list as $v) {
                    $sku_ids[] = $v['sku_id'];
                }
                $model = new shopProductSkusModel();
                $skus = $model->select('id,sku,name')->where("id IN(".implode(',', array_unique($sku_ids)).")")->fetchAll('id');
                foreach ($list as &$v) {
                    if (isset($skus[$v['sku_id']])) {
                        $v['sku_name'] = $skus[$v['sku_id']]['name'];
                        if ($v['sku_name']) {
                            if ($skus[$v['sku_id']]['sku']) {
                                $v['sku_name'] .= ' (' . $skus[$v['sku_id']]['sku'] . ')';
                            }
                        } else {
                            if ($skus[$v['sku_id']]['sku']) {
                                $v['sku_name'] = $skus[$v['sku_id']]['sku'];
                            }
                        }
                    }
                }
                unset($v);
            }
            if ($f == 'sku_count') {
                $sku_ids = array();
                foreach ($list as $v) {
                    $sku_ids[] = $v['sku_id'];
                }
                $model = new shopProductSkusModel();
                $skus = $model->select('id,count')->where("id IN(".implode(',', array_unique($sku_ids)).")")->fetchAll('id');
                foreach ($list as &$v) {
                    if (isset($skus[$v['sku_id']])) {
                        $sku = $skus[$v['sku_id']];
                        $v['sku_count'] = $sku['count'];
                    }
                }
                unset($v);
            }
            if ($f == 'product_name') {
                $product_ids = array();
                foreach ($list as $v) {
                    $product_ids[] = $v['product_id'];
                }
                $model = new shopProductModel();
                $products = $model->select('id,name')->where("id IN (".implode(',', array_unique($product_ids)).")")->fetchAll('id');
                foreach ($list as &$v) {
                    if (isset($products[$v['product_id']])) {
                        $v['product_name'] = $products[$v['product_id']]['name'];
                    }
                }
                unset($v);
            }

        }

    }

    /**
     * @param array $data
     *   Fields:
     *   int $data['product_id']
     *   int $data['sku_id']
     *   int|null $data['stock_id']
     *   decimal|null $data['before_count']
     *   decimal|null $data['after_count']
     *   decimal|null $data['diff_count']
     * @return boolean
     */
    public function add($data)
    {
        // setting stock_name here is forbidden
        if (array_key_exists('stock_name', $data)) {
            unset($data['stock_name']);
        }

        if (!array_key_exists('before_count', $data)) {
            $data['before_count'] = null;
        }
        if (!array_key_exists('after_count', $data)) {
            $data['after_count'] = null;
        }

        if (($data['before_count'] === null && $data['after_count'] !== null)
            || ($data['after_count'] === null && $data['before_count'] !== null)
            || ($data['before_count'] != $data['after_count'])
        ) {
            if ($data['after_count'] === null || ($data['after_count'] == 0 && $data['before_count'] === null)) {
                $data['diff_count'] = null;
            } else {
                $data['diff_count'] = $data['after_count'] - (double) $data['before_count'];
            }

            // change happens
            return $this->insert($data);
        } else {
            return false;
        }
    }

    public function insert($data, $type = 0)
    {
        $data['datetime'] = date('Y-m-d H:i:s');
        $data['description'] = self::$description;
        $data['type'] = self::$transaction_type;
        if (self::$params) {
            foreach (self::$params as $key => $val) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $val;
                }
            }
        }

        /**
         * @since 10.2.0
         */
        wa('shop')->event('product_stocks_log', ref([
            'data' => $data,
        ]));

        return parent::insert($data, $type);
    }
}
