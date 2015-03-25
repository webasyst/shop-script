<?php
/**
 * Note: shop_product_services.primary_price is stored in shop primary currency, 
 * and shop_product_services.price is in shop_service.currency.
 */
class shopProductServicesModel extends waModel
{
    protected $table = 'shop_product_services';

    const STATUS_FORBIDDEN = '0';
    const STATUS_PERMITTED = '1';    // default in table
    const STATUS_DEFAULT   = '2';

    private $product;
    private $service;
    /**
     * @var shopProductModel
     */
    private $product_model;

    /**
     * @var shopServiceModel
     */
    private $service_model;

    /**
     * @param array $products
     */
    public function deleteByProducts(array $products, $service_id = null)
    {
        if (!$service_id) {
            $this->deleteByField('product_id', $products);
        } else {
            $this->deleteByField(array(
                'product_id' => $products,
                'service_id' => $service_id
            ));
        }
    }

    public function deleteByVariants($variants)
    {
        $this->deleteByField('service_variant_id', $variants);
    }

    public function getServiceIds($product_id)
    {
        $sql = "SELECT DISTINCT(service_id) FROM ".$this->table." WHERE product_id = i:product_id";
        return $this->query($sql, array('product_id' => $product_id))->fetchAll(null, true);
    }

    public function getByProducts($product_ids, $hierarchy = false)
    {
        if (!$product_ids) {
            return array();
        }
        $sql = "SELECT * FROM ".$this->table." WHERE product_id IN (i:ids) ORDER BY sku_id, service_variant_id";
        $rows = $this->query($sql, array('ids' => $product_ids))->fetchAll();
        if (!$hierarchy) {
            return $rows;
        }
        $result = array();
        foreach ($rows as $row) {
            $s_id = $row['service_id'];
            $v_id = $row['service_variant_id'];
            if (!$row['sku_id']) {
                if ($row['price'] === null && isset($result[$row['product_id']][$s_id]['price'])) {
                    $row['price'] = $result[$row['product_id']][$s_id]['price'];
                }
                $result[$row['product_id']][$row['service_id']]['variants'][$v_id] = $row;
            } else {
                if ($row['price'] === null && isset($result[$row['product_id']][$s_id]['variants'][$v_id])) {
                    $row['price'] = $result[$row['product_id']][$s_id]['variants'][$v_id]['price'];
                }
                $result[$row['product_id']]['skus'][$row['sku_id']][$s_id]['variants'][$v_id] = $row;
            }
        }
        return $result;
    }

    /**
     * Save
     * @param int $product_id
     * @param int $service_id
     * @param array $data
     *
     * Exmple of format
     *
     *    array (
     *     75 => // id of variant
     *     array (
     *       'price' => 500, // stirng|float|null
     *       'status' => '1',
     *       'skus' =>
     *       array (
     *         933 =>
     *         array (
     *           'price' => NULL,
     *           'status' => '1',
     *         ),
     *         934 =>
     *         array (
     *           'price' => NULL,
     *           'status' => '1',
     *         ),
     *       ),
     *     ),
     *     76 =>
     *     array (
     *       'price' => NULL,
     *       'status' => '2',
     *       'skus' =>
     *       array (
     *         933 =>
     *         array (
     *           'price' => NULL,
     *           'status' => '1',
     *         ),
     *         934 =>
     *         array (
     *           'price' => NULL,
     *           'status' => '1',
     *         ),
     *       ),
     *     ),
     *   )
     *
     * @return boolean
     */
    public function save($product_id, $service_id, $data)
    {
        $product_id = (int)$product_id;
        $service_id = (int)$service_id;
        if (!$product_id || !$service_id) {
            return false;
        }
        $key = array('product_id' => $product_id, 'service_id' => $service_id);

        $add = array();
        $update = array();

        $variants = $this->getVariants($product_id, $service_id);

        foreach ($variants as $v_id => $variant) {
            if (!isset($data[$v_id])) {
                continue;
            }
            $key['service_variant_id'] = $v_id;
            $ps_id = $variant['ps_id'];

            $item = $key + $data[$v_id];
            unset($item['skus']);

            if ($ps_id === null) {
                $item['sku_id'] = null;
                ksort($item);
                $add[] = $item;
            } else {
                $update[$ps_id] = $item;
            }

            if (!empty($data[$v_id]['skus'])) {
                foreach ($data[$v_id]['skus'] as $sku_id => $sku) {
                    $item = $key + $sku;
                    $item['sku_id'] = $sku_id;
                    if (empty($variant['skus'][$sku_id])) {
                        ksort($item);
                        $add[] = $item;
                    } else {
                        $update[$variant['skus'][$sku_id]['ps_id']] = $item;
                    }
                }
            }
        }

        if ($update) {
            foreach ($update as $id => $item) {
                $this->updateById($id, $item);
            }
        }
        if ($add) {
            $this->multipleInsert($add);
        }

        $service_model = new shopServiceModel();
        $currency = $service_model->select('currency')->where('id = i:id', array('id' => $service_id))->fetchField();
        if ($currency != '%') {
            $this->convertPrimaryPrices($product_id, $service_id);
        }

        return true;
    }

