<?php

class shopOrderModel extends waModel
{
    protected $table = 'shop_order';

    protected $contacts;

    private function getDefaultSearchOptions()
    {
        return array(
            'offset' => 0,
            'limit'  => 50,
            'escape' => true,
            'where'  => array(),
        );
    }

    public function getOffset($order_id, $where, $check_presenting = false)
    {
        $order_id = (int) $order_id;

        $where = $this->getWhereByField($where);

        // check presenting
        if ($check_presenting) {
            $sql = "SELECT * FROM `{$this->table}` WHERE id = $order_id ".($where ? "AND $where" : "");
            $item = $this->query($sql)->fetchAssoc();
        } else {
            $item = $this->getById($order_id);
        }
        if (!$item) {
            return false;
        }

        $offset_where = "create_datetime > '{$item['create_datetime']}' OR (create_datetime = '{$item['create_datetime']}' AND id < '{$item['id']}')";

        $sql = "SELECT COUNT(id) offset
                FROM `{$this->table}`
                WHERE ".($where ? "$where AND " : "")." ($offset_where)";
        return $this->query($sql)->fetchField('offset');
    }

    public function getList($fields = "*", $options = array())
    {
        $options += $this->getDefaultSearchOptions();

        $main_fields = '';
        $item_fields = '';
        $post_fields = '';

        foreach (explode(',', $fields) as $name) {
            if (substr($name, 0, 6) === 'items.') {
                $item_fields .= ','.substr($name, 6);
            } else {
                if ($this->fieldExists($name) || $name == '*') {
                    $main_fields .= ','.$name;
                } else {
                    $post_fields .= ','.$name;
                }
            }
        }
        $item_fields = substr($item_fields, 1);
        $main_fields = substr($main_fields, 1);
        $post_fields = substr($post_fields, 1);

        $where = $this->getWhereByField($options['where']);
        if(!empty($options['change_datetime'])) {
            if($where) {
                $where .= ' AND ';
            }
            $where .= sprintf("IFNULL(`update_datetime`,`create_datetime`) > '%s'",date("Y-m-d H:i:s", $options['change_datetime']));
        }

        $sql = "SELECT $main_fields FROM `{$this->table}`".
            ($where ? " WHERE $where" : "").
            " ORDER BY create_datetime DESC, id".
            " LIMIT ".($options['offset'] ? $options['offset'].',' : '').(int)$options['limit'];
        $data = $this->query($sql)->fetchAll('id');

        if (!$data) {
            return array();
        }

        if ($item_fields) {
            $order_id = null;
            foreach ($this->query("
                SELECT $item_fields, id, order_id FROM `shop_order_items`
                WHERE order_id IN ('".implode("','", array_keys($data))."')
                ORDER BY order_id"
            ) as $item) {
                if ($order_id != $item['order_id']) {
                    $order_id = $item['order_id'];
                    unset($item['order_id']);
                }
                if ($options['escape']) {
                    $item['name'] = htmlspecialchars($item['name']);
                }
                $data[$order_id]['items'][$item['id']] = $item;
            }
        }

        if ($post_fields) {
            $this->workupList($data, $post_fields, $options['escape']);
        }

        return $data;
    }

    private function workupList(&$data, $fields, $escape)
    {
        $opm = null;
        foreach (explode(',', $fields) as $field) {
            if ($field == 'contact') {

                $contact_ids = array();
                $order_ids = array();
                foreach ($data as $item) {
                    if ($item['contact_id']) {
                        $contact_ids[] = $item['contact_id'];
                    } else {
                        $order_ids[] = $item['id'];
                    }
                }

                $contacts_info = $this->getContactsInfo($contact_ids, $order_ids);

                foreach ($data as &$item) {
                    $contact_id = $item['contact_id'];
                    $order_id  = $item['id'];
                    if ($contact_id) {
                        $item['contact'] = $contacts_info['contacts'][$contact_id];
                    } else {
                        $item['contact'] = $contacts_info['order_params'][$order_id];
                    }
                    if ($escape) {
                        $item['contact']['name'] = htmlspecialchars($item['contact']['name']);
                    }
                }
            } else if ($field == 'params') {
                if ($opm === null) {
                    $opm = new shopOrderParamsModel();
                }
                foreach ($opm->get(array_keys($data)) as $o_id => $params) {
                    $data[$o_id]['params'] = $params;
                }
            }
        }
    }

