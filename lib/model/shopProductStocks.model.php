<?php
class shopProductStocksModel extends waModel
{
    protected $table = 'shop_product_stocks';
    protected $id = array('sku_id','stock_id');

    /**
     * TODO: realize
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }

    /**
     * Delete stock with or not recounting total counter of skus
     *
     * @param int $stock_id
     * @param boolean $recount
     */
    public function deleteStock($stock_id, $recount = true)
    {
        $stock_id = (int)$stock_id;
        if ($stock_id) {
            if ($recount) {

                $sql = "UPDATE `shop_product_skus` s
                        JOIN `{$this->table}` ps ON s.id = ps.sku_id
                        SET s.count = s.count - ps.count
                        WHERE ps.stock_id = $stock_id";
                if (!$this->exec($sql)) {
                    return false;
                }

                $sql = "UPDATE `shop_product` p
                        JOIN (
                            SELECT product_id, SUM(count) count
                            FROM {$this->table}
                            WHERE stock_id = {$stock_id}
                            GROUP BY product_id
                        ) AS ps ON p.id = ps.product_id
                        SET p.count = p.count - ps.count";
                if (!$this->exec($sql)) {
                    return false;
                }

                $product_model = new shopProductModel();
                $product_model->correctCount();

            }
            return $this->deleteByField('stock_id', $stock_id);
        }
        return false;
    }

    /**
     * Move skus from source stock to destination stock and REMOVE source stock
     *
     * @param int $src_id
     * @param int $dst_id
     */
    public function move($src_id, $dst_id)
    {
        // increase counts of destination stock for intersecting skus
        $sql = "UPDATE `{$this->table}` dst
                JOIN `{$this->table}` src ON src.sku_id = dst.sku_id
                SET dst.count = dst.count + src.count
                WHERE dst.stock_id = $dst_id AND src.stock_id = $src_id AND src.count IS NOT NULL AND dst.count IS NOT NULL";
        if (!$this->exec($sql)) {
            return false;
        }

        // and for that items which aren't in destination stock change stock_id to stock_id of source stock
        // ( in other words turn source items to desctination items )
        $sql = "UPDATE `{$this->table}` src
                LEFT JOIN `{$this->table}` dst ON dst.sku_id = src.sku_id AND dst.stock_id = $dst_id
                SET src.stock_id = {$dst_id}
                WHERE src.stock_id = $src_id AND dst.sku_id IS NULL";
        if (!$this->exec($sql)) {
            return false;
        }
        return $this->deleteStock($src_id, false);
    }

    /**
     * Transfer sku from one stock (src) to another (dst)
     *
     * @param int $src_id
     * @param int $dst_id
     * @param int $sku_id
     * @param int $count
     * @return boolean
     */
    public function transfer($src_id, $dst_id, $sku_id, $count = null) {
        $src_id = (int)$src_id;
        $dst_id = (int)$dst_id;
        $sku_id = (int)$sku_id;

        $stock_model = new shopStockModel();
        $data = $stock_model->getByField('id', array($src_id, $dst_id), true);
        if (count($data) < 2) {
            return false;
        }

        // count data
        $data = $this->query("
            SELECT stock_id, count
            FROM {$this->table}
            WHERE sku_id = $sku_id AND stock_id IN($src_id, $dst_id)"
        )->fetchAll('stock_id', true);

        $src_count = isset($data[$src_id]) ? $data[$src_id] : null;
        $dst_count = isset($data[$dst_id]) ? $data[$dst_id] : null;
        if ($dst_count === null) {
            return true;
        }

        if ($count === null) {
            // get all from src if src has numeric count
            if ($src_count !== null) {
                $count = $src_count;
            }
        } else {
            if ($src_count !== null) {
                if ($src_count <= 0) {
                    $count = 0;
                } else {
                    $count = min((int)$count, $src_count);
                }
            } else {
                $count = (int)$count;
            }
        }

        if (!$count) {
            return true;
        }

        if (!$this->updateByField(
            array('sku_id' => $sku_id, 'stock_id' => $dst_id),
            array('count' => $dst_count + $count)
        ))
        {
            return false;
        }

        if (!$this->updateByField(
            array('sku_id' => $sku_id, 'stock_id' => $src_id),
            array('count' => $src_count - $count)
        ))
        {
            return false;
        }

        /*
        $src = $data[$src_id];
        $dst = isset($data[$dst_id]) ? $data[$dst_id] : array();
        $count = $count === null ? $src['count'] : min((int)$count, $src['count']);
        if (!$count) {
            return true;
        }

        if (empty($dst)) {
            if (!$this->insert(array(
                'sku_id' => $sku_id,
                'stock_id' => $dst_id,
                'product_id' => $src['product_id'],
                'count' => $count
            ))) {
                return false;
            }
        } else {
            if (!$this->updateByField(array(
                    'sku_id' => $sku_id,
                    'stock_id' => $dst_id
                ), array('count' => $dst['count'] + $count)))
            {
                return false;
            }
        }
        if (!$this->updateByField(array(
                'sku_id' => $sku_id,
                'stock_id' => $src_id
            ), array('count' => $src['count'] - $count)))
        {
            return false;
        }
        */

        return true;
    }

