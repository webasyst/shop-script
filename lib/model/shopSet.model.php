<?php

class shopSetModel extends waModel
{
    protected $table = 'shop_set';

    const TYPE_STATIC  = 0;
    const TYPE_DYNAMIC = 1;

    public function add($data)
    {
        if (!empty($data)) {
            if ($this->query("UPDATE {$this->table} SET sort = sort + 1")) {
                if (!isset($data['create_datetime'])) {
                    $data['create_datetime'] = date('Y-m-d H:i:s');
                }
                $id = $this->escape($data['id']);
                if ($this->insert($data, 2)) {
                    return $id;
                }
            }
        }
        return false;
    }

    public function getAll($key = null, $normalize = false)
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY sort")->fetchAll($key, $normalize);
    }

    public function move($id, $before_id = null)
    {
        $id = $this->escape($id);
        if (!$before_id) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT MAX(sort) sort FROM {$this->table}")->fetchField('sort') + 1;
            $this->updateById($id, array('sort' => $sort));
        } else {
            $before_id = $this->escape($before_id);
            $items = $this->query("SELECT * FROM {$this->table} WHERE id IN ('$id', '$before_id')")->fetchAll('id');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_id]['sort'];
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= $sort");
            $this->updateById($id, array('sort' => $sort));
        }
        return true;
    }

    public function delete($id)
    {
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }

        /**
         * @event set_delete
         */
        wa()->event('set_delete', $item);

        $this->deleteById($id);

        // delete related info
        $set_products_model = new shopSetProductsModel();
        $set_products_model->deleteByField('set_id', $id);

        return true;
    }

    public function update($id, $data) {
        $change_id = false;
        if (isset($data['id'])) {
            if ($data['id'] == $id) {
                unset($data['id']);
            } else {
                $change_id = true;
            }
        }
        $this->updateById($id, $data);
        if ($change_id) {
            $set_products_model = new shopSetProductsModel();
            $set_products_model->updateByField(array('set_id' => $id), array('set_id' => $data['id']));
        }
    }

    public function recount($set_id = null)
    {
        $cond = "
            WHERE s.type = ".self::TYPE_STATIC."
            GROUP BY s.id
            HAVING s.count != cnt
        ";
        if ($set_id !== null) {
            $set_ids = array();
            foreach ((array)$set_id as $id) {
                $set_ids[] = $id;
            }
            if (!$set_ids) {
                return;
            }
            $cond = "
                WHERE s.id IN ('".implode("','", $this->escape($set_ids))."') AND s.type = ".self::TYPE_STATIC."
                GROUP BY s.id
            ";
        }
        $sql = "
        UPDATE `{$this->table}` s JOIN (
            SELECT s.id, s.count, count(sp.product_id) cnt
            FROM `{$this->table}` s
            LEFT JOIN `shop_set_products` sp ON sp.set_id = s.id
            $cond
        ) r ON s.id = r.id
        SET s.count = r.cnt";
        return $this->exec($sql);
    }

    public function suggestUniqueId($original_id)
    {
        $counter = 1;
        $id = $original_id;
        while ($this->idExists($id)) {
            $id = "{$original_id}_{$counter}";
            $counter++;
        }
        return $id;
    }

    public function idExists($id)
    {
        $where = "id = s:id";
        return !!$this->select('id')->where($where, array('id' => $id))->fetch();
    }
}