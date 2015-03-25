<?php
/**
 * Note: shop_service.price is stored in shop primary currency, not shop_service.currency!
 */
class shopServiceModel extends waModel
{
    protected $table = 'shop_service';

    public function delete($id)
    {
        if (!$this->getById($id)) {
            return false;
        }
        foreach (array(
            new shopServiceVariantsModel(),
            new shopTypeServicesModel(),
            new shopProductServicesModel()
        ) as $model) {
            $model->deleteByField('service_id', $id);
        }
        return $this->deleteById($id);
    }

    public function save($data, $id = 0, $not_delete_products = false)
    {
        $primary_currency = wa('shop')->getConfig()->getCurrency();
        if (empty($data['currency'])) {
            $data['currency'] = $primary_currency;
        }

        $just_inserted = false;
        if (!$id) {
            $id = $this->insert(array(
                'name'     => $data['name'],
                'price'    => 0,
                'currency' => $data['currency'],
                'tax_id'   => isset($data['tax_id']) ? $data['tax_id'] : ($data['tax_id'] === null ? null : 0),
                'variant_id' => 0,
            ));
            if (!$id) {
                return false;
            }
            $just_inserted = true;
        } else {
            $id = (int)$id;
        }
        if ($id) {
            $this->updateVariants($id, $data['variants']);
            $this->updateTypes($id, !empty($data['types']) ? $data['types'] : array());
            if (!empty($data['products'])) {
                $this->updateProducts($id, $data['products']);
            }

            if (!$just_inserted) {
                $update = array();
                $fields = array('name', 'currency', 'price', 'tax_id');
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $update[$field] = $data[$field];
                    } else {
                        if ($field === 'tax_id' && $data[$field] === null) {
                            $update[$field] = $data[$field];
                        }
                    }
                }
                if ($update) {
                    $this->updateById($id, $update);
                }
            }
        }

        $currency = $data['currency'];
        // update primary price
        if ($currency != '%') {
            $currency_model = new shopCurrencyModel();
            $rate = $currency_model->getRate($currency);
        } else {
            $rate = 1;  // hack for percents
        }

        $sql = "UPDATE `shop_service_variants`
                SET primary_price = price*$rate
                WHERE service_id = $id";
        $this->exec($sql);

        $sql = "UPDATE `shop_product_services` ps
                SET ps.primary_price = ps.price*$rate
                WHERE ps.service_id = $id AND ps.price IS NOT NULL";
        $this->exec($sql);

        $sql = "UPDATE `shop_service` s
                JOIN `shop_service_variants` sv ON s.id = sv.service_id AND s.variant_id = sv.id
                SET s.price = sv.primary_price
                WHERE s.id = $id";
        $this->exec($sql);

        return $id;
    }

    private function updateVariants($service_id, $variants)
    {
        $add = array();
        $update = array();

        $variants_model = new shopServiceVariantsModel();
        $products_model = new shopProductServicesModel();

        $old_variants = $variants_model->getByField('service_id', $service_id, 'id');

        $sort = 0;
        foreach ($variants as $item) {
            $item['sort'] = $sort;
            $sort += 1;
            if (empty($item['id']) || empty($old_variants[$item['id']])) {
                $item['service_id'] = $service_id;
                $add[] = $item;
            } else {
                $variant_id = $item['id'];
                $update[$variant_id] = $item;
                unset($old_variants[$variant_id]);
            }
        }

        $default_id = null;
        foreach ($add as $item) {
            $added_id = $variants_model->insert($item);
            if (!empty($item['default'])) {
                $default_id = $added_id;
            }
        }

        foreach ($update as $id => $item) {
            if (!empty($item['default'])) {
                $default_id = $id;
            }
            $variants_model->updateById($id, $item);
        }
        if ($old_variants) {
            $ids = array_keys($old_variants);
            $variants_model->delete($ids);
            $products_model->deleteByVariants($ids);
        }

        if (!$default_id) {
            $default_id = $this->query("
                SELECT id FROM `shop_service_variants`
                WHERE service_id = ".(int) $service_id." LIMIT 1
            ")->fetchField('id');
            $default_id = $default_id ? $default_id : null;
        }

        $this->updateById($service_id, array(
            'variant_id' => $default_id
        ));

        return array_keys($variants_model->getByField('service_id', $service_id, 'id'));
    }

    private function updateTypes($service_id, $types)
    {
        $model = new shopTypeServicesModel();
        $where = $model->getWhereByField(array('service_id' => $service_id));
        if (!$where) {
            return false;
        }

        $old_data = array_keys($model->getByField('service_id', $service_id, 'type_id'));

        $add = array();
        foreach (array_diff($types, $old_data) as $type_id) {
            $add[] = array('type_id' => $type_id, 'service_id' => $service_id);
        }

        if ($add) {
            $model->multipleInsert($add);
        }

        $delete = array_diff($old_data, $types);
        if ($delete) {
            $model->deleteByField(array('type_id' => $delete, 'service_id' => $service_id));
        }
    }

    private function updateProducts($service_id, $products)
    {
        $variants_model = new shopServiceVariantsModel();
        $variants = array_keys($variants_model->getByField('service_id', $service_id, 'id'));
        $model = new shopProductServicesModel();

        $old_data = array();
        foreach ($this->query("
            SELECT * FROM `".$model->getTableName()."`
            WHERE service_id = $service_id AND sku_id IS NULL
            ORDER BY `service_id`, `service_variant_id`
        ") as $item) {
            $old_data[$item['product_id']][$item['service_variant_id']] = $item;
        }

        $add = array();
        foreach ($products as $product_id) {
            foreach ($variants as $variant_id) {
                if (!isset($old_data[$product_id][$variant_id])) {
                    $add[] = array(
                        'product_id'         => $product_id,
                        'service_id'         => $service_id,
                        'service_variant_id' => $variant_id
                    );
                }
            }
        }

        if (!empty($add)) {
            $model->multipleInsert($add);
        }

        /*
         $delete = array(
         'service_variant_id' => array()
         );

         foreach ($old_data as $product_id => $items) {
         foreach ($items as $variant_id => $item) {
         $delete['service_variant_id'][] = $variant_id;
         }
         }

         if ($delete['product_id'] && $delete['service_variant_id']) {
         $where = $model->getWhereByField(array('product_id' => $delete['product_id'], 'service_id' => $service_id));
         if ($where) {
         $model->query("DELETE FROM `".$model->getTableName()."` WHERE $where");
         }
         }
         */
    }

    public function getTop($limit, $start_date = null, $end_date = null, $options=array())
    {
        $paid_date_sql = array();
        if ($start_date) {
            $paid_date_sql[] = "o.paid_date >= DATE('".$this->escape($start_date)."')";
        }
        if ($end_date) {
            $paid_date_sql[] = "o.paid_date <= DATE('".$this->escape($end_date)."')";
        }
        if ($paid_date_sql) {
            $paid_date_sql = implode(' AND ', $paid_date_sql);
        } else {
            $paid_date_sql = "o.paid_date IS NOT NULL";
        }

        $limit = (int) $limit;
        $limit = ifempty($limit, 10);

        $storefront_join = '';
        $storefront_where = '';
        if (!empty($options['storefront'])) {
            $storefront_join = "JOIN shop_order_params AS op2
                                    ON op2.order_id=o.id
                                        AND op2.name='storefront'";
            $storefront_where = "AND op2.value='".$this->escape($options['storefront'])."'";
        }

        $sql = "SELECT
                    s.*,
                    SUM(oi.price*o.rate*oi.quantity) AS total
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_service AS s
                        ON oi.service_id=s.id
                    {$storefront_join}
                WHERE $paid_date_sql
                    AND oi.type = 'service'
                    {$storefront_where}
                GROUP BY s.id
                ORDER BY total DESC
                LIMIT $limit";

        return $this->query($sql);
    }

    public function getAll($key = null, $normalize = false)
    {
        return $this->query("SELECT * FROM `{$this->table}` ORDER BY sort")->fetchAll($key, $normalize);
    }

    public function move($id, $before_id = null)
    {
        $id = (int) $id;
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

    public static function sortServices($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }

}
