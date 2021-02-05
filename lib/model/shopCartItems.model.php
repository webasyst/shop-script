<?php

class shopCartItemsModel extends waModel
{
    protected $table = 'shop_cart_items';

    public function total($code)
    {
        if (!$code) {
            return 0;
        }
        $sql = "SELECT c.id as item_id, c.quantity, s.*
                FROM ".$this->table." c
                    JOIN shop_product_skus s ON c.sku_id = s.id
                WHERE c.code = s:code
                    AND type = 'product'";

        $skus = $this->query($sql, array('code' => $code))->fetchAll('item_id');
        if (!$skus) {
            return 0.0;
        }
        $product_ids = array();
        foreach ($skus as $k => $sku) {
            $product_ids[] = $sku['product_id'];
            $skus[$k]['original_price'] = $sku['price'];
            $skus[$k]['original_compare_price'] = $sku['compare_price'];
        }
        $product_ids = array_unique($product_ids);
        $product_model = new shopProductModel();
        $products = $product_model->getById($product_ids);

        foreach ($products as $p_id => $p) {
            $products[$p_id]['original_price'] = $p['price'];
            $products[$p_id]['original_compare_price'] = $p['compare_price'];
        }

        $this->promoProductPrices()->workupPromoProducts($products);
        $this->promoProductPrices()->workupPromoSkus($skus, $products);

        $event_params = array(
            'products' => &$products,
            'skus' => &$skus
        );
        wa('shop')->event('frontend_products', $event_params);
        shopRounding::roundSkus($skus);
        $products_total = 0.0;
        foreach ($skus as $s) {
            $products_total += $s['frontend_price'] * $s['quantity'];
        }
        // services
        $services_total = $this->getServicesTotal($code, $event_params);
        return (float) ($products_total + $services_total);
    }

    // Helper for total()
    // Services total in frontend currency
    protected function getServicesTotal($code, $products_skus)
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

        $variant_ids = array();
        $product_ids = array();
        $service_stubs = array();
        foreach ($services as $s) {
            if ($s['service_variant_id']) {
                $variant_ids[] = $s['service_variant_id'];
            }
            $product_ids[] = $s['product_id'];

            $service_stubs[$s['service_id']] = array(
                'id' => $s['service_id'],
                'currency' => $s['currency'],
            );
        }
        $variant_ids = array_unique($variant_ids);
        $product_ids = array_unique($product_ids);

        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */

        // get variant settings
        $rounding_enabled = shopRounding::isEnabled('services');
        $variants_model = new shopServiceVariantsModel();
        $variants = $variants_model->getWithPrice($variant_ids);
        if ($rounding_enabled) {
            shopRounding::roundServiceVariants($variants, $service_stubs);
        }

        // get products/skus settings
        $product_services_model = new shopProductServicesModel();
        $products_services = $product_services_model->getByProducts($product_ids, true);