    public function getProductStatus($product_id, $service_id)
    {
        $product_id = (int) $product_id;
        $service_id = (int) $service_id;
        $sql = "SELECT MAX(status) status
                FROM {$this->table}
                WHERE product_id = $product_id AND service_id = $service_id";
        return (int)$this->query($sql)->fetchField('status');
    }

    private function convertPrimaryPrices($product_id, $service_id)
    {
        // convert inner pirce (primary_price) of itself
        $sql = "UPDATE `{$this->table}` ps
            JOIN `shop_service` s ON s.id = ps.service_id
            JOIN `shop_currency` c ON c.code = s.currency
            SET ps.primary_price = ps.price*c.rate
            WHERE ps.product_id = i:0 AND ps.service_id = i:1";
        $this->exec($sql, $product_id, $service_id);
    }

    private function getProduct($product_id) {
        if (!$this->product || $this->product['id'] != $product_id) {
            $this->product = $this->getProductModel()->getById($product_id);
        }
        return $this->product;
    }

    private function countServicesQuery($product_id)
    {
        $product_id = (int)$product_id;
        $product = $this->getProduct($product_id);
        if (!$product) {
            return false;
        }
        $type_id = $product['type_id'];

        $sql = "
            SELECT COUNT(r.id) cnt FROM (
                SELECT s.id
                FROM `shop_service` s
                    LEFT JOIN `{$this->table}` ps ON
                        s.id = ps.service_id AND ps.product_id = $product_id
                    LEFT JOIN `shop_type_services` ts ON
                        s.id = ts.service_id AND type_id ".($type_id ? " = $type_id" : " IS NULL")."
                WHERE ps.sku_id IS NULL AND
                    (
                        (ps.status IS NULL AND (ps.product_id IS NOT NULL OR ts.type_id IS NOT NULL))
                        OR
                        ps.status != ".self::STATUS_FORBIDDEN."
                    )
                GROUP BY s.id
            ) r
        ";
        return $sql;
    }

    public function getAvailableServicesFullInfo($product_id, $sku_id)
    {
        $sku_id = (int)$sku_id;
        $product_id = (int)$product_id;
        $product = $this->getProduct($product_id);
        if (!$product) {
            return array();
        }

        $services = $this->getServices($product);
        foreach ($services as $s_id => $service) {
            if ($service['status'] == self::STATUS_FORBIDDEN) {
                unset($services[$s_id]);
            }
        }
        if (empty($services)) {
            return array();
        }

        $service_ids = array_keys($services);

        $sql = "SELECT
                    sv.id,
                    sv.service_id,
                    sv.name,
                    sv.price AS base_price,
                    sv.primary_price AS base_primary_price,

                    ps.product_id,
                    ps.sku_id,
                    ps.price,
                    ps.primary_price,
                    ps.status

            FROM `shop_service_variants` sv
                LEFT JOIN `{$this->table}` ps ON sv.id = ps.service_variant_id AND ps.product_id = $product_id

            WHERE sv.service_id IN (".implode(',', $service_ids).") AND (ps.sku_id IS NULL OR ps.sku_id = $sku_id)
            ORDER BY sv.service_id, sv.id, ps.sku_id";

        $service_id = 0;
        foreach ($this->query($sql) as $item) {
            if ($item['service_id'] != $service_id) {
                $service_id = $item['service_id'];
                $services[$service_id]['variants'] = array(
                    $item['id'] => array(
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'price' => (float) ($item['price'] ? $item['price'] : $item['base_price']),
                        'primary_price' => (float) ($item['primary_price'] ? $item['primary_price'] : $item['base_primary_price']),
                        'status' => $item['status']
                    )
                );
                continue;
            }
            if ($item['sku_id'] === null) {
                $services[$service_id]['variants'][$item['id']] = array(
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => (float) ($item['price'] ? $item['price'] : $item['base_price']),
                    'primary_price' => (float) ($item['primary_price'] ? $item['primary_price'] : $item['base_primary_price']),
                    'status' => $item['status']
                );
            } else if (isset($services[$service_id]['variants'][$item['id']])) {
                if ($item['status'] !== null && $item['status'] == 0) {
                    unset($services[$service_id]['variants'][$item['id']]);
                } else if ($item['price'] !== null) {
                    $services[$service_id]['variants'][$item['id']]['price'] = (float) (
                        $item['price'] ? $item['price'] : $item['base_price']
                    );
                    $services[$service_id]['variants'][$item['id']]['primary_price'] = (float) (
                        $item['primary_price'] ? $item['primary_price'] : $item['base_primary_price']
                    );
                }
            }
        }
        // cut off that services that not available for this sku
        foreach ($services as $s_id => $service) {
            if (empty($service['variants'])) {
                unset($services[$s_id]);
            }
        }

        // set defaults (default variant may be overridden)
        foreach ($services as &$service) {
            $service['variant_id'] = $this->setDefaultVariant($service['variants'], $service['variant_id']);
        }
        unset($service);

        return $services;
    }