    /*
    public function getStocksOfProduct($product_id, $stock_ids = null, $sku_order = 'count DESC', $stock_order = false)
    {
        if (!$product_id) {
            return false;
        }
        $where = array('product_id' => $product_id);
        if ($stock_ids) {
            $where['stock_id'] = $stock_ids;
        }
        $where = $this->getWhereByField($where, true);
        if (!$where) {
            return false;
        }

        $product_skus_model = new shopProductSkusModel();
        $skus_table = $product_skus_model->getTableName();

        if ($sku_order != 'count DESC' &&
            $sku_order != 'count ASC' &&
            $sku_order != 'sku.count DESC' &&
            $sku_order != 'sku.count ASC')
        {
            $sku_order = 'count DESC';
        }

        if (substr($sku_order, 0, 3) == 'sku') {
            $sku_order = $skus_table.substr($sku_order, 3);
        } else {
            $sku_order = $this->table.$sku_order;
        }

        $sql = "SELECT {$this->table}.*, {$skus_table}.name FROM {$this->table}
                JOIN {$skus_table} ON {$skus_table}.id = {$this->table}.sku_id";

        if ($stock_order) {
            $stock_model = new shopStockModel();
            $stock_table = $stock_model->getTableName();
            $sql .= " JOIN {$stock_table} ON {$stock_table}.id = {$this->table}.stock_id
                        WHERE $where ORDER BY {$stock_table}.sort, $sku_order";
        } else {
            $sql .= " WHERE $where ORDER BY {$this->table}.stock_id, $sku_order";
        }
        $data = array();
        $stock_id = 0;
        foreach ($this->query($sql) as $item) {
            if ($stock_id != $item['stock_id']) {
                $data[$item['stock_id']] = array();
                $stock_id = $item['stock_id'];
            }
            $data[$item['stock_id']][] = array(
                'id' => $item['sku_id'],
                'count' => $item['count'],
                'name' => $item['name']
            );
        }
        return $data;
    }
*/
    public function filterSkusByNoStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        foreach ($sku_ids as &$sku_id) {
            $sku_id = (int) $sku_id;
        }
        $sql = "SELECT sk.id, st.sku_id
                FROM `shop_product_skus` sk
                LEFT JOIN `shop_product_stocks` st ON sk.id = st.sku_id
                WHERE st.sku_id IS NULL AND sk.id IN(".implode(',', $sku_ids).")";
        return array_keys($this->query($sql)->fetchAll('id'));
    }

    /**
     * @param int $stock_id
     */
    public function insertFromSkus($stock_id)
    {
        $test = $this->select('*')->limit(1)->fetchAll();
        // expected shop_product_stocks hasn't any records yet!!!
        if (empty($test)) {
            $stock_id = (int) $stock_id;
            $this->exec("
                INSERT INTO {$this->table} (sku_id, stock_id, product_id, count)
                SELECT id AS sku_id, $stock_id AS stock_id, product_id, count
                FROM `shop_product_skus` ps
                WHERE ps.count IS NOT NULL
            ");
        }
    }

    /**
     * Get stocks info for every sku passed through first argument
     *
     * @param array $sku_ids ID of skus
     * @return array
     *
     * Example array(
     *     '<sku_id>' => array(
     *         '<stock_id>' => array(
     *             'name' => '<Name of stock>',
     *             'count' => null|int
     *         )
     *         ...
     *     )
     *     ...
     * )
     *
     */
    public function getBySkuId($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $sku_ids = (array) $sku_ids;

        $stock_model = new shopStockModel();
        $stocks = $stock_model->select('id, name, NULL as count')->order('sort')->fetchAll('id', true);

        $data = array();
        $sku_id = null;
        foreach (
            $this->
                select('*')->
                where("sku_id IN (".implode(',', $sku_ids).")")->
                order("sku_id")->
                fetchAll()
            as $item
        )
        {
            if ($sku_id != $item['sku_id']) {
                $sku_id = $item['sku_id'];
            }
            $data[$sku_id][$item['stock_id']] = $item['count'];
        }

        foreach ($data as $sku_id => $sku_stocks) {
            $data[$sku_id] = $stocks;
            foreach ($sku_stocks as $stock_id => $count) {
                $data[$sku_id][$stock_id]['count'] = $count;
            }
        }
        return $data;
    }
}
