<?php
/**
 * @author Webasyst
 *
 * Note the currencies for different price fields:
 *
 * - shop_product.price                      primary currency
 * - shop_product.min_price                  primary currency
 * - shop_product.max_price                  primary currency
 * - shop_product.compare_price              primary currency
 * - shop_product.total_sales                primary currency
 * - shop_product.compare_price_selectable   shop_product.currency
 * - shop_product.base_price_selectable      shop_product.currency
 * - shop_product.purchase_price_selectable  shop_product.currency
 *
 */
class shopProductModel extends waModel
{
    const STATUS_ACTIVE = 1;
    const STATUS_DISABLED = 2;
    const STATUS_DRAFT = 3;

    const SKU_TYPE_FLAT = 0;
    const SKU_TYPE_SELECTABLE = 1;

    protected $table = 'shop_product';

    public function getByUrl($url, $category_id = null)
    {
        $sql = "SELECT p.* FROM ".$this->table." p";
        if ($category_id) {
            $sql .= " JOIN shop_category_products cp ON p.id = cp.product_id AND cp.category_id = ".(int)$category_id;
        }
        $sql .= " WHERE p.url = s:0 LIMIT 1";
        return $this->query($sql, $url)->fetch();
    }

    public function delete(array $product_ids)
    {
        if (wa()->getEnv() !== 'cli') {
            $delete_ids = $this->filterAllowedProductIds($product_ids);
        } else {
            $delete_ids = $product_ids;
        }

        if (empty($delete_ids)) {
            return false;
        }

        $params = array('ids' => $delete_ids);
        /**
         * @event product_delete
         * @param array [string]mixed $params
         * @param array [string]array $params['ids'] Array of IDs of deleted product entries
         * @return void
         */
        wa('shop')->event('product_delete', $params);

        // remove files
        foreach ($delete_ids as $product_id) {
            $paths = array(
                shopProduct::getPath($product_id, null, false),
                shopProduct::getPath($product_id, null, true),
            );
            foreach ($paths as $path) {
                try {
                    waFiles::delete($path);                 // xx/yy/product_id/
                    if (@rmdir(dirname($path))) {           // xx/yy/    (if empty)
                        @rmdir(dirname(dirname($path)));    // xx/       (if empty)
                    }
                } catch (waException $e) {
                }
            }
        }

        // remove from related models
        foreach (array(
                     new shopProductFeaturesModel(),
                     new shopProductImagesModel(),
                     new shopProductReviewsModel(),
                     new shopProductServicesModel(),
                     new shopProductSkusModel(),
                     new shopProductStocksModel(),
                     new shopProductStocksLogModel(),
                     new shopProductTagsModel(),
                     new shopCategoryProductsModel(),
                     new shopSetProductsModel(),
                     new shopSearchIndexModel(),
                     new shopProductFeaturesSelectableModel(),
                     new shopProductParamsModel(),
                     new shopCartItemsModel()
                 ) as $model) {
            /**
             * @var shopProductStorageInterface $model
             */
            $model->deleteByProducts($delete_ids);
        }

        $type_ids = $this
            ->select('DISTINCT `type_id`')
            ->where($this->getWhereByField($this->id, $delete_ids))
            ->fetchAll('type_id');

        // remove records
        if ($this->deleteById($delete_ids)) {
            $type_model = new shopTypeModel();
            $type_model->recount(array_keys($type_ids));
            if ($cache = wa('shop')->getCache()) {
                $cache->deleteGroup('sets');
            }
            return $delete_ids;
        }
        return false;
    }

