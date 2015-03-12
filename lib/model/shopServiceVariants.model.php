<?php
/**
 * Note: shop_service_variant.primary_price is stored in shop primary currency, 
 * and shop_service_variant.price is in shop_service.currency.
 */
class shopServiceVariantsModel extends waModel
{
    protected $table = 'shop_service_variants';

    public function delete($id)
    {
        if (!$this->deleteById($id)) {
            return false;
        }

        $product_services = new shopProductServicesModel();
        $product_services->deleteByField('service_variant_id', $id);

        return true;
    }

    public function get($service_id)
    {
        return $this->query("SELECT * FROM `{$this->table}`WHERE service_id = ".(int)$service_id." ORDER BY sort")->fetchAll();
    }

    public function getWithPrice($ids)
    {
        if (!$ids) {
            return array();
        }
        $sql = "SELECT v.*, IFNULL(v.price, s.price) price, s.currency FROM ".$this->table." v
                JOIN shop_service s ON v.service_id = s.id
                WHERE v.id IN (i:ids)";
        return $this->query($sql, array('ids' => $ids))->fetchAll('id');
    }
    
    public function getByField($field, $value = null, $all = false, $limit = false) {
        if (is_array($field)) {
            $limit = $all;
            $all = $value;
            $value = false;
        }
        $sql = "SELECT * FROM ".$this->table;
        $where = $this->getWhereByField($field, $value);
        if ($where != '') {
            $sql .= " WHERE ".$where;
        }
        $sql .= " ORDER BY service_id, sort";
        if ($limit) {
            $sql .= " LIMIT ".(int) $limit;
        } elseif (!$all) {
            $sql .= " LIMIT 1";
        }

        $result = $this->query($sql);

        if ($all) {
            return $result->fetchAll(is_string($all) ? $all : null);
        } else {
            return $result->fetchAssoc();
        }
    }

    public function move($service_id, $id, $before_id = null)
    {
        $service_id = (int) $service_id;
        $service_model = new shopServiceModel();
        $service = $service_model->getById($service_id);
        if (!$service) {
            return false;
        }
        
        $id = (int) $id;
        if (!$before_id) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT MAX(sort) sort FROM {$this->table} WHERE service_id = {$service_id}")->fetchField('sort') + 1;
            $this->updateById($id, array('sort' => $sort));
        } else {
            $before_id = $this->escape($before_id);
            $items = $this->query("SELECT * FROM {$this->table} WHERE id IN ('$id', '$before_id')")->fetchAll('id');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_id]['sort'];
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= {$sort} AND service_id = {$service_id}");
            $this->updateById($id, array('sort' => $sort));
        }
        return true;
    }

}