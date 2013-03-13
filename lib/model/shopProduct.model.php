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
    protected $table = 'shop_product';

    public function delete(array $product_ids)
    {
        $delete_ids = array();

        // remove files
        foreach ($product_ids as $product_id) {
            try {
                waFiles::delete(shopProduct::getPath($product_id, null, false));
                waFiles::delete(shopProduct::getPath($product_id, null, true));
                $delete_ids[] = (int)$product_id;
            } catch (waException $e) { }
        }

        if (empty($delete_ids)) {
            return false;
        }

        $params = array('ids' => $delete_ids);
        /**
         * @event product_delete
         * @param array[string]mixed $params
         * @param array[string]array $params['ids']
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
            new shopSearchIndexModel()
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
     * @param int $offset
     * @param int $count
     * @return array multilevel array
     *
     * First level: products
     * Second level: skus and stocks (guarantee that count of subarray 'stocks' is count of stocks of shop
     * Third level: if 2d level is stocks than skus placed in this stock
     * @example array(
     *   array(
     *     'id' => 123
     *     'name' => 'Product123',
     *     'total_count' => 40,
     *     'skus' => array(
     *         array( 'id' => 1, 'name' => 'Sku1OfProduct123', 'count' => 10 ),
     *         array( 'id' => 2, 'name' => 'Sku2OfProduct123', 'count' => 15 ),
     *         array( 'id' => 3, 'name' => 'Sku3OfProduct123', 'count' => 25 )
     *      ),
     *      'stocks' => array(
     *          array()   // product width ID=123 is not in 1st stock
     *          array(
     *              array( 'id' => 1, 'name' => 'Sku1OfProduct123', 'count' => 3 ),
     *              array( 'id' => 3, 'name' => 'Sku3OfProduct123', 'count' => 16 )
     *          ),
     *          array(
     *              array( 'id' => 1, 'name' => 'Sku1OfProduct123', 'count' => 7 ),
     *              array( 'id' => 2, 'name' => 'Sku2OfProduct123', 'count' => 15 ),
     *              array( 'id' => 3, 'name' => 'Sku3OfProduct123', 'count' => 9 )
     *          )
     *      )
     *   )
     * )
     */
    public function getProductStocks($offset, $count, $order = 'desc')
    {
        $order = ($order == 'desc' || $order == 'DESC') ? 'DESC' : 'ASC';

        $stock_model = new shopStockModel();
        $stock_id_index = array_flip(array_keys($stock_model->getAll('id')));

        if ($stocks_count = count($stock_id_index)) {
            $dummy = array_fill(0, $stocks_count, array());
        } else {
            $dummy = array();
        }


        $product_images_model = new shopProductImagesModel();

        $product_ids = array();
        $image_ids = array();
        $sql = "SELECT p.id, p.name, p.count, p.image_id
                    FROM shop_product_skus sk
                    JOIN {$this->table} p ON sk.product_id = p.id
                    GROUP BY sk.product_id
                    HAVING SUM( sk.count ) IS NOT NULL
                    ORDER BY SUM( sk.count ) $order
                    LIMIT ".(int)$offset.", ".(int)$count;

        $data = array();
        foreach ($this->query($sql) as $item) {
            $data[$item['id']] = array(
                'id' => $item['id'],
                'name' => $item['name'],
                'url_crop_small' => null,
                'total_count' => $item['count'],
                'skus' => array(
                ),
                'stocks' => $dummy
            );
            if ($item['image_id'] != null) {
                $image_ids[] = $item['image_id'];
            }
        }
        if (empty($data)) {
            return array();
        }
        $images = $product_images_model->getByField('id', $image_ids, 'product_id');
        $size = wa()->getConfig()->getImageSize('crop_small');

        if (!$stocks_count) {
            return array_values($data);
        }

        $sql = "SELECT
                    sk.product_id, sk.id AS sku_id, sk.name AS sku_name, sk.count,
                    pst.stock_id, pst.count AS stock_count
                FROM shop_product_skus sk
                LEFT JOIN shop_product_stocks pst ON pst.sku_id = sk.id
                WHERE sk.product_id IN (".implode(',', array_keys($data)).") AND sk.count IS NOT NULL
                ORDER BY sk.product_id, sk.count $order, sk.id";

        $product_id = 0;
        $sku_id = 0;
        $p_product = null;
        foreach ($this->query($sql) as $item) {
            if ($product_id != $item['product_id']) {
                $product_id = $item['product_id'];
                $p_product = &$data[$product_id];
                if (isset($images[$product_id])) {
                    $p_product['url_crop_small'] = shopImage::getUrl($images[$product_id], $size);
                }
            }
            if ($sku_id != $item['sku_id']) {
                $p_product['skus'][] = array(
                    'id' => $item['sku_id'],
                    'name' => $item['sku_name'],
                    'count' => $item['count']
                );
                $sku_id = $item['sku_id'];
            }
            if ($item['stock_id'] !== null) {
                $index = $stock_id_index[$item['stock_id']];
                $p_product['stocks'][$index][] = array(
                    'id' => $item['sku_id'],
                    'name' => $item['sku_name'],
                    'count' => $item['stock_count']
                );
            }
        }
        return array_values($data);
    }

    public function correctMainCategory($product_ids = null, $category_ids = null)
    {
        $where = array();
        if ($product_ids) {
            $where[] = "p.id IN (".implode(',', (array)$product_ids).") ";
        }
        if ($category_ids) {
            $where[] = "p.category_id IN (".implode(',', (array)$category_ids).")";
        }
        $sql = "UPDATE
            {$this->table} p
            LEFT JOIN shop_category_products cp ON p.id = cp.product_id
            SET p.category_id = cp.category_id";
        if ($where) {
            $sql .= " WHERE ".implode(' AND ', $where);
        }
        return $this->exec($sql);
    }

    public function countProductStocks()
    {
        return $this->query("SELECT COUNT(id) cnt FROM (
                SELECT p.id
                FROM shop_product_skus sk
                JOIN {$this->table} p ON sk.product_id = p.id
                GROUP BY sk.product_id
                HAVING SUM( sk.count ) IS NOT NULL
                ORDER BY SUM( sk.count )
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

    public function getTop($limit, $order='sales', $start_date = null, $end_date = null)
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
            'new' => array('name' => _w('New!'), 'code' => '<div class="badge new"><span>'._w('New!').'</span></div>'),
            'bestseller' => array('name' => _w('Bestseller!'), 'code' => '<div class="badge bestseller"><span>'._w('Bestseller!').'</span></div>'),
            'lowprice' => array('name' => _w('Low price!'), 'code' => '<div class="badge low-price"><span>'._w('Low price!').'</span></div>'),
        );
    }
}