    /**
     * Get aggregated data about placing products(skus) in stocks
     *
     * @param $options array
     *
     * @return array multilevel array
     *
     * First level: products
     * Second level: skus and stocks (guarantee that count of subarray 'stocks' is count of stocks of shop
     * Third level: if 2d level is stocks than skus placed in this stock
     *
     * @example array(
     *   array(
     *     'id' => 123
     *     'name' => 'Product123',
     *     'total_count' => 40,
     *     'skus' => array(
     *         // all skus for product indexed by sku_id. Count is numeric or NULL
     *         '1' => array( 'id' => '1', 'name' => 'Sku1OfProduct123', 'count' => 10 ),
     *         '2' => array( 'id' => '2', 'name' => 'Sku2OfProduct123', 'count' => 15 ),
     *         '3' => array( 'id' => '3', 'name' => 'Sku3OfProduct123', 'count' => 25 )
     *      ),
     *      'stocks' => array(
     *          // indexed by stock_id. Info about all stocks.
     *          '111' => array(
     *              // all skus for product indexed by sku_id. Count is numeric or NULL
     *              '1' => array( 'id' => '1', 'name' => 'Sku1OfProduct123', 'count' => null ),
     *              '2' => array( 'id' => '2', 'name' => 'Sku2OfProduct123', 'count' => null ),
     *              '3' => array( 'id' => '3', 'name' => 'Sku3OfProduct123', 'count' => null )
     *          )
     *          '222' => array(
     *              // all skus for product indexed by sku_id. Count is numeric or NULL
     *              '1' => array( 'id' => '1', 'name' => 'Sku1OfProduct123', 'count' => 3 ),
     *              '2' => array( 'id' => '2', 'name' => 'Sku2OfProduct123', 'count' => null ),
     *              '3' => array( 'id' => '3', 'name' => 'Sku3OfProduct123', 'count' => 16 )
     *          ),
     *          '333' => array(
     *              // all skus for product indexed by sku_id. Count is numeric or NULL
     *              '1' => array( 'id' => '1', 'name' => 'Sku1OfProduct123', 'count' => 7 ),
     *              '2' => array( 'id' => '2', 'name' => 'Sku2OfProduct123', 'count' => 15 ),
     *              '3' => array( 'id' => '3', 'name' => 'Sku3OfProduct123', 'count' => 9 )
     *          )
     *      )
     *   )
     * )
     */
    public function getProductStocks($options = array())
    {
        if (func_num_args() > 1 && !is_array(func_get_arg(0))) {
            $args = func_get_args();
            $options['offset'] = ifset($args[0], 0);
            $options['limit'] = ifset($args[1], 0);
            $options['order'] = ifset($args[2], '');
        }

        $order = strtolower((string) ifset($options['order'])) === 'desc' ? 'DESC' : 'ASC';
        $offset = (int) ifset($options['offset']);
        $limit = (int) ifset($options['limit']);
        $sort = (string) ifset($options['sort']);

        $sql_ar = array(
            'SELECT' => array('p.id'),
            'FROM' => $this->table . ' p',
            'LEFT JOIN' => array(),
            'WHERE' => array(),
            'GROUP BY' => array(),
            'ORDER BY' => array(),
            'LIMIT' => "{$offset}, {$limit}"
        );

        // define stock_id for sorting
        $parsed = $this->parseStockSortKey($sort);
        $stock_id = $parsed['id'];

        // fill sql array
        if ($stock_id) {
            $sql_ar['LEFT JOIN'] = "shop_product_stocks sps ON sps.product_id = p.id AND sps.stock_id = {$stock_id}";
            if (!empty($options['count_is_not_null'])) {
                $sql_ar['WHERE'][] = "sps.`count` IS NOT NULL";
            }
            $sql_ar['GROUP BY'][] = "p.id";
            $sql_ar['ORDER BY'][] = "SUM(sps.count) {$order}";
        } else {
            if (!empty($options['count_is_not_null'])) {
                $sql_ar['WHERE'][] = "p.`count` IS NOT NULL";
            }
            $sql_ar['ORDER BY'][] = "p.`count` {$order}";
        }

        $sql = $this->buildSql($sql_ar);

        $ids = array_keys($this->query($sql)->fetchAll('id'));

        $sort = 'count';
        if ($stock_id) {
            $sort = 'stock_count_' . $stock_id;
        }

        return $this->getProductStocksByProductId($ids, $order, $sort);
    }