    /**
     * @param int|null $state_id If null return counters for each state in assoc array
     * @return int|array
     */
    public function getStateCounters($state_id = null)
    {
        $where = "";
        if ($state_id !== null) {
            $where = "WHERE " . $this->getWhereByField('state_id', $state_id);
        }
        $sql = "
            SELECT state_id, COUNT(state_id) cnt FROM `{$this->table}`
            {$where} GROUP BY state_id";
        $r = $this->query($sql);
        if ($state_id !== null) {
            $cnt = $r->fetchField('cnt');
            return $cnt ? $cnt : 0;
        }

        $counters = array();
        $workflow = new shopWorkflow();
        foreach (array_keys($workflow->getAvailableStates()) as $state_id) {
            $counters[$state_id] = 0;
        }

        $counters = $r->fetchAll('state_id', true) + $counters;

        return $counters ? $counters : array();
    }

    public function getStorefrontCounters()
    {
        $storefronts = array();           // collect counters
        $aux_storefronts = array();     // maintain ambiguity of '/' at the end of urls
        $routes = wa()->getRouting()->getByApp('shop');
        foreach ($routes as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = $domain.'/'.$route['url'];
                if (substr($url, -1) == '*') {
                    $url = substr($url, 0, -1);
                }
                if (substr($url, -1) == '/') {
                    $url = substr($url, 0, -1);
                }
                $aux_storefronts[$url] = true;
                $aux_storefronts[$url.'/'] = true;
                $storefronts[$url.'/'] = 0;
            }
        }
        if (!$storefronts) {
            return array();
        }
        
        $escaped_urls = array();
        foreach (array_keys($aux_storefronts) as $url) {
            $escaped_urls[] = $this->escape($url);
        }
        $sql = "SELECT p.value, COUNT(p.order_id) AS cnt
            FROM `{$this->table}` AS o
            JOIN `shop_order_params` p ON o.id = p.order_id
            WHERE p.name = 'storefront' AND p.value IN ('".implode("','", $escaped_urls)."')
            GROUP BY p.value";