    public function getProductServiceFullInfo($product_id, $service_id = null)
    {
        $product = $this->getProduct($product_id);
        if (!$product) {
            return array();
        }

        $services = $this->getServices($product, $service_id);
        if (!$services) {
            return array();
        }
        if ($service_id) {
            $services = array(
                $service_id => $services
            );
        }
        $service_ids = array_keys($services);

        $data = array();

        $product_skus_model = new shopProductSkusModel();
        $skus = $product_skus_model->getByField('product_id', $product_id, 'id');

        $variants = $this->getVariants($product_id, $service_ids);

        foreach ($variants as $s_id => $service) {
            foreach ($service['variants'] as &$variant) {
                if ($variant['status'] === null) {
                    $variant['status'] = $services[$s_id]['type_id'] ? self::STATUS_PERMITTED :
                        self::STATUS_FORBIDDEN;
                }
                foreach ($skus as $sku_id => $sku) {
                    $sk_item = &$variant['skus'][$sku_id];
                    if (empty($sk_item)) {
                        $sk_item = array(
                            'id' => $variant['id'],
                            'sku_id' => $sku_id,
                            'price' => null,
                            'primary_price' => null,
                            'base_price' => $variant['base_price'],
                            'primary_base_price' => $variant['primary_base_price'],
                            'status' => self::STATUS_PERMITTED
                        );
                    }
                    $sk_item['name'] = $sku['name'];
                    // base_price on sku level is price on product level
                    if ($variant['price'] !== null) {
                        $sk_item['base_price'] = $variant['price'];
                        $sk_item['primary_base_price'] = $variant['primary_base_price'];
                    }
                    if ($variant['status'] == self::STATUS_FORBIDDEN) {
                        $sk_item['status'] = self::STATUS_FORBIDDEN;
                    }
                    unset($sk_item);
                }
                unset($variant);
            }
            $this->setDefaultVariant($service['variants'], $services[$s_id]['variant_id']);
            $data[$s_id] = $services[$s_id];
            $data[$s_id]['variants'] = $service['variants'];
        }
        return $service_id ? $data[$service_id] : $data;
    }

    /**
     * Mark default variant with SELF::STATUS_DEFAULT (if not marked)
     *
     * @param array $variants
     * @param int $default_variant_id
     * @return int ID of overridden default variant
     */
    private function setDefaultVariant(&$variants, $default_variant_id)
    {
        $default = 0;
        $overridden = 0;
        foreach ($variants as $variant_id => &$variant) {
            if ($variant_id == $default_variant_id) {
                $default = $default_variant_id;
            }
            if ($variant['status'] == self::STATUS_DEFAULT) {
                $default = $variant_id;
                $overridden = $variant_id;
                break;
            }
        }
        unset($variant);

        if (!$overridden) {
            if ($default && $variants[$default]['status'] == self::STATUS_PERMITTED) {
                $variants[$default]['status'] = self::STATUS_DEFAULT;
            } else {
                foreach ($variants as &$variant) {
                    if ($variant['status'] == self::STATUS_PERMITTED) {
                        $variant['status'] = self::STATUS_DEFAULT;
                        break;
                    }
                }
            }
        }
        return $default ? $default : $default_variant_id;
    }

    public function countServices($product_id)
    {
        $sql = $this->countServicesQuery($product_id);
        return $sql ? $this->query($sql)->fetchField('cnt') : 0;
    }

