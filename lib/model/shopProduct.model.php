<?php
/**
 *
 * @author WebAsyst Team
 * @version SVN: $Id: shopProduct.model.php 2031 2012-08-17 16:18:20Z vlad $
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

    public function delete(array $product_ids)
    {
        $delete_ids = array();

        // remove files
        foreach ($product_ids as $product_id) {
            try {
                waFiles::delete(shopProduct::getPath($product_id, null, false));
                waFiles::delete(shopProduct::getPath($product_id, null, true));
                $delete_ids[] = (int) $product_id;
            } catch (waException $e) {
            }
        }

        if (empty($delete_ids)) {
            return false;
        }

        $params = array('ids' => $delete_ids);
        /**
         * @event product_delete
         * @param array[string]mixed $params
         * @param array[string]array $params['ids'] Array of IDs of deleted product entries
         * @return void
         */
        wa()->event('product_delete', $params);

        // remove from related models
        foreach (array(
            new shopProductFeaturesModel(),
            new shopProductImagesModel(),
            new shopProductReviewsModel(),
            new shopProductServicesModel(),
            new shopProductSkusModel(),
            new shopProductStocksModel(),
            new shopProductTagsModel(),
            new shopCategoryProductsModel(),
            new shopSetProductsModel(),
            new shopSearchIndexModel(),
            new shopProductFeaturesSelectableModel(),
            new shopProductParamsModel()
        ) as $model) {
            $model->deleteByProducts($delete_ids);
        }

        $type_ids = array_keys($this->query("SELECT DISTINCT type_id FROM `{$this->table}` WHERE id IN(".implode(',', $delete_ids).")")->fetchAll('type_id'));
        // remove records
        if ($this->deleteById($delete_ids)) {
            $type_model = new shopTypeModel();
            $type_model->recount($type_ids);
            return $delete_ids;
        }
        return false;
    }

    /**
     * Get aggregated data about placing products(skus) in stocks
     *
     * @param int $offset
     * @param int $count
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
    public function getProductStocks($offset, $count, $order = 'desc')
    {
        $order = ($order == 'desc' || $order == 'DESC') ? 'DESC' : 'ASC';

        // get products ids
        $sql = "SELECT id FROM {$this->table} ORDER BY count $order LIMIT ".(int) $offset.", ".(int) $count;
        $ids = array_keys($this->query($sql)->fetchAll('id'));

        return $this->getProductStocksByProductId($ids, $order);
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
    public function getProductStocksByProductId($product_id, $order = 'desc')
    {
        if (!$product_id) {
            return array();
        }
        $product_ids = (array) $product_id;
        $product_ids_str = implode(',', $product_ids);

        $order = ($order == 'desc' || $order == 'DESC') ? 'DESC' : 'ASC';

        // necessary models
        $stock_model = new shopStockModel();
        $product_images_model = new shopProductImagesModel();

        // stock ids of items ordered by sort
        $stock_ids = array_keys($stock_model->getAll('id'));

        // get products
        $sql = "
            SELECT id, name, count, image_id
            FROM {$this->table}
            WHERE id IN ( {$product_ids_str} )
            ORDER BY count $order
        ";

        $data = array();
        $image_ids = array();
        foreach ($this->query($sql) as $item) {
            $data[$item['id']] = array(
                'id'             => $item['id'],
                'name'           => $item['name'],
                'url_crop_small' => null,
                'count'          => $item['count'],
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
        $size = wa()->getConfig()->getImageSize('crop_small');

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

        // temporary aggragating info about stocks
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

    /**
     * Correct main category of products
     *
     * The key point: if main category of product is corrected it is does not affect
     *
     * @param null|int|array $product_ids  filter by product ID
     * @param null|int|array $category_ids filter by product.category_id
     */
    public function correctMainCategory($product_ids = null, $category_ids = null)
    {
        $where = array();
        if ($product_ids) {
            $where[] = "p.id IN (".implode(',', (array) $product_ids).") ";
        }
        if ($category_ids) {
            $where[] = "p.category_id IN (".implode(',', (array) $category_ids).")";
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
        // Repair this invariant:
        // If sku.count IS NULL proper product.count must be NULL
        $sql = "
            UPDATE `shop_product` p
            JOIN `shop_product_skus` s ON s.product_id = p.id
            SET p.count = NULL
            WHERE s.count IS NULL AND s.available = 1
        ";
        $this->exec($sql);

        // Repair this invariant:
        // If all skus of product are unavailable product.count must be 0
        $sql = "
            UPDATE shop_product p JOIN (
                SELECT p.id, p.count, SUM(sk.available) all_sku_available
                FROM shop_product p
                JOIN shop_product_skus sk ON p.id = sk.product_id
                WHERE p.count IS NULL || (p.count IS NOT NULL AND p.count != 0)
                GROUP BY p.id
                HAVING all_sku_available = 0
            ) r ON p.id = r.id
            SET p.count = 0
        ";
        $this->exec($sql);
    }

    public function countProductStocks()
    {
        return $this->query("SELECT COUNT(id) cnt FROM (
                SELECT p.id
                FROM shop_product_skus sk
                JOIN {$this->table} p ON sk.product_id = p.id
                GROUP BY sk.product_id
        ) r")->fetchField('cnt');
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
                $type_id => '+1'
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
        $sql = "UPDATE `{$this->table}` SET type_id = ".(int) $to_type_id." WHERE type_id = ".(int) $from_type_id;
        if (!$this->exec($sql)) {
            return false;
        }

        $type_model = new shopTypeModel();
        $type_model->recount(array($from_type_id, $to_type_id));

        return true;
    }

    public function getCurrency($product_id)
    {
        return $this->select('currency')->where('id='.(int) $product_id)->fetchField('currency');
    }

    public function getTop($limit, $order = 'sales', $start_date = null, $end_date = null)
    {
        $paid_date_sql = shopOrderModel::getDateSql('o.paid_date', $start_date, $end_date);

        if ($order !== 'sales') {
            $order = 'profit';
        }
        $limit = (int) $limit;
        $limit = ifempty($limit, 10);

        $sql = "SELECT
                    p.*,
                    SUM(ps.price*pcur.rate*oi.quantity) AS sales,
                    SUM(ps.purchase_price*pcur.rate*oi.quantity) AS purchase,
                    SUM(ps.price*pcur.rate*oi.quantity - ps.purchase_price*pcur.rate*oi.quantity) AS profit
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                WHERE $paid_date_sql
                    AND oi.type = 'product'
                GROUP BY p.id
                ORDER BY $order DESC
                LIMIT $limit";

        return $this->query($sql);
    }

    public static function badges()
    {
        return array(
            'new'        => array('name' => _w('New!'), 'code' => '<div class="badge new"><span>'._w('New!').'</span></div>'),
            'bestseller' => array('name' => _w('Bestseller!'), 'code' => '<div class="badge bestseller"><span>'._w('Bestseller!').'</span></div>'),
            'lowprice'   => array('name' => _w('Low price!'), 'code' => '<div class="badge low-price"><span>'._w('Low price!').'</span></div>'),
        );
    }

    /**
     * Check current user rights to product with its type id
     *
     * @param array|int $product
     * @throws waException
     * @return boolean
     */
    public function checkRights($product)
    {
        if (is_numeric($product)) {
            $type_id = $this->select('type_id')->where('id='.(int) $product)->fetchField('type_id');
            if (!$type_id && false) {
                throw new waException(_w("Unknown type"));
            }
        } elseif (is_array($product)) {
            if (!isset($product['type_id'])) {
                //throw new waException(_w("Unknown type"));
                $type_id = null;
            } else {
                $type_id = $product['type_id'];
            }
        } else {
            $type_id = null;
            //throw new waException(_w("Unknown type"));
        }
        return (boolean) wa()->getUser()->getRights('shop', 'type.'.$type_id);
    }
}
