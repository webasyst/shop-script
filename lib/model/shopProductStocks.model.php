<?php
class shopProductStocksModel extends waModel
{
    protected $table = 'shop_product_stocks';
    protected $id = array('sku_id','stock_id');

    /**
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
        
        $stock_model = new shopStockModel();
        $stock = $stock_model->getById($stock_id);
        $stock_name = $stock ? $stock['name'] : '';
        
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
            
            $log_model = new shopProductStocksLogModel();
            $log_model->updateByField(array(
                'stock_id' => $stock_id
            ), array(
                'stock_id' => null,
                'stock_name' => $stock_name
            ));
            
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
        $src_id = (int) $src_id;
        $dst_id = (int) $dst_id;
        
        $this->logMove($src_id, $dst_id);
        
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
     * Insert to log info about moving
     * @param int $stock_id
     * @param int|null $chunk_size Insert items by chunks of this size. If null make insert at a time
     */
    private function logMove($src_id, $dst_id, $chunk_size = 100)
    {
        if ($chunk_size !== null && $chunk_size <= 0) {
            return;
        }
        
        $stock_model = new shopStockModel();
        $src_stock = $stock_model->getById($src_id);
        $dst_stock = $stock_model->getById($dst_id);
        $src_name = $src_stock ? '@'.$src_stock['name'] : '';
        $dst_name = $dst_stock ? '@'.$dst_stock['name'] : '';
        
        $log_model = new shopProductStocksLogModel();
        
        $sql = "SELECT COUNT(src.stock_id) FROM `shop_product_stocks` src
            LEFT JOIN `shop_product_stocks` dst ON dst.sku_id = src.sku_id AND 
                dst.stock_id = {$dst_id}
            WHERE src.stock_id = {$src_id}";
        $total_count = (int) $this->query($sql)->fetchField();
        if ($chunk_size === null) {
            $chunk_size = $total_count;
        }
        for ($offset = 0; $offset < $total_count; $offset += $chunk_size) {
            $data_1 = array();
            $data_2 = array();
                
            $sql = "SELECT 
                    src.sku_id, src.product_id, src.count AS src_count,
                    dst.count AS dst_count
                FROM `shop_product_stocks` src
                LEFT JOIN `shop_product_stocks` dst ON dst.sku_id = src.sku_id AND 
                    dst.stock_id = {$dst_id}
                WHERE src.stock_id = {$src_id}
                ORDER BY src.sku_id
                LIMIT {$offset}, {$chunk_size}";

            foreach ($this->query($sql) as $row) {
                if ($row['src_count'] == 0) {
                    // nothing to move, ignore insert to log
                    continue;
                }
                $data_1[] = array(
                    'product_id' => $row['product_id'],
                    'sku_id' => $row['sku_id'],
                    'stock_id' => $src_id,
                    'before_count' => $row['src_count'],
                    'after_count' => 0,
                    'diff_count' => -$row['src_count'],
                    'type' => shopProductStocksLogModel::TYPE_STOCK,
                    'datetime' => date('Y-m-d H:i:s')
                );
                $data_2[] = array(
                    'product_id' => $row['product_id'],
                    'sku_id' => $row['sku_id'],
                    'stock_id' => $dst_id,
                    'before_count' => $row['dst_count'],
                    'after_count' => $row['src_count'] + (int) $row['dst_count'],
                    'diff_count' => $row['src_count'],
                    'type' => shopProductStocksLogModel::TYPE_STOCK,
                    'datetime' => date('Y-m-d H:i:s')
                );
            }
            $log_model->multipleInsert($data_1);
            $log_model->multipleInsert($data_2);
        }
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
            SELECT stock_id, count, product_id
            FROM {$this->table}
            WHERE sku_id = $sku_id AND stock_id IN($src_id, $dst_id)"
        )->fetchAll('stock_id', true);

        $src_count = isset($data[$src_id]) ? $data[$src_id]['count'] : null;
        $dst_count = isset($data[$dst_id]) ? $data[$dst_id]['count'] : null;
        if ($dst_count === null) {
            return true;
        }
        
        $product_id = $data[$dst_id]['product_id'];

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

        $log_model = new shopProductStocksLogModel();
        
        if (!$this->updateByField(
            array('sku_id' => $sku_id, 'stock_id' => $src_id),
            array('count' => $src_count - $count)
        ))
        {
            return false;
        }
        
        $log_model->insert(array(
            'product_id' => $product_id,
            'sku_id' => $sku_id,
            'stock_id' => $src_id,
            'before_count' => $src_count,
            'after_count' => $src_count - $count,
            'diff_count' => -$count
        ));
        
