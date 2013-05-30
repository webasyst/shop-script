<?php
class shopStockModel extends waModel
{
    const LOW_DEFAULT = 5;
    const CRITICAL_DEFAULT = 2;

    protected $table = 'shop_stock';

    public function move($id, $before_id = null)
    {
        if (!$before_id) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT MAX(sort) sort FROM {$this->table}")->fetchField('sort') + 1;
            $this->updateById($id, array('sort' => $sort));
        } else {
            $before_id = (int)$before_id;
            $items = $this->query("SELECT * FROM {$this->table} WHERE id IN ($id, $before_id)")->fetchAll('id');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_id]['sort'];
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= $sort");
            $this->updateById($id, array('sort' => $sort));
        }
        return true;
    }

    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY sort";
        return $this->query($sql)->fetchAll($key, $normalize);
    }

    public function add($data, $before_id = null)
    {
        if (empty($data)) {
            return false;
        }
        $before = null;
        if ($before_id) {
            $before = $this->getById($before_id);
        }
        if (!$before) {
            $sort = $this->query("SELECT MAX(sort) AS sort FROM {$this->table}")->fetchField('sort') + 1;
        } else {
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= {$before['sort']}");
            $sort = (int)$before['sort'];
        }
        if (empty($data['low_count'])) {
            $data['low_count'] = shopStockModel::LOW_DEFAULT;
        }
        if (empty($data['critical_count'])) {
            $data['critical_count'] = shopStockModel::CRITICAL_DEFAULT;
        }
        $data['sort'] = $sort;
        $id = $this->insert($data);

        // After insert new stock, for all skus with stocks this invariant is broken:
        // in multistocking if at least one stock is "infinity" for sku, sku count must be NULL
        $sql = "
            UPDATE `shop_product_skus` s JOIN (
                SELECT sk.id FROM `shop_product_skus` sk
                JOIN `shop_product_stocks` st ON sk.id = st.sku_id
            ) r ON s.id = r.id
            SET s.count = NULL
        ";
        $this->exec($sql);

        $product_model = new shopProductModel();
        $product_model->correctCount();

        return $id;
    }

    /*
    public function add($data, $before_id = null)
    {
        if (empty($data)) {
            return false;
        }
        $before = null;
        if ($before_id) {
            $before = $this->getById($before_id);
        }
        if (!$before) {
            $sort = $this->query("SELECT MAX(sort) AS sort FROM {$this->table}")->fetchField('sort') + 1;
        } else {
            $this->query("UPDATE {$this->table} SET sort = sort + ".count($data)." WHERE sort >= {$before['sort']}");
            $sort = (int)$before['sort'];
        }
        foreach ($data as &$item) {
            if (empty($item['low_count'])) {
                $item['low_count'] = shopStockModel::LOW_DEFAULT;
            }
            if (empty($item['critical_count'])) {
                $item['critical_count'] = shopStockModel::CRITICAL_DEFAULT;
            }
            $item['sort'] = $sort;
            $sort += 1;
        }
        unset($item);

        $this->multipleInsert($data);
    }
    */

    /**
     * Delete stock with or not writing-off products
     *
     * @param int $stock_id
     * @param int|null $dst_stock If null than writing-off products else move products to $dst_stock
     */
    public function delete($stock_id, $dst_stock = null)
    {
        $stock = $this->getById($stock_id);
        if (!$stock) {
            return false;
        }
        $stock_counts = $this->countAll();
        $product_stocks_model = new shopProductStocksModel();
        if (!$dst_stock) {
            if (!$product_stocks_model->deleteStock($stock_id, $stock_counts > 1)) {
                return false;
            }
        } else {
            if ($stock_counts <= 1) {
                return false;
            }
            if (!$product_stocks_model->move($stock_id, $dst_stock)) {
                return false;
            }
        }

        return $this->deleteById($stock_id);
    }
}
