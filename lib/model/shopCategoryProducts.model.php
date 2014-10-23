<?php

class shopCategoryProductsModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_category_products';
    protected $id = array('product_id', 'category_id');

    public function add($product_ids, $category_ids)
    {
        if (!$product_ids || !$category_ids) {
            return false;
        }

        $ignore = array();
        foreach (
        $this->query("
                SELECT category_id, product_id FROM {$this->table}
                WHERE " . $this->getWhereByField('product_id', $product_ids))
        as $item)
        {
            $ignore[$item['category_id']][$item['product_id']] = true;
        }

        $add = array();
        $counts = array();
        foreach ((array)$category_ids as $category_id) {
            $category_id = (int)$category_id;
            $add[$category_id] = array();
            $counts[$category_id] = 0;
            foreach ((array)$product_ids as $product_id) {
                $product_id = (int)$product_id;
                if (!isset($ignore[$category_id][$product_id])) {
                    $add[$category_id][] = array(
                        'category_id' => $category_id,
                        'product_id' => $product_id
                    );
                    $counts[$category_id] += 1;
                }
            }
            if (empty($add[$category_id])) {
                unset($add[$category_id], $counts[$category_id]);
            }
        }

        if (!empty($add)) {
            $data = array();
            $last_sort = array();
            foreach ($this->query("
                        SELECT category_id, MAX(sort) AS sort FROM {$this->table}
                        WHERE ".$this->getWhereByField('category_id', array_keys($add))."
                        GROUP BY category_id")
            as $item)
            {
                $last_sort[$category_id] = $item['sort'];
            }
            foreach ($add as $category_id => &$products) {
                $sort = isset($last_sort[$category_id]) ? $last_sort[$category_id] + 1 : 0;
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
            foreach ($counts as $category_id => $count) {
                $this->query("UPDATE `shop_category` SET count = count + $count WHERE id = $category_id");
            }
        }

        $product_model = new shopProductModel();
        $product_model->correctMainCategory($product_ids);

    }

    public function move($product_ids, $before_id, $category_id = null)
    {
        if (!$product_ids || !$category_id) {
            return false;
        }
        $product_ids = (array)$product_ids;
        $before_id   = (int)$before_id;
        $category_id = (int)$category_id;

        if ($before_id) {
            $sort = $this->query("
                SELECT sort FROM {$this->table}
                WHERE product_id = $before_id AND category_id = $category_id"
            )->fetchField('sort');
            if ($sort === false) {
                return false;
            }
            $this->exec("
                UPDATE {$this->table} SET sort = sort + ".count($product_ids)."
                WHERE sort >= $sort AND category_id = $category_id"
            );
        } else {
            $sort = $this->query("
                SELECT MAX(sort) sort FROM {$this->table}
                WHERE category_id = $category_id")->fetchField('sort')
            + 1;
        }
        foreach ($product_ids as $product_id) {
            $this->updateByField(array(
                'product_id'  => $product_id,
                'category_id' => $category_id
            ), array('sort' => $sort++));
        }
        return true;
    }

    /*
    public function deleteProducts($category_id, $count = null)
    {
        $sql = "SELECT product_id FROM {$this->table} WHERE category_id = $category_id ";
        if ($count) {
            $sql .= ' LIMIT '.(int)$count;
        }
        $product_ids = array_keys($this->query($sql)->fetchAll('product_id'));
        if ($product_ids) {
            $product_model = new shopProductModel();
            return $product_model->delete($product_ids);
        }
        return false;
    }
    */

    /**
     * Delete products from category
     *
     * @param int $category_id
     * @param array|bool $product_ids If true than delete all products from category
     * @return boolean
     */
    public function deleteProducts($category_id, $product_ids = array())
    {
        if (!$category_id) {
            return false;
        }

        $category_model = new shopCategoryModel();


        if ($product_ids === true) {

            $product_ids = array_keys($this->select('product_id')->
                where('category_id = :c_id', array('c_id' => $category_id))->
                fetchAll('product_id', true));

            if (!$this->deleteByField('category_id', $category_id)) {
                return false;
            }
            if (!$category_model->updateById($category_id, array('count' => 0))) {
                return false;
            }

        } else {
            if (!$this->deleteByField(array('category_id' => $category_id, 'product_id' => $product_ids))) {
                return false;
            }
            if (!$category_model->recount($category_id)) {
                return false;
            }
        }

        $product_model = new shopProductModel();
        $product_model->correctMainCategory($product_ids);

        return true;
    }

    /**
     * Clear category (remove all products from category)
     * @param string $category_id
     * @return boolean
     */
    public function clearCategory($category_id)
    {
        return $this->deleteProducts($category_id, true);
    }

    /**
     * Method triggered when deleting product through shopProductModel
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {
        $category_model = new shopCategoryModel();

        foreach ($this->query("SELECT category_id, count(product_id) cnt FROM {$this->table}
            WHERE product_id IN (".implode(',', $product_ids).")
            GROUP BY category_id") as
        $item)
        {
            $category_model->query(
                "UPDATE ".$category_model->getTableName()." SET count = count - {$item['cnt']}
                WHERE id = {$item['category_id']}"
            );
        }
        $this->deleteByField('product_id', $product_ids);
    }

    public function getData(shopProduct $product)
    {
        $sql = "SELECT c.* FROM ".$this->table." cp JOIN shop_category c ON cp.category_id = c.id
        WHERE cp.product_id = i:id ORDER BY c.left_key";
        $data = $this->query($sql, array('id' => $product['id']))->fetchAll('id');
        if ($data && !empty($data[$product['category_id']])) {
            if (wa()->getEnv() == 'frontend' && waRequest::param('url_type') == 1) {
                foreach ($data as &$row) {
                    $row['full_url'] = $row['url'];
                }
                unset($row);
            }
            $first = $data[$product['category_id']];
            unset($data[$product['category_id']]);
            return array($first['id'] => $first) + $data;
        }
        return $data;
    }

    public function setData(shopProduct $product, $data)
    {
        $data = array_unique(array_map('intval', $data));
        $key = array_search(0, $data, true);
        if ($key !== false) {
            unset($data[$key]);
        }

        $category_ids = array_keys($this->getByField('product_id', $product->id, 'category_id'));

        if ($obsolete = array_diff($category_ids, $data)) {
            $this->deleteByField(array('product_id' => $product->id, 'category_id' => $obsolete));

            // correct counter
            $category_model = new shopCategoryModel();
            $category_model->recount($obsolete);
        }
        if ($added = array_diff($data, $category_ids)) {
            $this->add($product->id, $added);
        }

        //$product_model = new shopProductModel();
        //$product_model->correctMainCategory($product->id);
        if ($data) {
            $product->category_id = reset($data);
        } else {
            $product->category_id = null;
        }
        $product_model = new shopProductModel();
        $product_model->updateById($product->id, array(
            'category_id' => $product->category_id
        ));

        return $data;
    }
    
    /**
     * Check for each product if is in any categories and return proper ids
     * @param array|int $product_ids
     * @param array|int $category_ids
     */
    public function filterByEnteringInCategories($product_ids, $category_ids)
    {
        $product_ids = (array) $product_ids;
        $category_ids = (array) $category_ids;
        if (empty($product_ids) || empty($category_ids)) {
            return array();
        }
        $sql = "SELECT product_id FROM `{$this->table}` 
            WHERE product_id IN(" . implode(',', $product_ids) . ") 
                AND category_id IN(" . implode(',', $category_ids) . ")";
        return array_keys($this->query($sql)->fetchAll('product_id'));
    }
}