        if (!$this->updateByField(
            array('sku_id' => $sku_id, 'stock_id' => $dst_id),
            array('count' => $dst_count + $count)
        ))
        {
            return false;
        }
        
        $log_model->add(array(
            'product_id' => $product_id,
            'sku_id' => $sku_id,
            'stock_id' => $dst_id,
            'before_count' => $dst_count,
            'after_count' => $dst_count + $count,
            'diff_count' => $count
        ));
        
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
    
    /**
     * Insert for NEW stock_id items with count = 0 FOR all existing skus in this table
     * Take into account stocks log
     * @param int $stock_id
     * @param int|null $chunk_size Insert items by chunks of this size. If null make insert at a time
     */
    public function insertZeros($stock_id, $chunk_size = 100)
    {
        if ($chunk_size !== null && $chunk_size <= 0) {
            return;
        }
        
        $sql = "SELECT COUNT(DISTINCT product_id, sku_id) FROM `shop_product_stocks`";
        $total_count = (int) $this->query($sql)->fetchField();
        if ($chunk_size === null) {
            $chunk_size = $total_count;
        }
        for ($offset = 0; $offset < $total_count; $offset += $chunk_size) {
            
            $data = array();
            
            $sql = "SELECT DISTINCT product_id, sku_id FROM `shop_product_stocks` 
                ORDER BY product_id, sku_id
                LIMIT {$offset}, {$chunk_size}";
            foreach ($this->query($sql) as $row) {
                $data[] = array(
                    'sku_id' => $row['sku_id'],
                    'stock_id' => $stock_id,
                    'product_id' => $row['product_id'],
                    'count' => 0
                );
            }
            // for product_stocks
            $this->multipleInsert($data);
        }
    }
    
    /**
     * Set count for stock and sku. 
     * Make insert, update or delete depending on input parameters and current state (sku-stock record in table)
     * Take into account stocking log (@see shopProductStocksLog)
     * 
     * @param array    $data Specify sku ID, stock ID, count
     * @param int      $data['sku_id'] sku ID, obligatory
     * @param int      $data['product_id'] product ID, optional
     * @param int      $data['stock_id'] stock ID, obligatory
     * @param int|null $data['count'] count, obligatory.
     * 
     * <code>
     * array(
     *     'sku_id' => 123,      // sku ID, obligatory
     *     'product_id' => 111,  // product ID, optional
     *     'stock_id' => 23,     // stock ID, obligatory
     *     'count' => 12,        // Maybe null or integer greater or equals 0
     * )
     * </code>
     * 
     * @return boolean
     */
    public function set($data)
    {
        if (empty($data['sku_id']) || empty($data['stock_id'])) {
            return false;
        }
        
         // isset doesn't work correctly with null
        if (!array_key_exists('count', $data)) {
            return false;
        }
        
        $count = $data['count'];
        if (empty($data['product_id'])) {
            $product_skus_model = new shopProductSkusModel();
            $data['product_id'] = $product_skus_model->select('product_id')->
                    where('sku_id = :sku_id', array(
                        'sku_id' => $data['sku_id']
                    ))->fetchField();
        }
        if (empty($data['product_id'])) {
            return false;
        }
        
        $key = array(
            'sku_id' => $data['sku_id'],
            'stock_id' => $data['stock_id']
        );
        
        $item = $this->getByField($key);
        if (($item && $count !== null && $count == $item['count']) || (!$item && $count === null)) {
            // nothing to update
            return true;
        }
        
        $log_data = array(
            'product_id' => $data['product_id'],
            'sku_id' => $data['sku_id'],
            'stock_id' => $data['stock_id'],
            'before_count' => null,
            'after_count' => $count
        );

        if ($item) {
            $log_data['before_count'] = $item['count'];
        }
        
        if ($count === null) {
            $op = 'delete';
        } else {
            if ($item) {
                $op = 'update';
            } else {
                $op = 'insert';
            }
        }
        
        if ($op == 'delete') {
            $this->deleteByField($key);
        } else if ($op == 'update') {
            $this->updateByField($key, $data);
        } else {
            $this->insert($data);
        }

        $log_model = new shopProductStocksLogModel();
        $log_model->add($log_data);
        
        return true;
        
    }
    
    /**
     * @param int $sku_id
     * @return boolean
     */
    public function hasAnyStocks($sku_id)
    {
        return !!$this->select('sku_id')->where('sku_id=:sku_id', array(
            'sku_id' => $sku_id,
        ))->limit(1)->fetchField();
    }
}
