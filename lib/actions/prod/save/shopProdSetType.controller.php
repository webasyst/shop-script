<?php

class shopProdSetTypeController extends waJsonController
{
    /**
     * @var shopTypeModel
     */
    private $type_model;

    public function execute()
    {
        $this->type_model = new shopTypeModel();
        $product_model = new shopProductModel();
        $type_id = $this->getType();

        if (!$type_id) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product type not found.'),
            ];
        }

        $all_product_ids = null;
        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $offset = 0;
        $hash = '';
        if (!$presentation_id) {
            $all_product_ids = $product_id;
            $hash = 'id/'.join(',', $all_product_ids);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
        }

        /**
         * Attaches a product to the types. Get data before changes
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param string $hash Collection Hash
         * @event products_types_set.before
         */
        $params = [
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        ];
        wa('shop')->event('products_types_set.before', $params);

        $collection = new shopProductsCollection($hash, $options);
        $count = 100;
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }
            $filtered = $product_model->filterAllowedProductIds($product_ids);
            $product_model->updateType($filtered, $type_id);
            $this->updateBasePrice($filtered);
            $all_updated_products = array_merge($all_updated_products, $product_ids);
            $offset += count($product_ids);
        }
        $count_all_updated_products = count($all_updated_products);
        if ($count_all_updated_products > 1) {
            for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
                $part_updated_products = array_slice($all_updated_products, $offset, 5000);
                $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
            }
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
        }

        /**
         * Attaches a product to the types
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param hash $hash Collection Hash
         * @event products_types_set.after
         */
        $params = [
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        ];
        wa('shop')->event('products_types_set.after', $params);

        $this->response['types'] = $this->type_model->getTypes();

    }

    public function getType()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_INT);
        if (!$type_id) {
            return null;
        } else {
            $types = shopTypeModel::extractAllowed([$type_id]);
            if (!$types) {
                return null;
            } else {
                return $type_id;
            }
        }
    }

    public static function updateBasePrice($ids)
    {
        if (!$ids) {
            return false;
        }
        $product_model = new shopProductModel();
        $sql = "SELECT p.id product_id, p.stock_base_ratio, p.sku_id = ps.id is_main_sku, p.currency, ps.price sku_price
                FROM {$product_model->getTableName()} p
                JOIN shop_product_skus ps ON p.id = ps.product_id
                WHERE p.id IN (?)";
        $skus = $product_model->query($sql, [$ids])->fetchAll();

        $config = wa('shop')->getConfig();
        $currency = $config->getCurrency();
        $currency_model = new shopCurrencyModel();

        $product_id = null;
        foreach ($skus as $sku) {
            if ((int)$sku['product_id'] !== $product_id) {
                if ($product_id) {
                    $product_model->updateById($product_id, [
                        'base_price' => $currency_model->convert($product_base_price, $product_currency, $currency),
                        'min_base_price' => $currency_model->convert(min($base_prices), $product_currency, $currency),
                        'max_base_price' => $currency_model->convert(max($base_prices), $product_currency, $currency),
                    ]);
                }

                $base_prices = [];
                $product_base_price = 0;
                $product_id = (int)$sku['product_id'];
                $product_currency = $sku['currency'];
            }

            $base_price = 0;
            if ($sku['stock_base_ratio'] > 0) {
                $base_price = $sku['sku_price'] / $sku['stock_base_ratio'];
            }
            $base_price = min(99999999999.9999, max(0.0001, $base_price));
            if ($sku['is_main_sku']) {
                $product_base_price = $base_price;
            }
            $base_prices[] = $base_price;
            if (!$base_prices) {
                $base_prices[] = 0;
            }
        }

        if ($product_id) {
            $product_model->updateById($product_id, [
                'base_price' => $currency_model->convert($product_base_price, $product_currency, $currency),
                'min_base_price' => $currency_model->convert(min($base_prices), $product_currency, $currency),
                'max_base_price' => $currency_model->convert(max($base_prices), $product_currency, $currency),
            ]);
        }

        return true;
    }
}