    private function parseStockSortKey($sort)
    {
        $stock_id = null;
        $prefix = 'stock_count';
        $prefix_len = strlen($prefix);
        if (strpos($sort, $prefix) === 0) {
            $stock_id = (int) substr($sort, $prefix_len + 1);
        }
        return array('id' => $stock_id, 'field' => 'count');
    }

    public function getWithCategoryUrl($ids)
    {
        if (!$ids) {
            return array();
        }
        $sql = "SELECT p.*, c.full_url category_url FROM ".$this->table." p
                LEFT JOIN shop_category c ON p.category_id = c.id
                WHERE p.id IN (i:ids)";
        return $this->query($sql, array('ids' => $ids))->fetchAll('id');
    }

    /**
     * Get aggregated data about placing products(skus) in stocks
     *
     * @see getProductStocks
     *
     * @param int|array $product_id
     * @param string $order
     * @return array
     */
    public function getProductStocksByProductId($product_id, $order = 'desc', $sort = 'count')
    {
        if (!$product_id) {
            return array();
        }
        $product_ids = (array)$product_id;
        $product_ids_str = implode(',', $product_ids);

        $order = ($order == 'desc' || $order == 'DESC') ? 'DESC' : 'ASC';

        // necessary models
        $stock_model = new shopStockModel();
        $product_images_model = new shopProductImagesModel();

        // stock ids of items ordered by sort
        $stock_ids = array_keys($stock_model->getAll('id'));

        // get products sql array structure
        $sql_ar = array(
            'SELECT' => array('p.id', 'p.name', 'p.count', 'p.image_id'),
            'FROM' => $this->table . ' p',
            'LEFT JOIN' => array(),
            'WHERE' => array("p.id IN ( {$product_ids_str} )"),
            'GROUP BY' => array(),
            'ORDER BY' => array(),
        );

        // define stock_id for sorting
        $parsed = $this->parseStockSortKey($sort);
        $stock_id = $parsed['id'];

        // fill sql array
        if ($stock_id) {
            $sql_ar['SELECT'][] = "{$stock_id} AS selected_stock_id, SUM(sps.count) AS selected_stock_count";
            $sql_ar['LEFT JOIN'] = "shop_product_stocks sps ON sps.product_id = p.id AND sps.stock_id = {$stock_id}";
            $sql_ar['GROUP BY'][] = "p.id";
            $sql_ar['ORDER BY'][] = "IF(SUM(sps.count) IS NULL, 1, 0) ASC, SUM(sps.count) {$order}";
        } else {
            if (!empty($options['count_is_not_null'])) {
                $sql_ar['WHERE'][] = "p.`count` IS NOT NULL";
            }
            $sql_ar['ORDER BY'][] = "p.`count` {$order}";
        }

        // get products
        $sql = $this->buildSql($sql_ar);

        $data = array();
        $image_ids = array();
        foreach ($this->query($sql) as $item) {
            $data[$item['id']] = $item + array(
                'url_crop_small' => null,
                'skus'           => array(),
                'stocks'         => array()
            );
            if ($item['image_id'] != null) {
                $image_ids[] = $item['image_id'];
            }
        }

        if (!$data) {
            return array();
        }

        $product_ids = array_keys($data);
        $product_ids_str = implode(',', $product_ids);

        $images = $product_images_model->getByField('id', $image_ids, 'product_id');
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $size = $config->getImageSize('crop_small');

        // get for skus number of stocks in which it presents
        $sql = "
            SELECT sk.id, COUNT(sk.id) num_of_stocks
            FROM shop_product_skus sk
            JOIN shop_product_stocks st ON sk.id = st.sku_id
            WHERE sk.product_id IN ( {$product_ids_str} )
            GROUP BY sk.id
        ";
        $num_of_stocks = $this->query($sql)->fetchAll('id', true);

        // get info about skus and stocks
        $sql = "SELECT
                    sk.product_id,
                    sk.id AS sku_id,
                    sk.name AS sku_name,
                    sk.count,

                    pst.stock_id,
                    pst.count AS stock_count
                FROM shop_product_skus sk
                LEFT JOIN shop_product_stocks pst ON pst.sku_id = sk.id
                WHERE sk.product_id IN ( {$product_ids_str} )
                ORDER BY sk.product_id, sk.count $order, sk.id";

        $stocks_count = count($stock_ids);

        // temporary collect info about stocks
        $sku_stocks = array();
        if ($stocks_count) {
            $sku_stocks = array_fill(0, $stocks_count, array());
        }

        $sku_id = 0;
        $product_id = 0;
        $p_product = null;
        foreach ($this->query($sql) as $item) {
            // another product
            if ($product_id != $item['product_id']) {
                $product_id = $item['product_id'];
                $p_product =& $data[$product_id];
                if (isset($images[$product_id])) {
                    $p_product['url_crop_small'] = shopImage::getUrl($images[$product_id], $size);
                }
            }
            // another sku
            if ($sku_id != $item['sku_id']) {
                $sku_id = $item['sku_id'];
                $p_product['skus'][$sku_id] = array(
                    'id'            => $sku_id,
                    'name'          => $item['sku_name'],
                    'count'         => $item['count'],
                    'num_of_stocks' => isset($num_of_stocks[$sku_id]) ? $num_of_stocks[$sku_id] : 0
                );
            }

            // aggregate info about stocks
            if ($item['stock_id'] !== null) {
                $sku_stocks[$item['stock_id']][$sku_id] = $item['stock_count'];
            }
        }

        // lay out stocks info
        if (!empty($sku_stocks)) {
            foreach ($data as & $product) {
                foreach ($stock_ids as $stock_id) {
                    foreach ($product['skus'] as $sku_id => $sku) {
                        $product['stocks'][$stock_id][$sku_id] = array(
                            'id'            => $sku_id,
                            'name'          => $sku['name'],
                            'count'         => isset($sku_stocks[$stock_id][$sku_id]) ? $sku_stocks[$stock_id][$sku_id] : null,
                            'num_of_stocks' => $sku['num_of_stocks']
                        );
                    }
                }
            }
            unset($product);
        }

        return $data;
    }

