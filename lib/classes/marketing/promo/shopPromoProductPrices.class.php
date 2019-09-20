<?php

class shopPromoProductPrices
{
    protected $options;

    /**
     * @var shopProductPromoPriceTmpModel
     */
    protected $model;
    protected $storefront;
    protected $shop_currency;

    protected $promo_prices;
    protected $promo_skus;

    public function __construct($options = [])
    {
        $options = is_array($options) ? $options : [];

        if (!empty($options['model']) && is_object($options['model']) && get_class($options['model']) == 'shopProductPromoPriceTmpModel') {
            // The temporary table model has a __destroy method to remove all content after use
            $this->model = $options['model'];
        } else {
            $this->model = new shopProductPromoPriceTmpModel();
        }

        if (wa()->getEnv() === 'frontend') {
            $routing_url = wa()->getRouting()->getRootUrl();
            $storefront = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');
            $this->storefront = $storefront;
        } else {
            $this->storefront = ifempty($options, 'storefront', null);
        }

        $this->options = $options;
        $this->shop_currency = wa('shop')->getConfig()->getCurrency();
    }

    protected function loadPromoPrices()
    {
        if (empty($this->storefront)) {
            $this->promo_prices = $this->promo_skus = [];
            return;
        }

        $enabled_promo_params = [
            'ignore_paused' => true,
            'storefront'    => $this->storefront,
            'rule_type'     => 'custom_price',
        ];

        // trying to find promotions in which rewrites prices of products
        $prlm = new shopPromoRulesModel();
        $rules = $prlm->getByActivePromos($enabled_promo_params);

        $promo_skus = $promo_prices = [];
        foreach ($rules as $rule) {
            foreach ($rule['rule_params'] as $product_id => $product_data) {
                $product_currency = $product_data['currency'];
                foreach ($product_data['skus'] as $sku_id => $sku) {
                    $promo_sku = ifempty($promo_skus, $product_id, $sku_id, null);
                    if ($sku['price'] && (empty($promo_sku) || $promo_sku['price'] > $sku['price'])) {
                        $promo_skus[$product_id][$sku_id] = [
                            'product_id'            => $product_id,
                            'sku_id'                => $sku_id,
                            'currency'              => $product_currency,
                            'price'                 => $sku['price'],
                            'primary_price'         => (float)shop_currency($sku['price'], $product_currency, $this->shop_currency, false),
                            'compare_price'         => $sku['compare_price'],
                            'primary_compare_price' => (float)shop_currency($sku['compare_price'], $product_currency, $this->shop_currency, false),
                        ];
                    }
                }
            }
        }

        foreach ($promo_skus as $product_id => $skus) {
            foreach ($skus as $sku) {
                $promo_prices[] = $sku;
            }
        }

        $this->promo_prices = $promo_prices;
        $this->promo_skus = $promo_skus;

        if (!empty($promo_prices)) {
            $this->model->multipleInsert($promo_prices);
        }
    }

    public function getPromoPrices()
    {
        if (!is_array($this->promo_prices)) {
            $this->loadPromoPrices();
        }

        return $this->promo_prices;
    }

    public function getPromoSkus()
    {
        if (!is_array($this->promo_skus)) {
            $this->loadPromoPrices();
        }

        return $this->promo_skus;
    }

    public function workupPromoProducts(&$products)
    {
        $this->loadPromoPrices();
        if (empty($this->promo_prices)) {
            return;
        }

        $price_fields = [
            'price',
            'min_price',
            'max_price',
            'compare_price'
        ];

        foreach ($products as &$p) {
            if (!isset($p['price'])) {
                continue;
            }

            $promo_sku_prices = ifempty($this->promo_skus, $p['id'], null);
            if (!$promo_sku_prices) {
                continue;
            }

            $p['is_promo'] = true;

            // Save original product prices
            foreach ($price_fields as $k) {
                if (isset($p[$k])) {
                    $p['raw_'.$k] = $p[$k];
                }
            }

            foreach ($promo_sku_prices as $promo_sku_price) {
                // Workup skus prices
                if (!empty($p['skus'])) {
                    $this->workupPromoSkus($p['skus'], $products);
                }
                // Main product price
                if ($p['sku_id'] == $promo_sku_price['sku_id']) {
                    if (isset($p['price'])) {
                        $p['price'] = $promo_sku_price['primary_price'];
                    }
                    if (isset($p['compare_price'])) {
                        $p['compare_price'] = $promo_sku_price['primary_compare_price'];
                    }
                }
                // Min product price
                if (isset($p['min_price']) && (float)$p['min_price'] > (float)$promo_sku_price['primary_price']) {
                    $p['min_price'] = $promo_sku_price['primary_price'];

                    // If the product has only one sku, then the maximum price will be the same
                    if (isset($p['sku_count'])) {
                        $p['max_price'] = $p['min_price'];
                    }
                }
                // Max product price
                if (isset($p['max_price']) && (float)$promo_sku_price['primary_price'] > (float)$p['max_price']) {
                    $p['max_price'] = $promo_sku_price['primary_price'];

                    // If the product has only one sku, then the minimum price will be the same
                    if (isset($p['sku_count']) && $p['sku_count'] == 1) {
                        $p['min_price'] = $p['max_price'];
                    }
                }
            }
        }
        unset($p);
    }

    public function workupPromoSkus(&$skus, $products)
    {
        $this->loadPromoPrices();
        if (empty($this->promo_prices)) {
            return;
        }

        $price_fields = [
            'price',
            'primary_price',
            'compare_price'
        ];

        foreach ($skus as &$sku) {
            foreach ($this->promo_prices as $promo_sku_price) {
                if ($sku['product_id'] == $promo_sku_price['product_id'] && $sku['id'] == $promo_sku_price['sku_id']) {

                    $sku['is_promo'] = true;

                    // Save original sku prices
                    foreach ($price_fields as $k) {
                        if (isset($sku[$k])) {
                            $sku['raw_'.$k] = $sku[$k];
                        }
                    }

                    $sku_product = ifset($products, $sku['product_id'], null);
                    $sku_product_currency = ifset($sku_product, 'currency', null);

                    $sku_price = (float)shop_currency($promo_sku_price['price'], $promo_sku_price['currency'], $sku_product_currency, false);
                    $sku_compare_price = (float)shop_currency($promo_sku_price['compare_price'], $promo_sku_price['currency'], $sku_product_currency, false);

                    $sku['primary_price'] = $promo_sku_price['primary_price'];
                    $sku['price'] = $sku_price;
                    $sku['compare_price'] = $sku_compare_price;
                }
            }
        }
        unset($sku);
    }
}