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
        if (!$rounding) {
            return $amount;
        }

        list($round_unit, $shift, $precision) = self::getRoundingVars($rounding);
        $result = round($amount + $shift, $precision) - $shift;
        if ($round_up_only && $result < $amount) {
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

    public static function roundProducts(&$products)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $default_currency = $config->getCurrency(true);
        $frontend_currency = $config->getCurrency(false);

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
                if (!empty($curs[$frontend_currency]['rounding'])) {
                    $p['currency'] = $frontend_currency;
                }
                foreach ($rounding as $k) {
                    if (!isset($p[$k])) {
                        continue; // does not break on partly loaded data
                    }
                    $p['frontend_'.$k] = $p['unconverted_'.$k] = $p[$k];
                    if ($p[$k] > 0) {
                        if ($p['unconverted_currency'] != $frontend_currency) {
                            $p['frontend_'.$k] = shop_currency($p[$k], $default_currency, $frontend_currency, null);
                            if (!empty($curs[$frontend_currency]['rounding'])) {
                                $p['frontend_'.$k] = shopRounding::roundCurrency($p['frontend_'.$k], $frontend_currency);
                                $p[$k] = shop_currency($p['frontend_'.$k], $frontend_currency, $default_currency, null);
                            }
                        } else {
                            $p['frontend_'.$k]= $p[$k] = shop_currency($p[$k], $frontend_currency, $frontend_currency, false);
                        }
                    }
                }
            }

            if (!empty($p['skus'])) {
                self::roundSkus($p['skus'], array($p));
            }
        }
        unset($p);
    }

    public static function roundSkus(&$skus, $products = null)
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
        $frontend_currency = $config->getCurrency(false);
        foreach ($skus as &$sku) {
            $product = ifset($products[$sku['product_id']]);
            $product_currency = ifset($product['unconverted_currency'], ifset($product['currency']));
            if (!$product_currency || !isset($curs[$product_currency]) || isset($sku['unconverted_currency'])) {
                continue;
            }

            $convert_currency = $product_currency != $frontend_currency && !empty($curs[$frontend_currency]['rounding']) && !empty($curs[$product_currency]);
            $sku['currency'] = $sku['unconverted_currency'] = $product_currency;
            if ($convert_currency) {
                $sku['currency'] = $frontend_currency;
            }

            foreach (array('price', 'compare_price') as $k) {
                if (!isset($sku[$k])) {
                    continue; // does not break on partly loaded data
                }

                $sku['frontend_'.$k] = $sku[$k];
                $sku['unconverted_'.$k] = $sku[$k];
                if ($sku[$k] > 0 && !empty($curs[$product_currency])) {
                    $sku['frontend_'.$k] = shop_currency($sku[$k], $product_currency, $frontend_currency, false);
                    if ($convert_currency) {
                        $sku[$k] = $sku['frontend_'.$k] = shopRounding::roundCurrency($sku['frontend_'.$k], $frontend_currency);
                    } else {
                        $sku[$k] = $sku['frontend_'.$k];
                    }
                }
            }

            if ($convert_currency && isset($sku['primary_price'])) {
                $sku['primary_price'] = shop_currency($sku['frontend_price'], $frontend_currency, $primary_currency, false);
            }
        }
        unset($sku);
    }

    public static function isEnabled()
    {
        // Rounding is always enabled now, but used to be disabled in the past.
        return true;
    }

    public static function roundServices(&$services)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $curs = $config->getCurrencies();
        $default_currency = $config->getCurrency(true);
        $frontend_currency = $config->getCurrency(false);

        foreach ($services as &$s) {
            if ($s['currency'] == '%') {
                continue;
            }
            if (!isset($s['unconverted_currency'])) {
                $s['frontend_price'] = $s['price'];
                $s['unconverted_price'] = $s['price'];
                $s['unconverted_currency'] = $s['currency'];
                if (!empty($curs[$frontend_currency]['rounding'])) {
                    $s['currency'] = $frontend_currency;
                }
                if ($s['price'] && $s['unconverted_currency'] != $frontend_currency) {
                    $s['frontend_price'] = shop_currency($s['price'], $default_currency, $frontend_currency, false);
                    if (!empty($curs[$frontend_currency]['rounding'])) {
                        $s['frontend_price'] = shopRounding::roundCurrency($s['frontend_price'], $frontend_currency);
                        $s['price'] = shop_currency($s['frontend_price'], $frontend_currency, $default_currency, false);
                    }
                } else {
                    $s['price'] = shop_currency($s['frontend_price'], $frontend_currency, $frontend_currency, false);
                }
            }

            if (!empty($s['variants']) && !empty($curs[$s['currency']])) {
                self::roundServiceVariants($s['variants'], array($s));
            }
        }
        unset($s);
    }

    public static function roundServiceVariants(&$variants, $services = null)
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
        $frontend_currency = $config->getCurrency(false);

        $round_services = wa()->getSetting('round_services');
        foreach ($variants as &$v) {
            $service = ifset($services[$v['service_id']]);
            $service_currency = ifset($service['unconverted_currency'], ifset($service['currency']));
            if (!$service_currency || !isset($curs[$service_currency]) || isset($v['unconverted_currency'])) {
                continue;
            }

            $v['currency'] = $v['unconverted_currency'] = $service_currency;
            if (!empty($curs[$frontend_currency]['rounding'])) {
                $v['currency'] = $frontend_currency;
            }

            $v['frontend_price'] = $v['unconverted_price'] = $v['price'];
            if ($v['price'] > 0) {
                if (($service_currency != $frontend_currency) || $round_services) {
                    $v['frontend_price'] = shop_currency($v['price'], $service_currency, $frontend_currency, false);
                    if (!empty($curs[$frontend_currency]['rounding'])) {
                        $v['price'] = $v['frontend_price'] = shopRounding::roundCurrency($v['frontend_price'], $frontend_currency);
                    }
                } else {
                    $v['price'] = shop_currency($v['price'], $frontend_currency, $frontend_currency, false);
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
