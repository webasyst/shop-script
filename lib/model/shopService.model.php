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
        if (!empty($options['sales_channel'])) {
            $storefront_join .= " JOIN shop_order_params AS opst2
                                    ON opst2.order_id=o.id
                                        AND opst2.name='sales_channel' ";
            $storefront_where .= " AND opst2.value='".$this->escape($options['sales_channel'])."' ";
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

    /**
     * Calculate formatted services info for each 'product' item with taking into account product/sku level price redeclaration and variants availability
     * Also will taking into account current 'service' items and applied to result 'services' info
     *
     * IMPORTANT: method expected flatten list of cart items
     *
     * @param array $items Flatten list of cart items gotten from shopCart->items(false)
     *
     * @return array - Heirarchy cart items with 'services' info
     *      1) Items of type 'service' will be removed from items
     *      2) Each 'product' item will be extended by 'services' array with only AVAILABLE services for this product/sku:
     *
     *          - array $item['services'][<int:service_id>] of shop_service DB record info with possible slightly changes:
     *          - array $item['services'][<int:service_id>]['variant_id'] - could be redeclared by default variant_id for product, if such redeclaration presented
     *                                                                          OR taken from input 'service' cart item, if such presented
     *          - array $item['services'][<int:service_id>]['price'] - could be redeclared by service price of product/sku, if such redeclaration presented AND if there is ONE service variant
     *
     *          - array $item['services'][<int:service_id>]['variants'] [optional] - could be presented or not. If presented here is format:
     *          - array $item['services'][<int:service_id>]['variants'][<int:variant_id>] of shop_service_variant DB record info with possible slightly changes:
     *          - array $item['services'][<int:service_id>]['variants'][<int:variant_id>]['price'] - could be redeclared by service price of product/sku, if such redeclaration presented
     *                          Also percentages will be calculated and 'price' will be in absolute currency units
     *
     *          - If $item['services'][<int:service_id>]['variants'] not presented there is ONE possible service variant.
     *                  In this case 'price' of $item['services'][<int:service_id>] is 'price' of this ONE service variant
     *
     *          Common logic of working with 'services' format:
     *              If there is 'variants' presented loop over it and get 'price' values from here. Current selected variant get from 'variant_id' of service
     *              If there is not 'variants' presented see 'price' of service itself
     *
     * @throws waDbException
     * @throws waException
     */
    public function applyServicesInfoToCartItems($items)
    {
        // Gather all the ids
        $product_ids = [];
        $sku_ids = [];
        $type_ids = [];

        foreach ($items as $item) {
            $product_ids[$item['product_id']] = $item['product_id'];
            $sku_ids[$item['sku_id']] = $item['sku_id'];
            if ($item['type'] == 'product') {
                $type_ids[$item['product']['type_id']] = $item['product']['type_id'];
            }
        }

        $type_ids = array_values($type_ids);
        $product_ids = array_values($product_ids);
        $sku_ids = array_values($sku_ids);

        $service_ids = [];

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[$row['service_id']] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for products and skus, part 1: gather service ids
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        foreach ($rows as $i => $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                unset($rows[$i]);
                continue;
            }
            $service_ids[$row['service_id']] = $row['service_id'];
        }

        $service_ids = array_values($service_ids);

        $services_rounding_enabled = shopRounding::isEnabled('services');

        // Get services
        $service_model = new shopServiceModel();
        $services = $service_model->getByField('id', $service_ids, 'id');

        if ($services_rounding_enabled) {
            shopRounding::roundServices($services);
        }

        // get services for products and skus, part 2
        $product_services = [];
        $sku_services = [];

        if ($services_rounding_enabled) {
            shopRounding::roundServiceVariants($rows, $services);
        }

        foreach ($rows as $row) {
            // just in case, drop off inconsistency rows
            if (!isset($services[$row['service_id']])) {
                continue;
            }
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        // Get service variants
        $variant_model = new shopServiceVariantsModel();
        $variants = $variant_model->getByField('service_id', $service_ids, true);

        if ($services_rounding_enabled) {
            shopRounding::roundServiceVariants($variants, $services);
        }

        foreach ($variants as $variant) {
            $services[$variant['service_id']]['variants'][$variant['id']] = $variant;
            unset($services[$variant['service_id']]['variants'][$variant['id']]['id']);
        }

        // IMPORTANT: about structure of $services
        // For each service here is ALL POSSIBLE VARIANTS now, but for each product NOT ALL VARIANTS could be available
        // Lower in the code this will be checked

        // When assigning services into cart items, we don't want service ids there
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);

        // Assign service and product data into cart items
        foreach ($items as $item_id => $item) {
            if ($item['type'] == 'product') {
                $p = $item['product'];

                $item_services = array();

                // services from type settings
                if (isset($type_services[$p['type_id']])) {
                    foreach ($type_services[$p['type_id']] as $service_id => &$s) {
                        $item_services[$service_id] = $services[$service_id];
                    }
                }

                // services from product settings
                if (isset($product_services[$item['product_id']])) {
                    foreach ($product_services[$item['product_id']] as $service_id => $product_service) {

                        // current service from plain list of services
                        $service = $services[$service_id];

                        // is service forbidden
                        $is_forbidden = isset($service['status']) && $service['status'] == shopProductServicesModel::STATUS_FORBIDDEN;

                        // if service is forbidden on product level unset from item services
                        if ($is_forbidden) {
                            unset($item_services[$service_id]);
                            continue;
                        }

                        // set for current item "raw" service info from plain list of services
                        // further this "raw" service info will be processing
                        if (!isset($item_services[$service_id])) {
                            $item_services[$service_id] = $service;
                        }

                        // process current item service variants
                        foreach ($item_services[$service_id]['variants'] as $variant_id => $variant) {

                            // service variant record defined on shop_product_services level
                            $product_service_variant = isset($product_service['variants'][$variant_id]) ? $product_service['variants'][$variant_id] : null;

                            // if there is not shop_product_services record, than check allowing by type
                            if (!$product_service_variant) {
                                $enabled_by_type = !empty($type_services[$p['type_id']][$service_id]);
                                if (!$enabled_by_type) {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                                continue;
                            }

                            // further working with shop_product_services record

                            // is variant forbidden by shop_product_services record?
                            if ($product_service_variant['status'] == shopProductServicesModel::STATUS_FORBIDDEN) {
                                unset($item_services[$service_id]['variants'][$variant_id]);
                                continue;
                            }

                            // price is redefined on shop_product_services level
                            if ($product_service_variant['price'] !== null) {
                                $item_services[$service_id]['variants'][$variant_id]['price'] = $product_service_variant['price'];
                            }

                            // default (selected) variant is different for this product
                            if ($product_service_variant['status'] == shopProductServicesModel::STATUS_DEFAULT) {
                                $item_services[$service_id]['variant_id'] = $variant_id;
                            }
                        }
                    }
                }

                // services from sku settings
                if (isset($sku_services[$item['sku_id']])) {
                    foreach ($sku_services[$item['sku_id']] as $service_id => $sku_service) {

                        // current service from plain list of services
                        $service = $services[$service_id];

                        // is service forbidden
                        $is_forbidden = isset($service['status']) && $service['status'] == shopProductServicesModel::STATUS_FORBIDDEN;

                        // if service is forbidden unset from item services
                        if ($is_forbidden) {
                            unset($item_services[$service_id]);
                            continue;
                        }

                        // to this type item service must be exists, if not then it is inconsistency in shop_product_services
                        //          (there is must be record for product with sku_id IS NULL)
                        if (!isset($item_services[$service_id])) {
                            continue;
                        }

                        // update variants by "sku level" (no need check enabling by type and etc because this already dealt with on "product level" above)
                        foreach ($sku_service['variants'] as $variant_id => $v) {
                            if ($v['status'] == shopProductServicesModel::STATUS_FORBIDDEN) {
                                unset($item_services[$service_id]['variants'][$variant_id]);
                                continue;
                            }

                            if ($v['price'] !== null) {
                                $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                            }
                        }
                    }
                }

                foreach ($item_services as $s_id => &$s) {
                    if (!$s['variants']) {
                        unset($item_services[$s_id]);
                        continue;
                    }

                    if ($s['currency'] == '%') {
                        shopProductServicesModel::workupItemServices($s, $item);
                    }

                    if (count($s['variants']) == 1) {
                        reset($s['variants']);
                        $v_id = key($s['variants']);
                        $v = $s['variants'][$v_id];
                        $s['variant_id'] = $v_id;
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                unset($s);

                uasort($item_services, array('shopServiceModel', 'sortServices'));

                $items[$item_id]['services'] = $item_services;
            } else {
                // rarely but could be not existed, because cart items in session old, but product services availability has been changed
                // so check isset first
                if (isset($items[$item['parent_id']]['services'][$item['service_id']])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                    if (isset($item['service_variant_id'])) {
                        $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                    }
                }

                unset($items[$item_id]);

            }
        }

        return $items;
    }
}