        foreach ($this->query($sql)->fetchAll() as $row) {
            $url = $row['value'];
            if (substr($url, -1) != '/') {
                $url = $url.'/';
            }
            $storefronts[$url] += $row['cnt'];
        }
        return $storefronts;
    }

    public function getContactCounters()
    {
        $counters = $this->query("
            SELECT contact_id, COUNT(contact_id) FROM `{$this->table}`
            GROUP BY contact_id
        ")->
        fetchAll('contact_id', true);
        return $counters ? $counters : array();
    }

    /**
     * Receive contacts info from contacts and order-params
     * @param array $contact_ids IDs of contact
     * @param array $order_ids IDs of orders with deleted contacts (info will be received from order-params)
     */
    private function getContactsInfo(array $contact_ids, array $order_ids)
    {
        $data = array('contacts' => $this->getContacts($contact_ids), 'order_params' => array());
        $order_params_model = new shopOrderParamsModel();
        foreach ($order_params_model->get($order_ids) as $order_id => $info) {
            $data['order_params'][$order_id] = $this->extractConctactInfo($info);
        }
        return $data;
    }

    public function extractConctactInfo($order_params_info)
    {
        $info = array();
        $info['name'] =  !empty($order_params_info['contact_name']) ? $order_params_info['contact_name'] : '';
        $info['email'] = !empty($order_params_info['contact_email']) ? $order_params_info['contact_email'] : '';
        $info['phone'] = !empty($order_params_info['contact_phone']) ? $order_params_info['contact_phone'] : '';
        $config = wa('shop')->getConfig();
        if ($config->getGeneralSettings('use_gravatar')) {
            $info['photo_50x50'] = shopHelper::getGravatar($info['email'], 50, $config->getGeneralSettings('gravatar_default'));
        } else {
            $contact = new waContact();
            $info['photo_50x50'] = $contact->getPhoto(50);
        }
        return $info;
    }

    public function getContacts($ids)
    {
        if (!$ids) {
            return array();
        }
        $contact_model = new waContactModel();
        $contacts = $contact_model->getByField('id', $ids, 'id');
        $config = wa('shop')->getConfig();
        $use_gravatar     = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');

        // Put everything into one array
        foreach ($contacts as &$c) {
            $contact = new waContact($c['id']);
            if (!$contact->get('photo') && $use_gravatar) {
                $c['photo_50x50'] = shopHelper::getGravatar($contact->get('email', 'default'), 50, $gravatar_default);
            } else {
                $c['photo_50x50'] = $contact->getPhoto(50);
            }
            //$c += ifset($additional_fields[$c['id']], array());
        }
        return $contacts;
    }

    public function getOrder($id, $extend = false, $escape = true)
    {
        $order = $this->getById($id);
        if (!$order) {
            return array();
        }

        $order_params_model = new shopOrderParamsModel();
        $order['params'] = $order_params_model->get($id);

        if ($order['contact_id']) {
            $contact = new waContact($order['contact_id']);
            $order['contact'] = array(
                'id' => $order['contact_id'],
                'name' => $contact->getName(),
                'email' => $contact->get('email', 'default'),
                'phone' => $contact->get('phone', 'default')
            );
            $config = wa('shop')->getConfig();
            $use_gravatar     = $config->getGeneralSettings('use_gravatar');
            $gravatar_default = $config->getGeneralSettings('gravatar_default');
            if (!$contact->get('photo') && $use_gravatar) {
                $order['contact']['photo_50x50'] = shopHelper::getGravatar($order['contact']['email'], 50, $gravatar_default);
            } else {
                $order['contact']['photo_50x50'] = $contact->getPhoto(50);
            }
        } else {
            $order['contact'] = $this->extractConctactInfo($order['params']);
        }

        $order_items_model = new shopOrderItemsModel();
        $order['items'] = $order_items_model->getItems($id, $extend);

        if ($escape) {
            if (!empty($order['items'])) {
                foreach ($order['items'] as &$product) {
                    if (!empty($product['name'])) {
                        $product['name'] = htmlspecialchars($product['name']);
                    }
                    if (!empty($product['item']['name'])) {
                        $product['item']['name'] = htmlspecialchars($product['item']['name']);
                    }
                    if (!empty($product['skus'])) {
                        foreach ($product['skus'] as &$sku) {
                            if (!empty($sku['name'])) {
                                $sku['name'] = htmlspecialchars($sku['name']);
                            }
                            unset($sku);
                        }
                    }
                    if (!empty($product['services'])) {
                        foreach ($product['services'] as &$service) {
                            if (!empty($service['name'])) {
                                $service['name'] = htmlspecialchars($service['name']);
                            }
                            if (!empty($service['item']['name'])) {
                                $service['item']['name'] = htmlspecialchars($service['item']['name']);
                            }
                            if (!empty($service['variants'])) {
                                foreach ($service['variants'] as &$variant) {
                                    $variant['name'] = htmlspecialchars($variant['name']);
                                    unset($variant);
                                }
                            }
                            unset($service);
                        }
                    }
                    unset($product);
                }
            }
            $order['contact']['name'] = htmlspecialchars($order['contact']['name']);
        }
        return $order;
    }

    public function insert($data, $type = 0)
    {
        if (!isset($data['create_datetime'])) {
            $data['create_datetime'] = date('Y-m-d H:i:s');
        }
        return parent::insert($data, $type);
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        if (is_array($field)) {
            $pdata = &$value;
        } else {
            $pdate = &$data;
        }
        if (!isset($pdata['create_datetime'])) {
            $pdata['create_datetime'] = date('Y-m-d H:i:s');
        }
        unset($pdata);

        return parent::updateByField($field, $value, $data, $options, $return_object);
    }

    /**
     * Update order with items
     *
     * Important: order metters. Each product-item must be directly before related services-items.
     * Example:
     *     // first product
          array(
     *         'product_id' => '1'
     *         'sku_id' => '1'
     *         ...
     *         'type'=>'product'
     *         ...
     *     )
     *     // services related to first product
     *     array(
     *         'product_id' => '1',
     *         'sku_id' => '1',
     *         ...
     *         'type'=>'service'
     *     ),
     *     array(
     *         'product_id' => '1',
     *         'sku_id' => '1',
     *         ...
     *         'type'=>'service'
     *     ),
     *     // second product
           array(
     *         'product_id' => '2'
     *         'sku_id' => '2'
     *         ...
     *         'type'=>'product'
     *         ...
     *     ),
     *     // services related to second product
     *     array(
     *         'product_id' => '2',
     *         'sku_id' => '2',
     *         ...
     *         'type'=>'service'
     *     ),
     *     array(
     *         'product_id' => '2',
     *         'sku_id' => '2',
     *         ...
     *         'type'=>'service'
     *     ),
     *
     * @param array $data
     * @param int $id If null than add new order
     * @return int $id
     */
    public function update($data, $id)
    {
        if (!$id && !empty($data['id'])) {
            $id = $data['id'];
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }

        $items_model = new shopOrderItemsModel();

        if ($id) {
            $items_model->update($data['items'], $id);
            unset($data['items']);
            $diff = array_diff_assoc($data, $this->getById($id));
            if ($diff) {
                $this->updateById($id, $diff);
            }
        }
        return $id;
    }

    public function delete($id)
    {
        $item = $this->getById($id);
        if ($item['state_id'] == 'deleted') {
            return false;
        }
        return $this->updateById($id, array('state_id' => 'deleted'));
    }


    /**
     * @param int $order_id
     */
    /*
    private function doProductsOnStocks($order_id, $op)
    {
        $op = $op == 'debit' ? '-' : '+';

        // models
        $product_stocks_model = new shopProductStocksModel();
        $order_params_model   = new shopOrderParamsModel();

        // try receive from order params
        $stock_id = null;
        $params = $order_params_model->get($order_id);
        if (!empty($params['stock_id'])) {
            $stock_id = $params['stock_id'];
        }

        // receive products (skus) of order
        $sql = "SELECT oi.sku_id AS id, oi.product_id, SUM(oi.quantity) count
            FROM ".$this->table." o
            JOIN shop_order_items oi ON o.id = oi.order_id AND oi.type = 'product'
            WHERE o.id = i:order_id
            GROUP BY oi.sku_id
            ORDER BY oi.product_id";

        $product_count = array();
        $product_id = null;
        foreach ($this->query($sql, array('order_id' => $order_id)) as $item) {
            if ($product_id != $item['product_id']) {
                $product_id = $item['product_id'];
                $product_count[$product_id] = 0;
            }
            $product_count[$product_id] += $item['count'];
            $this->exec("UPDATE `shop_product_skus` SET count = count $op {$item['count']} WHERE id = {$item['id']}");
            if ($stock_id) {
                // if doesn't exist this stock for this sku - get first available stock for this sku
                if (!$product_stocks_model->getByField(array('stock_id' => $stock_id, 'sku_id' => $item['id']))) {
                    $stock_id = $product_stocks_model->getByField('sku_id', $item['id']);
                    if ($stock_id) {
                        $stock_id = $stock_id['stock_id'];
                    }
                }
            } else {
                $stock_id = $product_stocks_model->getByField('sku_id', $item['id']);
                if ($stock_id) {
                    $stock_id = $stock_id['stock_id'];
                }
            }
            // if stock founded - do op under this stock
            if ($stock_id) {
                $this->exec("UPDATE `shop_product_stocks` SET count = count $op {$item['count']} WHERE sku_id = {$item['id']} AND stock_id = $stock_id");
            }
        }
        foreach ($product_count as $product_id => $count) {
            $this->exec("UPDATE `shop_product` SET count = count $op {$count} WHERE id = {$product_id}");
        }
        return true;
    }

    public function returnProductsToStocks($order_id)
    {
        $this->doProductsOnStocks($order_id, 'charge');
    }

    public function reduceProductsFromStocks($order_id)
    {
        $this->doProductsOnStocks($order_id, 'debit');
    }
*/

    public function returnProductsToStocks($order_id)
    {
        $order_params_model = new shopOrderParamsModel();
        $reduced = $order_params_model->getOne($order_id, 'reduced');
        if (!$reduced && $reduced !== null) {
            return;
        }
        $items_model = new shopOrderItemsModel();
        $items = $items_model->select('*')->where("type='product' AND order_id = ".(int) $order_id)->fetchAll();
        $sku_stock = array();
        foreach ($items as $item) {
            if (!isset($sku_stock[$item['sku_id']][$item['stock_id']])) {
                $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
            }
            $sku_stock[$item['sku_id']][$item['stock_id']] += $item['quantity'];
        }
        $items_model->updateStockCount($sku_stock);
        $order_params_model->setOne($order_id, 'reduced', 0);
    }

    public function reduceProductsFromStocks($order_id)
    {
        $order_params_model = new shopOrderParamsModel();
        $reduced = $order_params_model->getOne($order_id, 'reduced');
        if ($reduced) {
            return;
        }
        $items_model = new shopOrderItemsModel();
        $items = $items_model->select('*')->where("type='product' AND order_id = ".(int) $order_id)->fetchAll();
        $sku_stock = array();
        foreach ($items as $item) {
            if (!isset($sku_stock[$item['sku_id']][$item['stock_id']])) {
                $sku_stock[$item['sku_id']][$item['stock_id']] = 0;
            }
            $sku_stock[$item['sku_id']][$item['stock_id']] -= $item['quantity'];
        }
        $items_model->updateStockCount($sku_stock);
        $order_params_model->setOne($order_id, 'reduced', 1);
    }

    /**
     * @param int|array|null $order_id null - all orders
     */
    public function recalculateProductsTotalSales($order_id = null)
    {
        $product_ids = array();
        if ($order_id !== null) {
            $order_id = (array)$order_id;
            $product_ids = $this->query("SELECT DISTINCT product_id FROM shop_order_items
                WHERE type = 'product' AND order_id IN (i:order_id)", array('order_id' => $order_id))->fetchAll(null, true);
        }
        $sql = "SELECT oi.product_id AS id, SUM(oi.price * o.rate * oi.quantity) total_sales FROM ".$this->table." o JOIN shop_order_items oi
                ON o.id = oi.order_id AND oi.type = 'product'
                WHERE paid_date IS NOT NULL
                GROUP BY oi.product_id
                ORDER BY oi.product_id";
        if ($order_id === null) {
            $sql = "UPDATE `shop_product` p JOIN ($sql) r ON p.id = r.id SET p.total_sales = r.total_sales";
        } else if ($product_ids) {
            $sql = "UPDATE `shop_product` p JOIN ($sql) r ON p.id = r.id
                SET p.total_sales = r.total_sales
                WHERE p.id IN(".implode(',', $product_ids).")";
        }
        $this->query($sql);
    }

    public function getTotalSalesByProduct($product_id, $product_currency = null)
    {
        if ($product_currency) {
            $currency_model = new shopCurrencyModel();
            $rate = $currency_model->getRate($product_currency);
            if (!$rate) {
                $rate = 1;
            }
        } else {
            $rate = 1;
        }
        $sql = "SELECT SUM(oi.price * o.rate * oi.quantity) total, SUM(oi.quantity) quantity,
                SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, ps.purchase_price*".$this->escape($rate).")*oi.quantity) purchase
                FROM ".$this->table." o
                JOIN shop_order_items oi
                ON o.id = oi.order_id AND oi.product_id = i:product_id AND oi.type = 'product'
                JOIN shop_product_skus ps ON oi.sku_id = ps.id
                WHERE paid_date >= DATE_SUB(DATE('".date('Y-m-d')."'), INTERVAL 30 DAY)";
        return $this->query($sql, array('product_id' => $product_id))->fetch();
    }


    public function getSalesByProduct($product_id)
    {
        $sql = "SELECT o.paid_date, SUM(oi.price * o.rate * oi.quantity)
                FROM ".$this->table." o JOIN shop_order_items oi
                ON o.id = oi.order_id AND oi.product_id = i:product_id AND oi.type = 'product'
                WHERE o.paid_date >= DATE_SUB(DATE('".date('Y-m-d')."'), INTERVAL 30 DAY)
                GROUP BY o.paid_date";
        return $this->query($sql, array('product_id' => $product_id))->fetchAll('paid_date', true);
    }

    /** Data for profit report page */
    public function getProfit($start_date = null, $end_date = null, $group = null)
    {
        $date_col = ($group == 'months') ? "DATE_FORMAT(o.paid_date, '%Y-%m-01')" : 'o.paid_date';
        $paid_date_sql = self::getDateSql('o.paid_date', $start_date, $end_date);

        // Total sales, shipping and taxes
        $sql = "SELECT
                    {$date_col} as `date`,
                    SUM(o.total*o.rate) AS sales,
                    SUM(o.shipping*o.rate) AS shipping,
                    SUM(o.tax*o.rate) AS tax
                FROM ".$this->table." AS o
                WHERE $paid_date_sql
                GROUP BY {$date_col}";
        $min_date = null;
        $sales_by_date = $this->query($sql)->fetchAll('date');
        foreach($sales_by_date as &$row) {
            if (!$min_date || strcmp($min_date, $row['date']) > 0) {
                $min_date = $row['date'];
            }
            $row['purchase'] = 0;
            $row['profit'] = 0;
        }
        unset($row);

        // Total purchases
        $sql = "SELECT
                    {$date_col} as `date`,
                    SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, ps.purchase_price*pcur.rate)*oi.quantity) AS purchase
                FROM ".$this->table." AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                WHERE oi.type='product'
                    AND $paid_date_sql
                GROUP BY {$date_col}";
        foreach($this->query($sql) as $row) {
            $sales_by_date[$row['date']]['purchase'] = $row['purchase'];
            $row = &$sales_by_date[$row['date']];
            $row['profit'] = $row['sales'] - $row['purchase'] - $row['shipping'] - $row['tax'];
            unset($row);
        }

        // Add empty rows
        $empty_row = array(
            'sales' => 0,
            'shipping' => 0,
            'tax' => 0,
            'purchase' => 0,
            'profit' => 0,
        );
        if ($start_date) {
            $start_ts = strtotime($start_date);
        } else if ($min_date) {
            $start_ts = strtotime(ifempty($min_date, date('Y-m-d'))) - 48*3600;
        } else {
            $start_ts = strtotime(date('Y-m-01', strtotime("-1 months")));
        }
        $end_ts = strtotime(ifempty($end_date, date('Y-m-d')));
        for ($t = $start_ts; $t <= $end_ts; $t += 3600*24) {
            $date = date(($group == 'months') ? 'Y-m-01' : 'Y-m-d', $t);
            if (empty($sales_by_date[$date])) {
                $sales_by_date[$date] = array(
                    'date' => $date,
                ) + $empty_row;
            }
            foreach($empty_row as $k => $v) {
                $sales_by_date[$date][$k] = (float) $sales_by_date[$date][$k];
            }
        }
        ksort($sales_by_date);

        return $sales_by_date;
    }

    /** Data for sales report page */
    public function getSales($start_date = null, $end_date = null, $group = null)
    {
        $date_col = ($group == 'months') ? "DATE_FORMAT(o.paid_date, '%Y-%m-01')" : 'o.paid_date';
        $paid_date_sql = self::getDateSql('o.paid_date', $start_date, $end_date);
        $sql = "SELECT
                    {$date_col} AS `date`,
                    SUM(o.total*o.rate) AS total,
                    COUNT(*) AS `count`,
                    SUM(IF(o.is_first, o.total*o.rate, 0)) AS customer_first_total,
                    SUM(IF(o.is_first, 1, 0)) AS customer_first_count
                FROM {$this->table} AS o
                WHERE {$paid_date_sql}
                GROUP BY {$date_col}";

        // All rows from DB
        $min_date = null;
        $result = array(); // YYYY-MM-DD => array(...)
        foreach($this->query($sql) as $row) {
            if (!$min_date || strcmp($min_date, $row['date']) > 0) {
                $min_date = $row['date'];
            }
            $result[$row['date']] = $row;
        }

        // Add empty rows
        if ($start_date) {
            $start_ts = strtotime($start_date);
        } else if ($min_date) {
            $start_ts = strtotime(ifempty($min_date, date('Y-m-d'))) - 48*3600;
        } else {
            $start_ts = strtotime(date('Y-m-01', strtotime("-1 months")));
        }

        $end_ts = strtotime(ifempty($end_date, date('Y-m-d')));
        for ($t = $start_ts; $t <= $end_ts; $t += 3600*24) {
            $date = date(($group == 'months') ? 'Y-m-01' : 'Y-m-d', $t);
            if (empty($result[$date])) {
                $result[$date] = array(
                    'date' => $date,
                    'total' => 0,
                    'count' => 0,
                    'customer_first_total' => 0,
                    'customer_first_count' => 0,
                );
            }
            $result[$date]['total'] = (float) $result[$date]['total'];
        }
        ksort($result);

        return $result;
    }

    public function getTotalSales($start_date = null, $end_date = null)
    {
        $paid_date_sql = self::getDateSql('o.paid_date', $start_date, $end_date);
        $sql = "SELECT SUM(o.total*o.rate) AS total
                FROM ".$this->table." AS o
                WHERE $paid_date_sql";
        return $this->query($sql)->fetchField();
    }

    public function getTotalProfit($start_date = null, $end_date = null)
    {
        $paid_date_sql = self::getDateSql('o.paid_date', $start_date, $end_date);

        $sql = "SELECT
                    SUM(o.total*o.rate) AS total,
                    SUM(o.shipping*o.rate) AS shipping,
                    SUM(o.tax*o.rate) AS tax
                FROM ".$this->table." AS o
                WHERE $paid_date_sql";
        $r1 = $this->query($sql)->fetchAssoc();

        $sql = "SELECT SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, ps.purchase_price*pcur.rate)*oi.quantity) AS purchase
                FROM ".$this->table." AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                WHERE $paid_date_sql";
        $r2 = $this->query($sql)->fetchAssoc();

        return $r1['total'] - $r2['purchase'] - $r1['shipping'] - $r1['tax'];
    }

    public static function getDateSql($fld, $start_date, $end_date)
    {
        $paid_date_sql = array();
        if ($start_date) {
            $paid_date_sql[] = $fld." >= DATE('".$start_date."')";
        }
        if ($end_date) {
            $paid_date_sql[] = $fld." <= DATE('".$end_date."')";
        }
        if ($paid_date_sql) {
            return implode(' AND ', $paid_date_sql);
        } else {
            return $fld." IS NOT NULL";
        }
    }

    public function getTotalSkuSalesByProduct($product_id, $product_currency = null)
    {
        if ($product_currency) {
            $currency_model = new shopCurrencyModel();
            $rate = $currency_model->getRate($product_currency);
            if (!$rate) {
                $rate = 1;
            }
        } else {
            $rate = 1;
        }
        $sql = "SELECT sku_id, SUM(oi.price * o.rate * oi.quantity) total, SUM(oi.quantity) quantity,
                SUM(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, ps.purchase_price*".$this->escape($rate).")*oi.quantity) purchase
                FROM ".$this->table." o
                JOIN shop_order_items oi ON o.id = oi.order_id AND oi.product_id = i:product_id AND oi.type = 'product'
                JOIN shop_product_skus ps ON oi.sku_id = ps.id
                WHERE paid_date >= DATE_SUB(DATE('".date('Y-m-d')."'), INTERVAL 30 DAY)
                GROUP BY oi.sku_id";
        return $this->query($sql, array('product_id' => $product_id))->fetchAll('sku_id');
    }

    public function getByCoupon($coupon_id)
    {
        $sql = "SELECT o.*
                FROM shop_order AS o
                    JOIN shop_order_params AS p
                        ON p.order_id=o.id
                WHERE p.name='coupon_id'
                    AND p.value=:cid";
        $result = $this->query($sql, array('cid' => $coupon_id))->fetchAll('id');
        $this->workupList($result, 'params', false);
        return $result ? $result : array();
    }

    public function autocompleteById($q, $limit, $full=false)
    {
        $q = $this->escape($q, 'like');
        if ($full) {
            $q = '%'.$q.'%';
        } else {
            $q = $q."%";
        }
        $sql = "SELECT o.id, o.state_id, o.total, o.currency, c.name AS customer_name
                FROM {$this->table} AS o
                    JOIN wa_contact AS c
                        ON c.id=o.contact_id
                WHERE o.id LIKE '$q'
                ORDER BY o.id DESC
                LIMIT $limit";
        return $this->query($sql)->fetchAll();
    }
}

