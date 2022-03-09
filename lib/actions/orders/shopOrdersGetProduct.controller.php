<?php

class shopOrdersGetProductController extends waJsonController
{
    protected $product = null;

    /**
     * If requested by sku_id to return information on a specific sku
     * If only product_id was requested to return all product information
     * @return void|null
     */
    public function execute()
    {
        if ($this->validate()) {
            return null;
        }

        $shop_product = $this->getProduct();

        $product = $shop_product->getData();
        $product = $this->workupProduct($product);

        $product['skus'] = $shop_product->getSkus();
        $product['skus'] = $this->workupSkus($product['skus']);
        $product["show_order_counts"] = shopOrderEditAction::getProductOrderCounts();

        $units = shopHelper::getUnits();
        $formatted_units = shopFrontendProductAction::formatUnits($units);
        $fractional_config = shopFrac::getFractionalConfig();
        $this->response['stock_unit'] = ( $fractional_config["stock_units_enabled"] ? $formatted_units[$product["stock_unit_id"]] : null);

        if ($this->getSkuId()) {
            $sku = $product['skus'][$this->getSkuId()];
            $sku['services'] = $this->getServices($product, $sku);

            $this->response['product'] = $product;
            $this->response['sku'] = $sku;
            $this->response['service_ids'] = array_keys($sku['services']);
        } else {
            $sku = ifset($product, 'skus', $product['sku_id'], []);
            //take services for the main sku
            $product['services'] = $this->getServices($product, $sku);

            $this->response['product'] = $product;
            $this->response['sku_ids'] = array_keys($product['skus']);
            $this->response['service_ids'] = array_keys($product['services']);
        }
    }

    /**
     * Add the price in text format
     * Add an icon
     *
     * @param $product
     * @return mixed
     */
    protected function workupProduct($product)
    {
        $default_currency = wa('shop')->getConfig()->getCurrency(true);
        $currency = $this->getCurrency();

        //shopProduct always returns in standard store currency. @see shopRounding::roundProducts
        //Need to convert currencies into order currency
        if ($default_currency !== $currency) {
            foreach (array('price', 'min_price', 'max_price', 'compare_price') as $key) {
                $value = shop_currency($product[$key], $default_currency, $currency, false);
                $product[$key] = round($value, 2);
            }
        }

        if ($product['min_price'] == $product['max_price']) {
            $product['price_str'] = wa_currency($product['min_price'], $currency);
            $product['price_html'] = wa_currency_html($product['min_price'], $currency);
        } else {
            $product['price_str'] = wa_currency($product['min_price'], $currency).'...'.wa_currency($product['max_price'], $currency);
            $product['price_html'] = wa_currency_html($product['min_price'], $currency).'...'.wa_currency_html($product['max_price'], $currency);
        }

        if (!$product['image_id']) {
            $product['url_crop_small'] = null;
        } else {
            /** @var shopConfig $config */
            $config = $this->getConfig();

            $product['url_crop_small'] = shopImage::getUrl(
                array(
                    'id'         => $product['image_id'],
                    'filename'   => $product['image_filename'],
                    'product_id' => $product['id'],
                    'ext'        => $product['ext'],
                ),
                $config->getImageSize('crop_small')
            );
        }

        // aggregated stocks count icon for product
        $product['icon'] = shopHelper::getStockCountIcon($product['count'], null, true);

        return $product;
    }

    /**
     * Get services for a specific sku
     *
     * @param $product
     * @param $sku
     * @return array
     */
    private function getServices($product, $sku)
    {
        if (empty($product) || empty($sku)) {
            return [];
        }

        $service_model = new shopProductServicesModel();

        $out_currency = $this->getCurrency();
        $sku_price = $sku['price'];

        $services = $service_model->getAvailableServicesFullInfo($product, $sku['id']);
        $services = $this->workupServices($services, $sku_price, $out_currency);

        unset($service);
        return $services;
    }

