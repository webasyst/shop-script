<?php
class shopTypeModel extends shopSortableModel
{
    protected $table = 'shop_type';

    public function getByName($name)
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `name` LIKE s:0";
        return $this->query($sql, $name)->fetch();
    }
    /**
     * Take into account rights
     */
    public function getTypes()
    {
        if (wa('shop')->getUser()->getRights('shop', 'type.all')) {
            return $this->getAll('id');
        } else {
            $types = array();
            $all_types = $this->getAll('id');
            foreach (self::extractAllowed(array_keys($all_types)) as $id) {
                if(isset($all_types[$id])) {
                    $types[$id] = $all_types[$id];
                }
            }
            return $types;
        }
    }

    public static function extractAllowed($type_ids)
    {
        if (!$type_ids) {
            return array();
        }
        $contact = wa('shop')->getUser();
        $allowed_ids = array();
        foreach ($type_ids as $id) {
            if ($contact->getRights('shop',sprintf( "type.%d",$id))) {
                $allowed_ids[] = $id;
            }
        }
        return $allowed_ids;
    }

    public function getNames()
    {
        return $this->select('id,name')->order('sort')->fetchAll('id', true);
    }

    /**
     * @param array $data Array format: id=>increment_value (e.g. '+1', '+2', '-1', '-5')
     */
    public function incCounters($data = array())
    {
        if (!$data) {
            return;
        }
        foreach ($data as $id => $inc) {
            if($id = intval($id)) {
                $inc = $this->escape(trim($inc));
                if ($inc{0} != '+' && $inc{0} != '-') {
                    $inc = '+'.$inc;
                }
                $this->exec("UPDATE `{$this->table}` SET `count`=`count`{$inc} WHERE `id` = {$id}");
            }
        }
    }

    public function deleteByField($field, $value = null)
    {
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
            $ids = array_keys($items);
        } else if($field == $this->id) {
            $ids = $value;
        } else {
            $items = $this->getByField($field, $value, $this->id);
            $ids = array_keys($items);
        }
        $res = false;
        if ($ids) {
            if ($res = parent::deleteByField($this->id, $ids)) {
                $product_model = new shopProductModel();
                $data = array('type_id'=>null);
                $product_model->updateByField('type_id', $ids, $data);

                $type_features_model = new shopTypeFeaturesModel();
                $type_features_model->deleteByField('type_id', $ids);
            }
        }
        return $res;
    }

    public function insert($data, $type = 0)
    {
        $id = parent::insert($data, $type);
        if($id) {
            $model = new shopTypeFeaturesModel();
            $model->addType($id);
        }
        return $id;
    }

    /**
     * @param int|array|null $type_id
     * @return boolean
     */
    public function recount($type_id = null)
    {
        $cond = "
            GROUP BY t.id
            HAVING t.count != cnt
        ";
        if ($type_id !== null) {
            $type_ids = array();
            foreach ((array)$type_id as $id) {
                $type_ids[] = (int)$id;
            }
            if (!$type_ids) {
                return;
            }
            $cond = "
                WHERE t.id IN (".implode(',', $type_ids).")
                GROUP BY t.id
            ";
        }
        $sql = "
            UPDATE `{$this->table}` t JOIN (
                SELECT t.id, t.count, count(p.type_id) cnt
                FROM `{$this->table}` t
                LEFT JOIN `shop_product` p ON p.type_id = t.id
                $cond
            ) r ON t.id = r.id
            SET t.count = r.cnt";

        return $this->exec($sql);
    }


    public function getSales($start_date = null, $end_date = null)
    {
        $paid_date_sql = shopOrderModel::getDateSql('o.paid_date', $start_date, $end_date);
        $sql = "SELECT
                    t.*,
                    SUM(ps.price*pcur.rate*oi.quantity) AS sales
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                    JOIN shop_type AS t
                        ON t.id=p.type_id
                WHERE $paid_date_sql
                    AND oi.type = 'product'
                GROUP BY t.id";
        return $this->query($sql)->fetchAll('id');
    }
}