    /**
     * Service(s) and its availability for this product.
     *
     * Availability for product consists of:
     *     - availability for type of product
     *     - shop_product_services.status
     *
     * @param array $product
     * @param int|null $service_id
     * @return array
     */
    public function getServices($product, $service_id = null)
    {
        if (is_numeric($product)) {
            $product = $this->getProduct($product);
            if (!$product) {
                return array();
            }
        }
        $service_id = (int)$service_id;
        $product_id = $product['id'];
        $type_id = $product['type_id'];
        $sql = "
            SELECT
                s.id,
                s.name,
                s.variant_id,
                s.price,
                s.currency,
                ps.product_id,
                MAX(ps.status) AS status,
                ts.type_id
            FROM `shop_service` s
            LEFT JOIN `{$this->table}` ps ON
                s.id = ps.service_id AND ps.product_id = $product_id
            LEFT JOIN `shop_type_services` ts ON
                s.id = ts.service_id AND type_id ".($type_id ? " = $type_id" : " IS NULL")."
            WHERE ps.sku_id IS NULL ".($service_id ? " AND s.id = $service_id" : "")."
            GROUP BY s.id
            ORDER BY s.sort
        ";
        $services = $this->query($sql)->fetchAll('id');
        if (!$services) {
            return array();
        }
        foreach ($services as &$service) {
            if ($service['status'] === null) {
                if ($service['product_id'] || $service['type_id']) {
                    $service['status'] = self::STATUS_PERMITTED;
                } else {
                    $service['status'] = self::STATUS_FORBIDDEN;
                }
            }
        }
        return $service_id ? $services[$service_id] : $services;
    }

    /**
     *
     * Get 2-level [3-level] array
     *   - services
     *   - variants
     *   - skus for each variant
     *
     * format: array(
     *   <service_id> => array(
     *       'variants' => array(
     *          <variant_id> => array(
     *              ....
     *              'skus' => array(
     *                  <sku_id> => array(
     *                      ...
     *                  )
     *                  ...
     *              )
     *          )
     *          ...
     *       )
     *       ...
     *   )
     * )
     *
     * @param int $product_id
     * @param int|array $service_id
     * @return array
     */
    private function getVariants($product_id, $service_id)
    {
        $product_id = (int)$product_id;
        $service_ids = (array)$service_id;

        if (!$service_ids) {
            return array();
        }

        // left join used
        $sql = "
            SELECT
                sv.id,
                sv.service_id,
                sv.name,
                sv.price AS base_price,
                sv.primary_price AS primary_base_price,

                ps.id AS ps_id,
                ps.product_id,
                ps.sku_id,
                ps.price,
                ps.primary_price,
                ps.status

            FROM `shop_service_variants` sv
            LEFT JOIN `{$this->table}` ps ON sv.id = ps.service_variant_id AND ps.product_id = i:0
            WHERE sv.service_id IN (i:1)
            ORDER BY sv.service_id, sv.sort, ps.sku_id";

        $data = array();
        $sku_id = 0;
        $v_id   = 0;
        $s_id   = 0;
        foreach ($this->query($sql, $product_id, $service_ids) as $item) {
            if ($s_id != $item['service_id']) {
                $s_id = $item['service_id'];
                $data[$s_id]['variants'] = array();
            }
            if ($v_id != $item['id']) {
                $v_id = $item['id'];
                $data[$s_id]['variants'][$v_id] = $item;
                $data[$s_id]['variants'][$v_id]['skus'] = array();
                $sku_id = 0;
                continue;
            }
            if ($sku_id != $item['sku_id']) {
                $sku_id = $item['sku_id'];
            }
            $data[$s_id]['variants'][$v_id]['skus'][$sku_id] = $item;
        }
        return is_array($service_id) ? $data : $data[$s_id]['variants'];
    }

    /**
     * Get products for which service is setted
     * @param int $service_id
     * @param int|string $status
     * @return array
     */
    public function getProducts($service_id, $status = self::STATUS_PERMITTED)
    {
        $service_id = (int)$service_id;
        $status = (int)$status;

        return $this->query("
            SELECT p.* FROM `{$this->table}` ps
            JOIN `shop_product` p ON p.id = ps.product_id
            WHERE ps.service_id = $service_id AND ps.sku_id IS NULL
                AND ps.status = $status
            GROUP BY ps.product_id
            ORDER BY ps.product_id
        ")->fetchAll();
    }

    public function getSkus($product_id, $service_id)
    {
        $service_id = (int)$service_id;
        $product_id = (int)$product_id;
        return $this->query("
            SELECT ps.* FROM `{$this->table}` ps
            WHERE ps.service_id = $service_id AND ps.product_id = $product_id AND ps.sku_id IS NOT NULL AND ps.service_variant_id IS NULL
            ORDER BY ps.product_id
        ")->fetchAll('sku_id');
    }

    /**
     * @return shopProductModel
     */
    private function getProductModel()
    {
        if (!$this->product_model) {
            $this->product_model = new shopProductModel();
        }
        return $this->product_model;
    }

    /**
     * @retrun getServiceModel
     */
    private function getServiceModel()
    {
        if (!$this->service_model) {
            $this->service_model = new shopServiceModel();
        }
        return $this->service_model;
    }
}