    protected function workupServices($services, $sku_price, $out_currency)
    {
        foreach ($services as $service_id => &$service) {
            $service_currency = $service['currency'];

            foreach ($service['variants'] as &$variant) {
                if ($service['currency'] == '%') {
                    $variant['percent_price'] = $variant['price'];
                    // Converting interest to actual value
                    $variant['price'] = (float)$sku_price / 100 * $variant['price'];
                }

                //Price in text format
                $variant['price'] = $this->convertService($variant['price'], $service_currency, $out_currency);
                $variant['price_str'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency($variant['price'], $out_currency);
                $variant['price_html'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency_html($variant['price'], $out_currency);
            }
            unset($variant);

            // Sets the default price for the service.
            $default_variant = ifset($service, 'variants', $service['variant_id'], []);
            if (isset($default_variant['price'])) {
                $service['price'] = $default_variant['price'];
                if (isset($default_variant['percent_price'])) {
                    $service['percent_price'] = $default_variant['percent_price'];
                }
            } else {
                // Invalid database state.
                unset($services[$service_id]);
            }
        }

        return $services;
    }


    /**
     * Formats the price in a text string.
     * Adds icons to stock counts
     *
     * @param $skus
     * @return mixed
     */
    protected function workupSkus($skus)
    {
        if (empty($skus) || !is_array($skus)) {
            return $skus;
        }

        $currency = $this->getCurrency();

        $sku_stocks = $this->getSkuStocks(array_keys($skus));

        foreach ($skus as &$sku) {
            if (isset($sku['price'])) {
                $sku['price'] = round($sku['price'], 2);
                $sku['price_str'] = wa_currency($sku['price'], $currency);
                $sku['price_html'] = wa_currency_html($sku['price'], $currency);
            }

            // detailed stocks count icon for sku
            if (empty($sku_stocks[$sku['id']])) {
                $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
            } else {
                $icons = array();
                $counts_htmls = array();
                foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                    $icons[$stock_id] = shopHelper::getStockCountIcon($stock['count'], $stock_id)." ";
                    if ($stock['count'] === null) {
                        $counts_htmls[$stock_id] = '∞';
                    } else {
                        $counts_htmls[$stock_id] = _w('%s left', '%s left', shopFrac::discardZeros($stock['count']));
                    }
                }
                $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                $sku['icons'] = $icons;
                $sku['count_htmls'] = $counts_htmls;
            }
        }
        unset($sku);

        return $skus;
    }


    /**
     * Converts currency and rounds up if necessary.
     *
     * @param $price
     * @param $in_currency
     * @param $out_currency
     * @return float|mixed|string
     */
    public function convertService($price, $in_currency, $out_currency)
    {
        //If necessary, we convert currencies
        if ($in_currency != '%' && $in_currency != $out_currency) {
            $price = shop_currency($price, $in_currency, $out_currency, false);
        }

        // Always round off interest and if currency conversion occurs.
        if (($in_currency == '%' || $in_currency != $out_currency) && wa()->getSetting('round_services')) {
            $price = shopRounding::roundCurrency($price, $out_currency);
        }

        return $price;
    }

    /**
     * Get sku quantity
     *
     * @param $sku_ids
     * @return array
     */
    protected function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->getBySkuId($sku_ids);
    }

    /**
     * Check the validity of data from $_GET
     * @return array
     */
    protected function validate()
    {
        if (!$this->getProductId()) {
            $this->errors[] = _w("Unknown product");
        }
        if (!$this->getCurrency()) {
            $this->errors[] = _w("Unknown currency");
        }

        $product = $this->getProduct();

        if (!$product['id']) {
            $this->errors[] = _w("Unknown product");
        }

        //Check if there is such a sku
        if ($this->getSkuId() && empty($product['skus'][$this->getSkuId()])) {
            $this->errors[] = _w('Unknown SKU');
        }

        return $this->errors;
    }

    /**
     * Object is cached
     * @see http://php.net/manual/en/language.oop5.references.php
     *
     * @return shopProduct
     */
    protected function getProduct()
    {
        if (!$this->product) {
            $options = [
                'round_currency' => $this->getCurrency()
            ];

            $storefront_context = $this->getStorefront();
            if (!empty($storefront_context)) {
                $options['storefront_context'] = $storefront_context;
            }

            $this->product = new shopProduct($this->getProductId(), $options);
        }

        return $this->product;
    }

    /**
     * Return 'currency' from $_GET
     * @return string
     */
    protected function getCurrency()
    {
        return waRequest::get('currency', null, waRequest::TYPE_STRING);
    }

    /**
     * Return 'storefront' from $_GET
     * @return null|string
     */
    protected function getStorefront()
    {
        return waRequest::get('storefront', null, waRequest::TYPE_STRING);
    }

    /**
     * Return 'product_id' from $_GET
     * @return int
     */
    protected function getProductId()
    {
        return waRequest::get('product_id', 0, waRequest::TYPE_INT);
    }

    /**
     * Return 'sku_id' from $_GET
     * @return int
     */
    protected function getSkuId()
    {
        return waRequest::get('sku_id', 0, waRequest::TYPE_INT);
    }
}