<?php

class shopCartItemsModel extends waModel
{
    protected $table = 'shop_cart_items';

    public function total($code)
    {
        $products_total = $this->getProductsTotal($code);
        $services_total = $this->getServicesTotal($code);
        return (float) ($products_total + $services_total);
    }

    // Helper for total()
    // Products total in frontend currency
    protected function getProductsTotal($code)
    {
        $sql = "SELECT c.quantity, s.*
                FROM ".$this->table." c
                    JOIN shop_product_skus s ON c.sku_id = s.id
                WHERE c.code = s:code
                    AND type = 'product'";

        $skus = $this->query($sql, array('code' => $code))->fetchAll();

        shopRounding::roundSkus($skus);
        $products_total = 0.0;
        foreach($skus as $s) {
            $products_total += $s['frontend_price'] * $s['quantity'];
        }
        return $products_total;
    }

    // Helper for total()
    // Services total in frontend currency
    protected function getServicesTotal($code)
    {
        $sql = "SELECT c.*, s.currency
                FROM ".$this->table." c
                    JOIN shop_service s
                        ON c.service_id = s.id
                WHERE c.code = s:code
                    AND type = 'service'";
        $services = $this->query($sql, array('code' => $code))->fetchAll();
        if (!$services) {
            return 0.0;
        }

        $sku_ids = array();
        $variant_ids = array();
        $product_ids = array();
        $service_stubs = array();
        foreach ($services as $s) {
            if ($s['service_variant_id']) {
                $variant_ids[] = $s['service_variant_id'];
            }
            $product_ids[] = $s['product_id'];
            if ($s['currency'] == '%') {
                $sku_ids[] = $s['sku_id'];
            }

            $service_stubs[$s['service_id']] = array(
                'id' => $s['service_id'],
                'currency' => $s['currency'],
            );
        }
        $variant_ids = array_unique($variant_ids);
        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

        // get variant settings
        $rounding_enabled = shopRounding::isEnabled();
        $variants_model = new shopServiceVariantsModel();
        $variants = $variants_model->getWithPrice($variant_ids);
        $rounding_enabled && shopRounding::roundServiceVariants($variants, $service_stubs);

        // get products/skus settings
        $product_services_model = new shopProductServicesModel();
        $products_services = $product_services_model->getByProducts($product_ids, true);

        // fetch product prices to calculate %-based service prices
        $sku_prices = array();
        if ($sku_ids) {
            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getByField(array('id' => $sku_ids), 'id');
            if ($rounding_enabled) {
                shopRounding::roundSkus($skus);
            }
            $sku_prices = array();
            foreach($skus as $s) {
                $sku_prices[$s['id']] = $s['primary_price'];
            }
        }

        $primary = wa('shop')->getConfig()->getCurrency();

        // Calculate total amount for all services
        $services_total = 0;
        foreach ($services as $s) {
            $p_id = $s['product_id'];
            $sku_id = $s['sku_id'];
            $s_id = $s['service_id'];
            $v_id = $s['service_variant_id'];
            $p_services = isset($products_services[$p_id]) ? $products_services[$p_id] : array();

            $s['price'] = $variants[$v_id]['price'];

            // price variant for sku
            if (!empty($p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'])) {
                shopRounding::roundServiceVariants($p_services['skus'][$sku_id][$s_id]['variants'], array(array('id' => $s['service_id'], 'currency' => $s['currency'])));
                $s['price'] = $p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'];
            }

            if ($s['currency'] == '%') {
                $s['price'] = $s['price'] * $sku_prices[$sku_id] / 100; // !!! This is not primary currency. Good thing is that this is actually never used in real life...
            } else {
                $s['price'] = shop_currency($s['price'], $variants[$v_id]['currency'], $primary, false);
            }

            $services_total += $s['price'] * $s['quantity'];
        }

        return (float) shop_currency($services_total, null, null, false);
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

    public function countSku($code, $sku_id)
    {
        $sql = "SELECT SUM(quantity) FROM ".$this->table." WHERE code = s:code AND type = 'product' AND sku_id = i:sku_id";
        return $this->query($sql, array(
            'code' => $code,
            'sku_id' => $sku_id
        ))->fetchField();

    }

    public function getItemByProductAndServices($code, $product_id, $sku_id, $services)
    {
        if (!$services) {
            return $this->getSingleItem($code, $product_id, $sku_id);
        }
        $items = array();
        $rows = $this->getByField(array('code' => $code, 'product_id' => $product_id, 'sku_id' => $sku_id), true);
        foreach ($rows as $row) {
            if ($row['type'] == 'product') {
                if (isset($items[$row['id']])) {
                    $row['services'] = $items[$row['id']]['services'];
                }
                $items[$row['id']] = $row;
            } else {
                $items[$row['parent_id']]['services'][$row['service_id']] = $row['service_variant_id'];
            }
        }
        foreach ($items as $item) {
            if (!isset($item['services']) || count($item['services']) != count($services)) {
                continue;
            }
            $flag = true;
            foreach ($item['services'] as $s_id => $v_id) {
                if (!isset($services[$s_id]) || $services[$s_id] != $v_id) {
                    $flag = false;
                    break;
                }
            }
            if ($flag) {
                return $item;
            }
        }
        return null;
    }

    public function getSingleItem($code, $product_id, $sku_id)
    {
        $sql = "SELECT c1.* FROM ".$this->table." c1
                LEFT JOIN ".$this->table." c2 ON c1.id = c2.parent_id
                WHERE c1.code = s:0 AND c1.type = 'product' AND c1.product_id = i:1 AND c1.sku_id = i:2 AND c2.id IS NULL LIMIT 1";
        return $this->query($sql, $code, $product_id, $sku_id)->fetch();
    }

    public function getByCode($code, $full_info = false, $hierarchy = true)
    {
        if (!$code) {
            return array();
        }
        $sql = "SELECT * FROM ".$this->table." WHERE code = s:0 ORDER BY parent_id";
        $items = $this->query($sql, $code)->fetchAll('id');

        if ($full_info) {
            $rounding_enabled = shopRounding::isEnabled();

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
            if (waRequest::param('url_type') == 2) {
                $products = $product_model->getWithCategoryUrl($product_ids);
            } else {
                $products = $product_model->getById($product_ids);
            }
            $rounding_enabled && shopRounding::roundProducts($products);

            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getByField('id', $sku_ids, 'id');
            $rounding_enabled && shopRounding::roundSkus($skus, $products);

            $service_model = new shopServiceModel();
            $services = $service_model->getByField('id', $service_ids, 'id');
            $rounding_enabled && shopRounding::roundServices($services);

            $service_variants_model = new shopServiceVariantsModel();
            $variants = $service_variants_model->getByField('id', $variant_ids, 'id');
            $rounding_enabled && shopRounding::roundServiceVariants($variants, $services);

            $product_services_model = new shopProductServicesModel();
            $rows = $product_services_model->getByProducts($product_ids);
            $rounding_enabled && shopRounding::roundServiceVariants($rows, $services);

            $product_services = $sku_services = array();
            foreach ($rows as $row) {
                if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                    continue;
                }
                $service_ids[] = $row['service_id'];
                if (!$row['sku_id']) {
                    $product_services[$row['product_id']][$row['service_variant_id']] = $row;
                }
                if ($row['sku_id']) {
                    $sku_services[$row['sku_id']][$row['service_variant_id']] = $row;
                }
            }

            $image_model = null;
            foreach ($items as $item_key => &$item) {
                if ($item['type'] == 'product' && isset($products[$item['product_id']])) {
                    $item['product'] = $products[$item['product_id']];
                    if (!isset($skus[$item['sku_id']])) {
                        unset($items[$item_key]);
                        continue;
                    }
                    $sku = $skus[$item['sku_id']];

                    // Use SKU image instead of product image if specified
                    if ($sku['image_id'] && $sku['image_id'] != $item['product']['image_id']) {
                        $image_model || ($image_model = new shopProductImagesModel());
                        $img = $image_model->getById($sku['image_id']);
                        if ($img) {
                            $item['product']['image_id'] = $sku['image_id'];
                            $item['product']['ext'] = $img['ext'];
                        }
                    }

                    $item['sku_code'] = $sku['sku'];
                    $item['purchase_price'] = $sku['purchase_price'];
                    $item['sku_name'] = $sku['name'];
                    $item['currency'] = $item['product']['currency'];
                    $item['price'] = $sku['price'];
                    $item['name'] = $item['product']['name'];
                    if ($item['sku_name']) {
                        $item['name'] .= ' ('.$item['sku_name'].')';
                    }
                    // Fix for purchase price when rounding is enabled
                    if (!empty($item['product']['unconverted_currency']) && $item['product']['currency'] != $item['product']['unconverted_currency']) {
                        $item['purchase_price'] = shop_currency($item['purchase_price'], $item['product']['unconverted_currency'], $item['product']['currency'], false);
                    }
                } elseif ($item['type'] == 'service' && isset($services[$item['service_id']])) {
                    $item['name'] = $item['service_name'] = $services[$item['service_id']]['name'];
                    $item['currency'] = $services[$item['service_id']]['currency'];
                    $item['service'] = $services[$item['service_id']];
                    $item['variant_name'] = $variants[$item['service_variant_id']]['name'];
                    if ($item['variant_name']) {
                        $item['name'] .= ' ('.$item['variant_name'].')';
                    }
                    $item['price'] = $variants[$item['service_variant_id']]['price'];
                    if (isset($product_services[$item['product_id']][$item['service_variant_id']])) {
                        if ($product_services[$item['product_id']][$item['service_variant_id']]['price'] !== null) {
                            $item['price'] = $product_services[$item['product_id']][$item['service_variant_id']]['price'];
                        }
                    }
                    if (isset($sku_services[$item['sku_id']][$item['service_variant_id']])) {
                        if ($sku_services[$item['sku_id']][$item['service_variant_id']]['price'] !== null) {
                            $item['price'] = $sku_services[$item['sku_id']][$item['service_variant_id']]['price'];
                        }
                    }
                    if ($item['currency'] == '%') {
                        $p = $items[$item['parent_id']];
                        $item['price'] = $item['price'] * $p['price'] / 100;
                        $item['currency'] = $p['currency'];
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
        $sql = "SELECT c.*, p.currency, s.price
                FROM ".$this->table." c
                    JOIN shop_product p
                        ON c.product_id = p.id
                    JOIN shop_product_skus s
                        ON c.sku_id = s.id
                WHERE c.code = s:code
                    AND c.id = i:id";
        $result = $this->query($sql, array('code' => $code, 'id' => $id))->fetchAssoc();
        if ($result) {
            $result['unconverted_price'] = $result['price'];
            $result['unconverted_currency'] = $result['currency'];
            if ($result['price'] && shopRounding::isEnabled()) {
                $frontend_currency = wa('shop')->getConfig()->getCurrency(false);
                if ($frontend_currency != $result['currency']) {
                    $result['currency'] = $frontend_currency;
                    $result['price'] = shopRounding::roundCurrency(shop_currency($result['unconverted_price'], $result['unconverted_currency'], $frontend_currency, false), $frontend_currency);
                }
            }
        }
        return $result;
    }

    /**
     * @param string $code
     * @param bool|int $check_count bool or stock_id
     * @return array
     */
    public function getNotAvailableProducts($code, $check_count)
    {
        if (is_numeric($check_count)) {
            $stock_id = $check_count;
            $f = "IF(ps.sku_id IS NULL, s.count, ps.count)";
        } else {
            $stock_id = false;
            $f = "s.count";
        }
        $sql = "SELECT c.id, s.available, ".$f." AS count,
                p.name, s.name sku_name FROM ".$this->table." c
                JOIN shop_product p ON c.product_id = p.id
                JOIN shop_product_skus s ON c.sku_id = s.id";
        if ($stock_id) {
            $sql .= " LEFT JOIN shop_product_stocks ps ON ps.sku_id = s.id AND ps.stock_id = i:stock_id";
        }
        $sql .= " WHERE c.type = 'product' AND c.code = s:code AND ";
        if ($check_count) {
            $sql .= '(s.available = 0 OR ('.$f.' IS NOT NULL AND c.quantity > '.$f.'))';
        } else {
            $sql .= 's.available = 0';
        }
        return $this->query($sql, array('code' => $code, 'stock_id' => $stock_id))->fetchAll();
    }


    public function deleteByProducts($product_ids)
    {
        return $this->deleteByField('product_id', $product_ids);
    }

    public function getLastCode($contact_id)
    {
        return $this->select('code')->where('contact_id = ?', $contact_id)->order('id DESC')->fetchField();
    }
}
