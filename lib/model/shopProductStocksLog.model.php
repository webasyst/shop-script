<?php

class shopProductStocksLogModel extends waModel {
    
    protected $table = 'shop_product_stocks_log';
    
    const TYPE_PRODUCT = 'product';
    const TYPE_IMPORT = 'import';
    const TYPE_STOCK = 'stock';
    const TYPE_ORDER = 'order';
    
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

    static public function getIcon($type)
    {
        switch ($type) {
            case self::TYPE_PRODUCT:
                $icon = '<i class="icon16 edit"></i>';
                break;
            case self::TYPE_IMPORT:
                $icon = '<i class="icon16 ss excel"></i>';
                break;
            case self::TYPE_ORDER:
                $icon = '<i class="icon16 ss shop"></i>';
                break;
            case self::TYPE_STOCK:
                $icon = '<i class="icon16 move"></i>';
                break;
            default :
                $icon = '<i class="icon16"></i>';
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
        foreach ($list as &$v) {
            $v['icon'] = shopProductStocksLogModel::getIcon($v['type']);
            if (!$v['description']) {
                if ($v['after_count'] === null) {
                    $v['description'] = _w('In stock value updated to ∞');
                } else {
                    $v['description'] = sprintf(_w('In stock value updated to %d'), $v['after_count']);
                }
            } else {
                if ($v['type'] == self::TYPE_ORDER) {
                    $v['description'] = sprintf(
                            _w($v['description']), 
                            '<a href="?action=orders#/orders/'.$v['order_id'].'">'.shopHelper::encodeOrderId($v['order_id']).'</a>'
                    );
                }
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
    
    public function add($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');
        $data['description'] = self::$description;
        $data['type'] = self::$transaction_type;
        if (self::$params) {
            if (isset(self::$params['order_id'])) {
                $data['order_id'] = self::$params['order_id'];
            }
        }
        return $this->insert($data);
    }
    
}