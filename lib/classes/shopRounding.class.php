<?php

/**
 * Collection of functions to deal with prices rounding when converting from one currency to another.
 *
 * Ideas behind rounding are following:
 * - In the DB all prices are always stored as is, without any rounding.
 * - Rounding is applied before showing prices to frontend users.
 * - Templates /should/ know nothing about rounding. All prices /should/ have
 *   rounding already applied in PHP before passing data to templates. This is true for all
 *   vars that come from controllers, $wa->shop->* helpers and ProductsCollection.
 *   The only problem is that bad plugins unaware of rounding could potentially break this rule.
 */
class shopRounding
{
    public static function round($amount, $rounding, $round_up_only)
    {
        $rounding = (float)$rounding;
        if (!$rounding || !$amount) {
            return $amount;
        }

        list($round_unit, $shift, $precision) = self::getRoundingVars($rounding);
        $result = round($amount + $shift, $precision) - $shift;
        if (($round_up_only && $result < $amount) || ($amount > 0 && $result <= 0)) {
            $result += $round_unit;
        }

        return $result;
    }

    public static function roundCurrency($amount, $currency_id, $force_round_up = false)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $currencies = $config->getCurrencies();
        if (empty($currencies[$currency_id]) || empty($currencies[$currency_id]['rounding'])) {
            return (float)$amount;
        }
        $currency = $currencies[$currency_id];
        return self::round($amount, $currency['rounding'], $force_round_up || ifset($currency['round_up_only']));
    }

    public static function getRoundingVars($rounding)
    {
        $rounding = (float)$rounding;
        if (!$rounding) {
            $rounding = '0.01';
        }

        // $rounding => $round_unit $shift $precision
        // 100       => 100         0      -2
        // 99        => 100         1      -2
        // 10        => 10          0      -1
        // 9         => 10          1      -1
        // 1         => 1           0       0
        // 0.99      => 1           0.01    0
        // 0.9       => 1           0.1     0
        // 0.1       => 0.1         0       1
        // 0.01      => 0.01        0       2

        $precision = 0;
        $r = $rounding;
        if ($r > 1) {
            while ($r > 1) {
                $precision -= 1;
                $r = $r / 10;
            }
        } elseif ($r < 1) {
            $r = $r * 10;
            while ($r <= 1) {
                $precision += 1;
                $r = $r * 10;
            }
        }

        $round_unit = pow(10, -$precision);
        $shift = $round_unit - $rounding;

        // TODO cache?..

        return array((float)(string)$round_unit, (float)(string)$shift, (float)(string)$precision);
    }

    public static function roundProducts(&$products, $out_currency = null)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $default_currency = $config->getCurrency(true);
        // Get frontend currency if did not pass the parameter
        $out_currency = $out_currency ? $out_currency : $config->getCurrency(false);

        // All these fields are stored in shop PRIMARY currency - $default_currency
        // They must remain so - they are converted by design theme to $out_currency.
        // But! Theme does not know anything about rounding (for legacy reasons).
        // To apply rounding here, we must convert each field from $default_currency to $out_currency,
        // then apply rounding, then convert back from $out_currency to $default_currency.

        $rounding = array(
            'price',
            'min_price',
            'max_price',
            'compare_price'
        );
        foreach ($products as &$p) {
            if (empty($p['currency'])) {
                continue;
            }
            if (!isset($p['unconverted_currency'])) {
                $p['unconverted_currency'] = $p['currency'];

                // If rounding is disabled for currency, we pass to template unmodified currency.
                // If rounding is enabled, product currency is converted to frontend currency.
                if (!empty($curs[$out_currency]['rounding'])) {
                    $p['currency'] = $out_currency;
                }
                foreach ($rounding as $k) {
                    if (!isset($p[$k])) {
                        continue; // does not break on partly loaded data
                    }
                    $p['frontend_'.$k] = $p['unconverted_'.$k] = $p[$k];
                    if ($p[$k] > 0) {
                        $p['frontend_'.$k] = shop_currency($p[$k], $default_currency, $out_currency, null);
                        if ($p['unconverted_currency'] != $out_currency) {
                            if (!empty($curs[$out_currency]['rounding'])) {
                                $p['frontend_'.$k] = shopRounding::roundCurrency($p['frontend_'.$k], $out_currency);
                                $p[$k] = shop_currency($p['frontend_'.$k], $out_currency, $default_currency, null);
                            }
                        }
                    }
                }
            }

            if (!empty($p['skus'])) {
                self::roundSkus($p['skus'], array($p), $out_currency);
            }
        }
        unset($p);
    }

    public static function roundSkus(&$skus, $products = null, $out_currency = null)
    {
        if (!$skus) {
            return;
        }

        $products = self::indexById($products);

        // Load products that we don't have data for
        $products_to_load = array();
        foreach ($skus as $sku) {
            if (!empty($sku['product_id']) && empty($products[$sku['product_id']]) && !isset($sku['unconverted_currency'])) {
                $products_to_load[$sku['product_id']] = $sku['product_id'];
            }
        }
        if ($products_to_load) {
            $product_model = new shopProductModel();
            $products += $product_model->getByField(array('id' => array_values($products_to_load)), 'id');
        }

        // Convert $skus
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $primary_currency = $config->getCurrency(true);
        // Get frontend currency if did not pass the parameter
        $out_currency = $out_currency ? $out_currency : $config->getCurrency(false);
        foreach ($skus as &$sku) {
            $product = ifset($products, $sku['product_id'], null);
            $product_currency = ifset($product, 'unconverted_currency', ifset($product, 'currency', null));
            if (!$product_currency || !isset($curs[$product_currency]) || isset($sku['unconverted_currency'])) {
                continue;
            }

            $convert_currency = $product_currency != $out_currency && !empty($curs[$out_currency]['rounding']) && !empty($curs[$product_currency]);
            $sku['currency'] = $sku['unconverted_currency'] = $product_currency;
            if ($convert_currency) {
                $sku['currency'] = $out_currency;
            }

            // price and compare_price are stored in shop_product.currency (unconverted currency) - $product_currency
            foreach (array('price', 'compare_price') as $k) {
                if (!isset($sku[$k])) {
                    continue; // does not break on partly loaded data
                }

                $sku['frontend_'.$k] = $sku[$k];
                $sku['unconverted_'.$k] = $sku[$k];
                if ($sku[$k] > 0 && !empty($curs[$product_currency])) {
                    $sku['frontend_'.$k] = shop_currency($sku[$k], $product_currency, $out_currency, null);
                    if ($convert_currency) {
                        $sku[$k] = $sku['frontend_'.$k] = shopRounding::roundCurrency($sku['frontend_'.$k], $out_currency);
                    } else {
                        $sku[$k] = $sku['frontend_'.$k];
                    }
                }
            }

            // primary_price is stored in shop primary currency - $primary_currency
            // We must not round this value because it gets converted back in theme.
            if ($convert_currency && isset($sku['primary_price'])) {
                $sku['primary_price'] = shop_currency($sku['frontend_price'], $out_currency, $primary_currency, null);
            }
        }
        unset($sku);
    }

    public static function isEnabled($type = 'products')
    {
        switch ($type) {
            case 'products':
                // Rounding is always enabled for products now,
                // but could have been possibly disabled in the past.
                return true;
            case 'services':
                return wa()->getSetting('round_services', 0, 'shop');
            case 'shipping':
                return wa()->getSetting('round_shipping', 0, 'shop');
            case 'discounts':
                return wa()->getSetting('round_discounts', 0, 'shop');
            default:
                return false;
        }
    }

    public static function roundServices(&$services, $out_currency = null)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $default_currency = $config->getCurrency(true);
        $out_currency = $out_currency ? $out_currency : $config->getCurrency(false);

        foreach ($services as &$s) {
            if ($s['currency'] == '%') {
                continue;
            }
            if (!isset($s['unconverted_currency'])) {
                $s['frontend_price'] = $s['price'];
                $s['unconverted_price'] = $s['price'];
                $s['unconverted_currency'] = $s['currency'];
                if (!empty($curs[$out_currency]['rounding'])) {
                    $s['currency'] = $out_currency;
                }
                // shop_service.price is stored in shop primary currency - $default_currency
                if ($s['price'] && $s['unconverted_currency'] != $out_currency) {
                    $s['frontend_price'] = shop_currency($s['price'], $default_currency, $out_currency, null);
                    if (!empty($curs[$out_currency]['rounding'])) {
                        $s['frontend_price'] = shopRounding::roundCurrency($s['frontend_price'], $out_currency);
                        $s['price'] = shop_currency($s['frontend_price'], $out_currency, $default_currency, null);
                    }
                }
            }

            if (!empty($s['variants']) && !empty($curs[$s['currency']])) {
                self::roundServiceVariants($s['variants'], array($s), $out_currency);
            }
        }
        unset($s);
    }

    public static function roundServiceVariants(&$variants, $services = null, $out_currency = null)
    {
        if (!$variants) {
            return;
        }

        $services = self::indexById($services);

        // Load services that we don't have data for
        $services_to_load = array();
        foreach ($variants as $v) {
            if (!empty($v['service_id']) && empty($services[$v['service_id']]) && !isset($v['unconverted_currency'])) {
                $services_to_load[$v['service_id']] = $v['service_id'];
            }
        }
        if ($services_to_load) {
            $service_model = new shopServiceModel();
            $services += $service_model->getByField(array('id' => array_values($services_to_load)), 'id');
        }

        // Convert $variants
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $out_currency = $out_currency ? $out_currency : $config->getCurrency(false);

        $round_services = wa()->getSetting('round_services');
        foreach ($variants as &$v) {
            $service = ifset($services[$v['service_id']]);
            $service_currency = ifset($service['unconverted_currency'], ifset($service['currency']));
            if (!$service_currency || !isset($curs[$service_currency]) || isset($v['unconverted_currency'])) {
                continue;
            }

            $v['currency'] = $v['unconverted_currency'] = $service_currency;
            if (!empty($curs[$out_currency]['rounding'])) {
                $v['currency'] = $out_currency;
            }

            // shop_service_variant.primary_price is stored in shop primary currency
            // shop_service_variant.price is in shop_service.currency
            $v['frontend_price'] = $v['unconverted_price'] = $v['price'];
            if ($v['price'] > 0) {
                if (($service_currency != $out_currency) || $round_services) {
                    $v['frontend_price'] = shop_currency($v['price'], $service_currency, $out_currency, null);
                    if (!empty($curs[$out_currency]['rounding'])) {
                        $v['price'] = $v['frontend_price'] = shopRounding::roundCurrency($v['frontend_price'], $out_currency);
                    }
                }
            }
        }
        unset($v);
    }

    protected static function indexById($arr)
    {
        $result = array();
        if ($arr) {
            foreach ($arr as $s) {
                if (!empty($s['id']) && !empty($s['currency'])) {
                    $result[$s['id']] = $s;
                }
            }
        }
        return $result;
    }
}
