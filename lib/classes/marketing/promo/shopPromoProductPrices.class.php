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
        } else {
            $storefront = null;
        }
        $this->storefront = ifempty($options, 'storefront', $storefront);

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

        $product_ids = [];
        foreach ($rules as $rule) {
            foreach ($rule['rule_params'] as $product_id => $product_data) {
                $product_ids[] = $product_id;
            }
        }

        $product_currencies = (new shopProductModel())->getCurrencies($product_ids);

        $promo_skus = $promo_prices = [];
        foreach ($rules as $rule) {
            foreach ($rule['rule_params'] as $product_id => $product_data) {
                if (empty($product_currencies[$product_id])) {
                    continue 2;
                }
                foreach ($product_data['skus'] as $sku_id => $sku) {
                    $promo_sku = ifempty($promo_skus, $product_id, $sku_id, null);
                    if ($sku['price'] && (empty($promo_sku) || $promo_sku['price'] > $sku['price'])) {
                        $promo_skus[$product_id][$sku_id] = [
                            'storefront'            => $this->storefront,
                            'product_id'            => $product_id,
                            'sku_id'                => $sku_id,
                            'price'                 => (float)shop_currency($sku['price'], $product_data['currency'], $product_currencies[$product_id]['currency'], false),
                            'primary_price'         => (float)shop_currency($sku['price'], $product_data['currency'], $this->shop_currency, false),
                            'compare_price'         => (float)shop_currency($sku['compare_price'], $product_data['currency'], $product_currencies[$product_id]['currency'], false),
                            'primary_compare_price' => (float)shop_currency($sku['compare_price'], $product_data['currency'], $this->shop_currency, false),
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
            'primary_price',
            'price',
            'compare_price'
        ];

        foreach ($skus as &$sku) {
            foreach ($this->promo_prices as $promo_sku_price) {
                if ($sku['product_id'] == $promo_sku_price['product_id'] && $sku['id'] == $promo_sku_price['sku_id']) {
                    $sku['is_promo'] = true;
                    foreach ($price_fields as $k) {
                        if (isset($sku[$k])) {
                            $sku['raw_'.$k] = $sku[$k];
                            $sku[$k] = $promo_sku_price[$k];
                        }
                    }
                }
            }
        }
        unset($sku);
    }
}