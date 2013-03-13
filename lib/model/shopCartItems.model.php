<?php

class shopCartItemsModel extends waModel
{
    protected $table = 'shop_cart_items';

    public function total($code)
    {
        $sql = "SELECT SUM(s.primary_price * c.quantity) FROM ".$this->table." c
                JOIN shop_product_skus s ON c.sku_id = s.id
                WHERE c.code = s:code AND type = 'product'";
        $products_total = $this->query($sql, array('code' => $code))->fetchField();

        $services_total = 0;
        $sql = "SELECT c.*, s.price FROM ".$this->table." c JOIN
        shop_service s ON c.service_id = s.id WHERE c.code = s:code AND type = 'service'";
        $services = $this->query($sql, array('code' => $code))->fetchAll();
        if (!$services) {
            return shop_currency($products_total, wa('shop')->getConfig()->getCurrency(true), null, false);
        }
        $variant_ids = array();
        $product_ids = array();
        foreach ($services as $s) {
            if ($s['service_variant_id']) {
                $variant_ids[] = $s['service_variant_id'];
            }
            $product_ids[] = $s['product_id'];
        }
        $variant_ids = array_unique($variant_ids);
        $product_ids = array_unique($product_ids);
        // get variant settings
        $variants_model = new shopServiceVariantsModel();
        $variants = $variants_model->getById($variant_ids);
        // get products/skus settings
        $product_services_model = new shopProductServicesModel();
        $products_services = $product_services_model->getByProducts($product_ids, true);

        foreach ($services as $s) {
            $p_id = $s['product_id'];
            $sku_id = $s['product_id'];
            $s_id = $s['service_id'];
            $v_id = $s['service_variant_id'];
            $p_services = isset($products_services[$p_id]) ? $products_services[$p_id] : array();

            if (!$v_id) {
                if (!empty($p_services['skus'][$sku_id][$s_id]['primary_price'])) {
                    $s['price'] = $p_services['skus'][$sku_id][$s_id]['primary_price'];
                } elseif (!empty($p_services[$s_id]['primary_price'])) {
                    $s['price'] = $p_services[$s_id]['primary_price'];
                }
            } else {
                // base price of variant
                if (!empty($variants[$v_id]['primary_price'])) {
                    $s['price'] = $variants[$v_id]['primary_price'];
                }
                // price variant for sku
                if (!empty($p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'])) {
                    $s['price'] = $p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['primary_price'];
                } elseif (!empty($p_services[$s_id]['variants'][$v_id]['primary_price'])) {
                    $s['price'] = $p_services[$s_id]['variants'][$v_id]['primary_price'];
                }
            }

            $services_total += $s['price'];
        }

        $total = $products_total + $services_total;
        $primary = wa('shop')->getConfig()->getCurrency();
        $currency = wa('shop')->getConfig()->getCurrency(false);
        if ($currency != $primary) {
            $currencies = wa('shop')->getConfig()->getCurrencies(array($currency));
            $total = $total / $currencies[$currency]['rate'];
        }
        return $total;
    }

    public function count($code, $type = null)
    {
        $sql = "SELECT SUM(quantity) FROM ".$this->table." WHERE code = s:code";
        if ($type) {
            $sql .= ' AND type = s:type';
        }
        return $this->query($sql, array(
            'code' => $code,
            'type' => $type
        ))->fetchField();
    }

    public function getSingleItem($code, $product_id, $sku_id)
    {
        $sql = "SELECT c1.* FROM ".$this->table." c1
                LEFT JOIN ".$this->table." c2 ON c1.id = c2.parent_id
                WHERE c1.code = s:0 AND c1.product_id = i:1 AND c1.sku_id = i:2 AND c2.id IS NULL LIMIT 1";
        return $this->query($sql, $code, $product_id, $sku_id)->fetch();
    }

    public function getByCode($code, $full_info = false, $hierarchy = true)
    {
        $sql = "SELECT * FROM ".$this->table." WHERE code = s:0 ORDER BY parent_id";
        $items = $this->query($sql, $code)->fetchAll('id');

        if ($full_info) {
            $product_ids = $sku_ids = $service_ids = $variant_ids = array();
            foreach ($items as $item) {
                $product_ids[] = $item['product_id'];
                $sku_ids[] = $item['sku_id'];
                if ($item['type'] == 'service') {
                    $service_ids[] = $item['service_id'];
                    if ($item['service_variant_id']) {
                        $variant_ids[] = $item['service_variant_id'];
                    }
                }
            }

            $product_model = new shopProductModel();
            $products = $product_model->getByField('id', $product_ids, 'id');

            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getByField('id', $sku_ids, 'id');

            $service_model = new shopServiceModel();
            $services = $service_model->getByField('id', $service_ids, 'id');

            $service_variants_model = new shopServiceVariantsModel();
            $variants = $service_variants_model->getByField('id', $variant_ids, 'id');

            foreach ($items as &$item) {
                if ($item['type'] == 'product') {
                    $item['product'] = $products[$item['product_id']];
                    $sku = $skus[$item['sku_id']];
                    $item['sku_name'] = $sku['name'];
                    $item['currency'] = $item['product']['currency'];
                    $item['price'] = $sku['price'];
                    $item['name'] = $item['product']['name'];
                    if ($item['sku_name']) {
                        $item['name'] .= ' ('.$item['sku_name'].')';
                    }
                } else {
                    $item['name'] = $item['service_name'] = $services[$item['service_id']]['name'];
                    $item['price'] = $services[$item['service_id']]['price'];
                    $item['currency'] = $services[$item['service_id']]['currency'];
                    $item['service'] = $services[$item['service_id']];
                    if ($item['service_variant_id']) {
                        $item['variant_name'] = $variants[$item['service_variant_id']]['name'];
                        if ($item['variant_name']) {
                            $item['name'] .= ' ('.$item['variant_name'].')';
                        }
                        if ($variants[$item['service_variant_id']]['price'] !== null) {
                            $item['price'] = $variants[$item['service_variant_id']]['price'];
                        }
                    }
                }
            }
            unset($item);
        }
        // sort
        foreach ($items as $item_id => $item) {
            if ($item['parent_id']) {
                $items[$item['parent_id']]['services'][] = $item;
                unset($items[$item_id]);
            }
        }
        if (!$hierarchy) {
            $result = array();
            foreach ($items as $item_id => $item) {
                if (isset($item['services'])) {
                    $i = $item;
                    unset($i['services']);
                    $result[$item_id] = $i;
                    foreach ($item['services'] as $s) {
                        $result[$s['id']] = $s;
                    }
                } else {
                    $result[$item_id] = $item;
                }
            }
            $items = $result;
        }
        return $items;
    }

    public function getItem($code, $id)
    {
        $sql = "SELECT c.*, p.currency, s.price FROM ".$this->table." c
        JOIN shop_product p ON c.product_id = p.id JOIN shop_product_skus s ON c.sku_id = s.id
        WHERE c.code = s:code AND c.id = i:id";
        return $this->query($sql, array('code' => $code, 'id' => $id))->fetch();
    }
}