    private function buildSql($sql_ar)
    {
        $glues = array(
            'SELECT' => ',',
            'WHERE' => ' AND ',
            'JOIN' => ' ',
            'LEFT JOIN' => ' ',
            'ORDER BY' => ','
        );

        foreach ($sql_ar as $statement => $values) {
            if (empty($values)) {
                unset($sql_ar[$statement]);
                continue;
            }
            if (is_array($values)) {
                $glue = ifset($glues[$statement], ' ');
                $sql_ar[$statement] = join($glue, $values);
            }

            $sql_ar[$statement] = $statement . ' ' . $sql_ar[$statement];
        }

        return join(PHP_EOL, $sql_ar);
    }

    /**
     * @param int $product
     * @param int|null $category_id
     * @return array
     */
    public function getStorefrontMap($product, $category_id = null)
    {
        $storefronts_map = array();

        $product_id = (int)$product;

        // All categories of this product
        $sql = "SELECT c.*
                FROM shop_category_products AS cp
                    JOIN shop_category AS c
                        ON cp.category_id = c.id
                WHERE cp.product_id = ?
                ORDER BY c.left_key";
        $product_categories = $this->query($sql, $product_id)->fetchAll('id');

        $product_type = $this->select('type_id')->where('id='.$product_id)->fetchField();
        if (!$product_categories || !$product_type) {
            return array();
        }

        if ($category_id !== null) {
            if (isset($product_categories[$category_id])) {
                $product_categories = array($category_id => $product_categories[$category_id]);
            } else {
                return array();
            }
        }
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');

        $category_routes_model = new shopCategoryRoutesModel();
        $category_routes = $category_routes_model->getRoutes(array_keys($product_categories));
        foreach ($product_categories as $c_id => &$category) {
            $category['routes'] = isset($category_routes[$c_id]) ? $category_routes[$c_id] : array();
        }
        unset($category);

        foreach ($product_categories as $c_id => $category) {
            $storefronts_map[$c_id] = array();
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!empty($r['private'])) {
                        continue;
                    }

                    if ((empty($r['type_id']) || (in_array($product_type, (array)$r['type_id']))) &&
                        (!$category['routes'] || in_array($domain.'/'.$r['url'], $category['routes']))
                    ) {
                        $routing->setRoute($r, $domain);
                        $storefronts_map[$c_id][] = $routing->getUrl('shop/frontend/category', array(
                            'category_url' => isset($r['url_type']) && ($r['url_type'] == 1) ? $category['url'] : $category['full_url']
                        ), true);
                    }
                }
            }
        }

        $all_routes_count = 0;
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $r) {
                if (!empty($r['private'])) {
                    continue;
                }
                $all_routes_count += 1;
            }
        }

        foreach ($storefronts_map as $c_id => &$storefronts_list) {
            if (count($storefronts_list) == $all_routes_count) {
                $storefronts_list = array();
            }
        }
        unset($storefronts_list);

        if ($category_id !== null) {
            return $storefronts_map[$category_id];
        } else {
            return $storefronts_map;
        }
    }

    /**
     * Correct main category of products
     *
     * The key point: if main category of product is corrected it is does not affect
     *
     * @param null|int|array $product_ids filter by product ID
     * @param null|int|array $category_ids filter by product.category_id
     * @return bool|resource
     */
    public function correctMainCategory($product_ids = null, $category_ids = null)
    {
        $where = array();
        if ($product_ids) {
            $where[] = "p.id IN (".implode(',', (array)$product_ids).") ";
        }
        if ($category_ids) {
            $where[] = "p.category_id IN (".implode(',', (array)$category_ids).")";
        }

        // correct products with category_id IS NULL, but belonging to at least one category
        $sql = "
            UPDATE `{$this->table}` p
            JOIN `shop_category_products` cp ON p.id = cp.product_id
            SET p.category_id = cp.category_id
            WHERE p.category_id IS NULL
        ";
        if ($where) {
            $sql .= " AND ".implode(' AND ', $where);
        }

        $this->exec($sql);

        // correct products with category_id related with nonexistent categories
        $sql = "
            SELECT p.id FROM `{$this->table}` p
            LEFT JOIN `shop_category_products` cp ON p.id = cp.product_id AND p.category_id = cp.category_id
            WHERE p.category_id IS NOT NULL AND cp.category_id IS NULL
        ";
        if ($where) {
            $sql .= " AND ".implode(' AND ', $where);
        }
        $sql = "UPDATE `{$this->table}` p
                JOIN ($sql) r ON p.id = r.id
                LEFT JOIN `shop_category_products` cp ON p.id = cp.product_id
                SET p.category_id = cp.category_id";

        return $this->exec($sql);
    }

    public function correctCount()
    {
        // Repair: count of product == sum of count of skus
        // (negative sku counts are considered to be zero)
        $sql = "
            UPDATE shop_product p
                JOIN (
                    SELECT s.product_id id, SUM(IF(s.count < 0, 0, s.count)) count_of_skus, SUM(s.count IS NULL) has_infinity_count
                    FROM shop_product_skus s
                    WHERE s.available > 0 AND s.status > 0
                    GROUP BY s.product_id
                ) t ON p.id = t.id
            SET p.count = IF(has_infinity_count, NULL, count_of_skus)
        ";
        $this->exec($sql);

        // Repair: if all skus of product are unavailable, product.count must be 0
        $sql = "
            UPDATE shop_product p JOIN (
                SELECT sk.product_id id, SUM(sk.available) some_sku_available
                FROM shop_product_skus sk
                GROUP BY sk.product_id
                HAVING some_sku_available = 0
            ) r ON p.id = r.id
            SET p.count = 0
        ";
        $this->exec($sql);
    }

    public function countProductStocks($options = array())
    {
        $where = '';
        if (!empty($options['count_is_not_null'])) {
            $where = "WHERE `count` IS NOT NULL";
        }
        return $this->query("SELECT COUNT(*) FROM `{$this->table}` {$where}")->fetchField();
    }

    /**
     * Update type of product and update relation information
     * @param int|array $id product ID
     * @param int $type_id new type ID
     * @return boolean
     */
    public function updateType($id, $type_id)
    {
        if (!is_array($id)) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            if ($item['type_id'] == $type_id) {
                return true;
            }
            if (!$this->updateById($id, array('type_id' => $type_id))) {
                return false;
            }
            $type_model = new shopTypeModel();
            $type_model->incCounters(array(
                $item['type_id'] => '-1',
                $type_id         => '+1'
            ));
        } else {
            if (!$this->updateById($id, array('type_id' => $type_id))) {
                return false;
            }
            $type_model = new shopTypeModel();
            $type_model->recount();
        }
        return true;
    }

    /**
     * Change type of all products from $from_type_id to $to_type_id
     * @param int $from_type_id
     * @param int $to_type_id
     * @return boolean
     */
    public function changeType($from_type_id, $to_type_id)
    {
        $sql = "UPDATE `{$this->table}` SET type_id = ".(int)$to_type_id." WHERE type_id = ".(int)$from_type_id;
        if (!$this->exec($sql)) {
            return false;
        }

        $type_model = new shopTypeModel();
        $type_model->recount(array($from_type_id, $to_type_id));

        return true;
    }

    public function getCurrency($product_id)
    {
        return $this->select('currency')->where('id='.(int)$product_id)->fetchField('currency');
    }

    /**
     * Returns the currency of product
     * @param array $product_ids
     * @return array
     */
    public function getCurrencies($product_ids)
    {
        if (empty($product_ids)) {
            return [];
        }
        $sql = "SELECT id, currency
                FROM {$this->table}
                WHERE id IN (i:ids)";

        return $this->query($sql, ['ids' => $product_ids])->fetchAll('id');
    }

    public function getTop($limit, $order = 'sales', $start_date = null, $end_date = null, $options = array())
    {
        $paid_date_sql = shopOrderModel::getDateSql('o.paid_date', $start_date, $end_date);

        if ($order !== 'sales' && $order !== 'quantity') {
            $order = 'profit';
        }
        $limit = (int)$limit;
        $limit = ifempty($limit, 10);

        $storefront_join = '';
        $storefront_where = '';
        if (!empty($options['storefront'])) {
            $storefront_join = "JOIN shop_order_params AS op2
                                    ON op2.order_id=o.id
                                        AND op2.name='storefront'";
            $storefront_where = "AND op2.value='".$this->escape($options['storefront'])."'";
        }
        if (!empty($options['sales_channel'])) {
            $storefront_join .= " JOIN shop_order_params AS opst2
                                    ON opst2.order_id=o.id
                                        AND opst2.name='sales_channel' ";
            $storefront_where .= " AND opst2.value='".$this->escape($options['sales_channel'])."' ";
        }

        $sales_subtotal = '(oi.price*o.rate*oi.quantity)';
        $order_subtotal = '(o.total+o.discount-o.tax-o.shipping)';
        $discount = "IF({$order_subtotal} <= 0, 0, oi.price*o.rate*oi.quantity*o.discount / {$order_subtotal})";
        $purchase = '(IF(oi.purchase_price > 0, oi.purchase_price*o.rate, ps.purchase_price*pcur.rate)*oi.quantity)';

        // !!! With 15k orders this query takes ~3 seconds
        $sql = "SELECT
                    p.*,
                    SUM({$sales_subtotal} - {$discount}) AS sales,
                    SUM({$sales_subtotal} - {$discount} - {$purchase}) AS profit,
                    SUM({$sales_subtotal}) AS sales_subtotal,
                    SUM({$discount}) AS discount,
                    SUM({$purchase}) AS purchase,
                    SUM(oi.quantity) AS quantity
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                    {$storefront_join}
                WHERE $paid_date_sql
                    AND oi.type = 'product'
                    {$storefront_where}
                GROUP BY p.id
                ORDER BY $order DESC
                LIMIT $limit";
        return $this->query($sql);
    }

    public static function badges()
    {
        return array(
            'new' => array(
                'name' => _wd('shop', 'New!'),
                'code' => '<div class="badge new"><span>' . _wd('shop','New!') . '</span></div>'
            ),
            'bestseller' => array(
                'name' => _wd('shop', 'Bestseller!'),
                'code' => '<div class="badge bestseller"><span>' . _wd('shop','Bestseller!') . '</span></div>'
            ),
            'lowprice' => array(
                'name' => _wd('shop', 'Low price!'),
                'code' => '<div class="badge low-price"><span>' . _wd('shop','Low price!') . '</span></div>'
            ),
        );
    }

    /**
     * Get product ids and leave only allowed by rights.
     *
     * Used to remove products current user is not allowed to delete.
     * shopRightConfig::RIGHT_EDIT means user can only modify products they created.
     * This checks shop_product.contact_id
     *
     * @see checkRights
     * @see shopProductsCollection::addWhereByRights()
     *
     * @param array $product_ids
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function filterAllowedProductIds(array $product_ids)
    {
        if (wa('shop')->getUser()->getRights('shop', 'type.all') > shopRightConfig::RIGHT_EDIT) {
            return $product_ids;
        }

        $type_model = new shopTypeModel();
        $types = $type_model->getTypes();

        if (empty($product_ids) || empty($types)) {
            return array();
        }

        $full_types = $own_types = array();
        foreach ($types as $type_id => $t) {
            // user can delete own products only
            if (wa()->getUser()->getRights('shop', 'type.'.$type_id) == shopRightConfig::RIGHT_EDIT) {
                $own_types[] = $type_id;
            } elseif (wa()->getUser()->getRights('shop', 'type.'.$type_id) > shopRightConfig::RIGHT_EDIT) {
                $full_types[] = $type_id;
            }
        }

        $where = array();
        if ($full_types) {
            $where[] = '(type_id IN ('.implode(',', $full_types).'))';
        }
        if ($own_types) {
            $where[] = '(type_id IN ('.implode(',', $own_types).') AND contact_id = '.(int)wa()->getUser()->getId().')';
        }

        if (!$where) {
            return array();
        }

        $where = implode(' OR ', $where);

        $product_ids = array_map('intval', $product_ids);
        $sql = "
            SELECT id FROM `{$this->table}`
            WHERE id IN(".implode(',', $product_ids).")
                AND (".$where.")";

        $product_ids = array_keys($this->query($sql)->fetchAll('id'));

        return $product_ids;
    }

    /**
     * Check current user rights to one product by it type id
     *
     * See also how works these methods:
     * @param array|int $product ID or db record of a single product
     *
     * @param array $options extra options
     *  - int|string $options['level'] [optional]
     *      If numeric, that min level to check
     *      If string 'delete' - check can contact delete product
     *      If skipped just return rights level as it (shopRightConfig::RIGHT_*)
     *
     * @return boolean|int
     * @throws waException
     *
     * @see shopProductsCollection::addWhereByRights()
     * @see shopRightConfig
     * @see filterAllowedProductIds
     */
    public function checkRights($product, $options = array())
    {
        // what level to check option
        $options = is_array($options) ? $options : array();
        $options['level'] = ifset($options['level']);

        // current user info vars
        $user = wa()->getUser();
        $user_id = $user->getId();

        // admins can do anything
        if ($user->isAdmin('webasyst') || $user->isAdmin('shop')) {
            if (wa_is_int($options['level']) || $options['level'] === 'delete') {
                return true;
            } else {
                return PHP_INT_MAX;
            }
        }

        // No access to backend => no access to product
        if ($user->getRights('shop', 'backend') <= 0) {
            if (wa_is_int($options['level']) || $options['level'] === 'delete') {
                return false;
            } else {
                return 0;
            }
        }

        // vars to check rights
        $type_id = null;
        $product_contact_id = null;

        // get product info
        if (is_numeric($product)) {
            $product = $this->select('type_id, contact_id')->where('id=' . (int)$product)->fetchAssoc();
        }

        // fill vars for check rights
        if (is_array($product)) {
            if (isset($product['type_id'])) {
                $type_id = $product['type_id'];
            }
            if (isset($product['contact_id'])) {
                $product_contact_id = (int)$product['contact_id'];
            }
        }

        // current user's right level to this product
        $rights_level = $user->getRights('shop', 'type.'.$type_id);

        // what return dispatch
        if (wa_is_int($options['level'])) {
            return $rights_level >= $options['level'];
        } elseif ($options['level'] === 'delete') {
            return $rights_level > shopRightConfig::RIGHT_EDIT || ($rights_level == shopRightConfig::RIGHT_EDIT && $user_id === $product_contact_id);
        } else {
            return $rights_level;
        }

    }

    /**
     * @param int $id
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function correct($id)
    {
        if (!$id) {
            return false;
        }
        $id = (int)$id;
        $product = $this->getById($id);

        $product_skus_model = new shopProductSkusModel();
        $skus = $product_skus_model->getDataByProductId($id, true);

        $currency_model = new shopCurrencyModel();
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $currency = $config->getCurrency();

        $price = array();
        $update_product_data = array();

        // aggregate count by stocks for product
        // Invariant: if at least one sku.count IS NULL this aggregate count IS NULL
        $product_count = 0;

        $available_sku_count = 0;
        foreach ($skus as $sku) {
            if ($sku['available'] && $sku['status']) {
                $available_sku_count++;
            }

            $price[] = $this->castValue('double', $sku['price']);

            $sku_count = 0;
            $num_of_null = 0;
            foreach ($sku['stock'] as $count) {
                if ($count === null) {
                    // turn into NULL and is not longer changing
                    $sku_count = null;
                    $num_of_null++;
                } else {
                    // Once turned into NULL value is not changed
                    if ($sku_count !== null) {
                        $sku_count += $count;
                    }
                }
            }
            if ($num_of_null == count($sku['stock'])) {
                // all stock count is null means that not multistocking
                $sku_count = $sku['count'];
            }

            // maintain product_count invariant. See above
            if ($sku['available'] && $sku['status']) {
                if ($sku_count === null) {
                    $product_count = null;
                } elseif ($product_count !== null) {
                    $product_count += max(0, $sku_count);
                }
            }
        }

        if ($available_sku_count == 0) {
            $product_count = 0;
        }

        if (!$price) {
            $price[] = 0;
        }
        $update_product_data['sku_count'] = count($skus);
        $update_product_data['min_price'] = $currency_model->convert(min($price), $product['currency'], $currency);
        $update_product_data['max_price'] = $currency_model->convert(max($price), $product['currency'], $currency);
        $update_product_data['price'] = $currency_model->convert($skus[$product['sku_id']]['price'], $product['currency'], $currency);
        if (isset($skus[$product['sku_id']]['compare_price'])) {
            $update_product_data['compare_price'] = $currency_model->convert($skus[$product['sku_id']]['compare_price'], $product['currency'], $currency);
        }

        $update_product_data['count'] = $product_count;

        $this->updateById($product['id'], $update_product_data);

        return true;
    }

    public function updateById($id, $data, $options = null, $return_object = false)
    {
        if ($cache = wa('shop')->getCache()) {
            $cache->deleteGroup('sets');
        }
        return parent::updateById($id, $data, $options, $return_object);
    }

    public function existsSelectableProducts()
    {
        return $this->select(1)->where('sku_type = 1')->limit(1);
    }
}
