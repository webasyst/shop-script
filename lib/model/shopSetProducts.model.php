<?php

class shopSetProductsModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_set_products';

    public function add($product_ids, $set_ids)
    {
        if (!$product_ids || !$set_ids) {
            return false;
        }

        $ignore = array();
        foreach ($this->query("
                SELECT set_id, product_id FROM {$this->table}
                WHERE ".$this->getWhereByField('product_id', $product_ids))
            as $item)
        {
            $ignore[$item['set_id']][$item['product_id']] = true;
        }

        $add = array();
        $counts = array();
        foreach ((array)$set_ids as $set_id) {
            $add[$set_id] = array();
            $counts[$set_id] = 0;
            foreach ((array)$product_ids as $product_id) {
                $product_id = (int)$product_id;
                $set_id = $this->escape($set_id);
                if (!isset($ignore[$set_id][$product_id])) {
                    $add[$set_id][] = array(
                        'set_id' => $set_id,
                        'product_id' => $product_id
                    );
                    $counts[$set_id] += 1;
                }
            }
            if (empty($add[$set_id])) {
                unset($add[$set_id]);
                unset($counts[$set_id]);
            }
        }

        if (!empty($add)) {
            $data = array();
            $last_sort = array();
            foreach($this->query("
                    SELECT set_id, MAX(sort) AS sort FROM {$this->table}
                    WHERE ".$this->getWhereByField('set_id', array_keys($add))."
                    GROUP BY set_id")
                as $item)
            {
                $last_sort[$set_id] = $item['sort'];
            }
            foreach ($add as $set_id => &$products) {
                $sort = isset($last_sort[$set_id]) ? $last_sort[$set_id] + 1 : 0;
                foreach ($products as &$product) {
                    $product['sort'] = $sort;
                    $data[] = $product;
                    $sort += 1;
                }
                unset($product);
            }
            unset($products);

            $this->multipleInsert($data);
            // update counts

            foreach ($counts as $set_id => $count) {
                if ($count) {
                    $data = array(
                        'count' => $count,
                        'id' => $set_id,
                    );
                    $this->query("UPDATE `shop_set` SET `count` = `count` + i:count WHERE `id` = s:id", $data);
                }
            }

            if ($cache = wa('shop')->getCache()) {
                $cache->deleteGroup('sets');
            }
        }
    }

    public function move($product_ids, $before_id, $set_id = null)
    {
        if (!$product_ids || !$set_id) {
            return false;
        }
        $product_ids = (array)$product_ids;
        $before_id   = (int)$before_id;
        $set_id = $this->escape($set_id);

        if ($before_id) {
            $sort = $this->query("
                SELECT sort FROM {$this->table}
                WHERE product_id = $before_id AND set_id = '$set_id'"
            )->fetchField('sort');
            $this->exec("
                UPDATE {$this->table} SET sort = sort + ".count($product_ids)."
                WHERE sort >= $sort AND set_id = '$set_id'"
            );
        } else {
            $sort = $this->query("
                SELECT MAX(sort) sort FROM {$this->table}
                WHERE set_id = '$set_id'")->fetchField('sort')
            + 1;
        }
        foreach ($product_ids as $product_id) {
            $this->updateByField(array(
                'product_id'  => $product_id,
                'set_id' => $set_id
            ), array('sort' => $sort++));
        }
        if ($cache = wa('shop')->getCache()) {
            $cache->deleteGroup('sets');
        }
        return true;
    }

    /**
     * Delete products from set
     *
     * @param string $set_id
     * @param array|bool $product_ids If true than delete all products from set
     * @return boolean
     */
    public function deleteProducts($set_id, $product_ids = array())
    {
        if (!$set_id) {
            return false;
        }
        if ($product_ids === true) {
            if (!$this->deleteByField('set_id', $set_id)) {
                return false;
            }
        } else {
            if (!$this->deleteByField(array('set_id' => $set_id, 'product_id' => $product_ids))) {
                return false;
            }
        }
        $set_model = new shopSetModel();
        if ($cache = wa('shop')->getCache()) {
            $cache->deleteGroup('sets');
        }
        if ($product_ids === true) {
            return $set_model->updateById($set_id, array('count' => 0));
        } else {
            return $set_model->recount($set_id);
        }
    }

    /**
     * Clear set (remove all products from set)
     * @param string $set_id
     * @return boolean
     */
    public function clearSet($set_id)
    {
        if ($cache = wa('shop')->getCache()) {
            $cache->deleteGroup('sets');
        }
        return $this->deleteProducts($set_id, true);
    }

    /**
     * Method triggered when deleting product through shopProductModel
     * @param array $product_ids
     * @return void
     */
    public function deleteByProducts(array $product_ids)
    {
        $set_ids = array_keys($this->getByField(array('product_id' => $product_ids), 'set_id'));
        if ($this->deleteByField('product_id', $product_ids)) {
            $set_model = new shopSetModel();
            $set_model->recount($set_ids);
        }
    }

    public function getByProduct($id)
    {
        return $this->query(
            "SELECT s.* FROM `{$this->table}` sp
            JOIN `shop_set` s ON s.id = sp.set_id
            WHERE product_id = i:product_id ORDER BY sort",
                array(
                    'product_id' => (int) $id
                ))->fetchAll('id');
    }

    public function getData(shopProduct $product)
    {
        return $this->getByProduct($product->id);
    }

    public function setData(shopProduct $product, $data)
    {
        $set_ids = array();
        if (is_array($data)) {
            foreach($data as $i => $s) {
                if (!is_array($s)) {
                    $set_ids[] = $s;
                } else if (isset($s['id'])) {
                    $set_ids[] = $s['id'];
                }
            }
        }

        // Delete product from all sets except $set_ids
        $sql = "DELETE FROM {$this->table} WHERE product_id=? AND set_id NOT IN (?)";
        $this->exec($sql, array($product->id, ifempty($set_ids, 0)));

        // Make sure product is belongs to $set_ids
        $set_ids && $this->add(array($product->id), $set_ids);

        return $this->getData($product);
    }
}
