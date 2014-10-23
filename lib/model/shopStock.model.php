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
        
        $product_stocks_model = new shopProductStocksModel();
        $product_stocks_model->insertZeros($id);

        $product_model = new shopProductModel();
        $product_model->correctCount();

        return $id;
    }

    /**
     * Delete stock with or not writing-off products
     *
     * @param int $stock_id
     * @param int|null $dst_stock If null than writing-off products else move products to $dst_stock
     * @return bool
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
    
    public function stockExists($stock_id)
    {
        return !!$this->select('id')->where('id=i:id', array('id' => $stock_id))->fetchField();
    }
}