        $primary = $config->getCurrency();
        $frontend_currency = $config->getCurrency(false);

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
                shopRounding::roundServiceVariants(
                    $p_services['skus'][$sku_id][$s_id]['variants'],
                    array(
                        array(
                            'id'       => $s['service_id'],
                            'currency' => $s['currency'],
                        ),
                    )
                );
                $s['price'] = $p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'];
            }

            if ($s['currency'] == '%') {
                if (isset($products_skus['skus'][$s['parent_id']])) {
                    $sku_price = $products_skus['skus'][$s['parent_id']]['frontend_price'];
                } else {
                    // most likely never happen case, but just in case
                    $product = $products_skus['products'][$s['product_id']];
                    $product_price = $product['price'];
                    $product_currency = $product['currency'] !== null ? $product['currency'] : $primary;
                    $sku_price = shop_currency($product_price, $product_currency, $frontend_currency, false);
                }
                $s['price'] = shop_currency($s['price'] * $sku_price / 100, $frontend_currency, $frontend_currency, false);
            } else {
                $s['price'] = shop_currency($s['price'], $variants[$v_id]['currency'], $frontend_currency, false);
            }

            if ($rounding_enabled) {
                $s['price'] = shopRounding::roundCurrency($s['price'], $frontend_currency);
            }

            $services_total += $s['price'] * $s['quantity'];
        }
        return $services_total;
    }

    public function count($code, $type = null)
    {
        if (!$code) {
            return 0;
        }
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
        $sql = <<<SQL
SELECT c1.*
FROM {$this->table} c1
LEFT JOIN {$this->table} c2
ON c1.id = c2.parent_id
WHERE
  c1.code = s:0
  AND
  c1.type = 'product'
  AND
  c1.product_id = i:1
  AND
  c1.sku_id = i:2
  AND
  c2.id IS NULL
LIMIT 1
SQL;
        return $this->query($sql, $code, $product_id, $sku_id)->fetch();
    }

    public function getByCode($code, $full_info = false, $hierarchy = true)
    {
        if (!$code) {
            return array();
        }
        $sql = "SELECT * FROM ".$this->table." WHERE code = s:0 ORDER BY id, parent_id";
        $items = $this->query($sql, $code)->fetchAll('id');

        $obsolete = array();

        if ($full_info) {

            $product_rounding_enabled = shopRounding::isEnabled();
            $service_rounding_enabled = shopRounding::isEnabled('services');

            $round_services = wa()->getSetting('round_services');

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

            foreach ($products as $p_id => $p) {
                $products[$p_id]['original_price'] = $p['price'];
                $products[$p_id]['original_compare_price'] = $p['compare_price'];
            }

            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getByField('id', $sku_ids, 'id');

            foreach ($skus as $s_id => $s) {
                $skus[$s_id]['original_price'] = $s['price'];
                $skus[$s_id]['original_compare_price'] = $s['compare_price'];
            }

            $this->promoProductPrices()->workupPromoProducts($products);
            $this->promoProductPrices()->workupPromoSkus($skus, $products);

            $event_params = array(
                'products' => &$products,
                'skus'     => &$skus,
            );
            wa('shop')->event('frontend_products', $event_params);

            $product_rounding_enabled && shopRounding::roundProducts($products);
            $product_rounding_enabled && shopRounding::roundSkus($skus, $products);

            $service_model = new shopServiceModel();
            $services = $service_model->getByField('id', $service_ids, 'id');
            $service_rounding_enabled && shopRounding::roundServices($services);

            $service_variants_model = new shopServiceVariantsModel();
            $variants = $service_variants_model->getByField('id', $variant_ids, 'id');
            $service_rounding_enabled && shopRounding::roundServiceVariants($variants, $services);

            $product_services_model = new shopProductServicesModel();
            $rows = $product_services_model->getByProducts($product_ids);
            $service_rounding_enabled && shopRounding::roundServiceVariants($rows, $services);

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
                if (($item['type'] == 'product')
                    && isset($products[$item['product_id']])
                    && isset($skus[$item['sku_id']])
                ) {
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
                            $item['product']['image_filename'] = $img['filename'];
                            $item['product']['ext'] = $img['ext'];
                        }
                    }

                    $item['sku_code'] = $sku['sku'];
                    $item['purchase_price'] = $sku['purchase_price'];
                    $item['compare_price'] = $sku['compare_price'];
                    $item['sku_name'] = $sku['name'];
                    $item['currency'] = $item['product']['currency'];
                    $item['price'] = $sku['price'];
                    $item['name'] = $item['product']['name'];
                    $item['sku_file_name'] = $sku['file_name'];
                    if (is_scalar($item['sku_name']) && strlen($item['sku_name']) > 0) {
                        $item['name'] .= ' ('.$item['sku_name'].')';
                    }
                    // Fix for purchase price when rounding is enabled
                    if (!empty($item['product']['unconverted_currency']) && $item['product']['currency'] != $item['product']['unconverted_currency']) {
                        $item['purchase_price'] = shop_currency($item['purchase_price'], $item['product']['unconverted_currency'], $item['product']['currency'], false);
                    }
                } elseif (($item['type'] == 'service')
                    && isset($services[$item['service_id']])
                    && isset($items[$item['parent_id']])
                    && isset($products[$items[$item['parent_id']]['product_id']])
                ) {
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
                        $item['price'] = shop_currency($item['price'] * $p['price'] / 100, $p['currency'], $p['currency'], false);
                        $item['currency'] = $p['currency'];
                    }
                } else {
                    $obsolete[] = $item_key;
                    unset($items[$item_key]);
                    continue;
                }

                if ($round_services && ($item['type'] == 'service')) {
                    $item['price'] = shopRounding::roundCurrency($item['price'], $item['currency']);
                }
            }
            unset($item);
        }

        // delete outdated cart items
        if ($obsolete) {
            $this->deleteByField(
                array(
                    'code' => $code,
                    'id'   => $obsolete,
                )
            );
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
        $row = $this->getByField(array('code' => $code, 'id' => $id));
        if (!$row) {
            return array();
        }
        $product_model = new shopProductModel();
        $p = $product_model->getById($row['product_id']);
        if (!$p) {
            return array();
        }
        $p['original_price'] = $p['price'];
        $p['original_compare_price'] = $p['compare_price'];
        $products = array($p['id'] => $p);
        $skus_model = new shopProductSkusModel();
        $s = $skus_model->getById($row['sku_id']);
        if (!$s) {
            return array();
        }
        $s['original_price'] = $s['price'];
        $s['original_compare_price'] = $s['compare_price'];
        $skus = array($s['id'] => $s);

        $this->promoProductPrices()->workupPromoProducts($products);
        $this->promoProductPrices()->workupPromoSkus($skus, $products);

        $event_params = array(
            'products' => &$products,
            'skus' => &$skus
        );
        wa('shop')->event('frontend_products', $event_params);
        $result = $row;
        $result['price'] = $skus[$result['sku_id']]['price'];
        $result['currency'] = $products[$result['product_id']]['currency'];
        $result['unconverted_price'] = $result['price'];
        $result['unconverted_currency'] = $result['currency'];
        if ($result['price'] && shopRounding::isEnabled()) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */

            $frontend_currency = $config->getCurrency(false);
            if ($frontend_currency != $result['currency']) {
                $result['currency'] = $frontend_currency;
                $result['price'] = shopRounding::roundCurrency(
                    shop_currency($result['unconverted_price'], $result['unconverted_currency'], $frontend_currency, false),
                    $frontend_currency
                );
            }
        }
        return $result;
    }

    /**
     * @param string $code
     * @param bool|int|string $check_count bool or stock_id or 'v<virtualstock_id>'
     * @return array
     */
    public function getNotAvailableProducts($code, $check_count)
    {
        return array_filter(
            $this->checkAvailability($code, $check_count),
            function ($item) {
                return !$item['can_be_ordered'];
            }
        );
    }

    /**
     * @param string $code
     * @param bool|int|string $check_count bool or stock_id or 'v<virtualstock_id>'
     * @return array
     */
    public function checkAvailability($code, $check_count)
    {
        $count_join = '';
        $count_field = 'NULL';
        $can_be_ordered_field = '(s.available > 0 AND s.status > 0 AND p.status >= 1)';
        if ($check_count) {
            if (is_string($check_count) && $check_count[0] == 'v') {
                // Virtual stock id: check against sum of several stock counts
                $virtualsku_id = substr($check_count, 1);
                if (wa_is_int($virtualsku_id)) {
                    $sql = "SELECT stock_id FROM shop_virtualstock_stocks WHERE virtualstock_id=?";
                    $stock_ids = array_keys($this->query($sql, $virtualsku_id)->fetchAll('stock_id'));
                    if ($stock_ids) {
                        $sql = "SELECT DISTINCT sku_id FROM {$this->table} WHERE type = 'product' AND code = ?";
                        $sku_ids = array_keys($this->query($sql, array($code))->fetchAll('sku_id'));

                        if ($sku_ids) {
                            $count_field = 't.count';
                            $count_join = "LEFT JOIN (
                                               SELECT ps.sku_id, SUM(ps.count) AS count
                                               FROM shop_product_stocks AS ps
                                               WHERE ps.stock_id IN (".join(',', $stock_ids).")
                                                   AND ps.sku_id IN (".join(',', $sku_ids).")
                                               GROUP BY ps.sku_id
                                               HAVING (COUNT(ps.stock_id)) >= ".count($stock_ids)."
                                           ) AS t ON t.sku_id=ci.sku_id";
                        }
                    }
                }
            } elseif (wa_is_int($check_count)) {
                // Normal stock id: check against stock count
                $count_field = "ps.count";
                $count_join = "LEFT JOIN shop_product_stocks AS ps
                                   ON ps.sku_id = ci.sku_id AND ps.stock_id = '{$check_count}'";

               //
               // There's a catch.
               // In case product does not use stocks, actual counts are stored in `product_skus.count`.
               // This function currently does not check for that. This may or may not be good,
               // but currently is not considered a bug. Storefront limited by main stock
               // should not list products that does not use stocks.
               // See also shopProductsCollection->countOfSelectedStocks()
               //

            } else {
                // No stock specified; check against total count of the SKU
                $count_field = 's.count';
            }
            $can_be_ordered_field = "(s.available > 0 AND s.status > 0 AND ({$count_field} IS NULL OR ci.quantity <= {$count_field}) AND p.status >= 1)";
        }

        $sql = "SELECT ci.id, p.name, s.name AS sku_name, s.available, s.status as `sku_status`, p.status > 0 as `status`, ci.quantity, s.id as sku_id,
                    {$can_be_ordered_field} as `can_be_ordered`,
                    {$count_field} AS `count`
                FROM {$this->table} AS ci
                    JOIN shop_product AS p
                        ON ci.product_id = p.id
                    JOIN shop_product_skus AS s
                        ON ci.sku_id = s.id
                    {$count_join}
                WHERE ci.type = 'product'
                    AND ci.code = s:code";
        $products = $this->query($sql, array('code' => $code))->fetchAll('id');

        $quantity = array();
        foreach ($products as $product) {
            if (!empty($product['count'])) {
                if (!isset($quantity[$product['sku_id']])) {
                    $quantity[$product['sku_id']] = $product['quantity'];
                } else {
                    $quantity[$product['sku_id']] += $product['quantity'];
                }
            }
        }
        foreach ($products as $key => &$product) {
            if (isset($quantity[$product['sku_id']])) {
                $product['count'] -= $quantity[$product['sku_id']] - $product['quantity'];
                if ($product['count'] < $product['quantity']) {
                    $product['can_be_ordered'] = 0;
                }
            }
        }
        unset($product);

        return $products;
    }

    public function deleteByProducts($product_ids)
    {
        return $this->deleteByField('product_id', $product_ids);
    }

    public function getLastCode($contact_id)
    {
        return $this->select('code')->where('contact_id = ?', $contact_id)->order('id DESC')->fetchField();
    }

    /**
     * @return shopPromoProductPrices
     */
    protected function promoProductPrices()
    {
        static $promo_product_prices_class;

        if (empty($promo_product_prices_class)) {
            $promo_prices_model = new shopProductPromoPriceTmpModel();
            $promo_product_prices_class = new shopPromoProductPrices(['model' => $promo_prices_model]);
        }

        return $promo_product_prices_class;
    }
}
