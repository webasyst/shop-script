<?php

class shopSettingsCurrenciesAction extends waViewAction
{
    /**
     * @var array
     */
    protected $settings;

    public function execute()
    {
        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();
        foreach ($currencies as &$c) {
            $c['rate'] = $this->formatFloat($c['rate']);
        }
        unset($c);

        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */

        $wa = wa('shop');
        $primary = $config->getCurrency();
        $system_currencies = $this->getSystemCurrencies();
        $this->view->assign(array(
            'currencies' => $currencies,
            'primary' => $primary,
            'use_product_currency' => $wa->getSetting('use_product_currency'),
            'system_currencies' => $system_currencies,
            'rest_system_currencies' => array_diff_key($system_currencies, $currencies),
            'discount_distribution' => $this->getDiscountDistributionStrategy(),
            'rounding_options' => $config->getRoundingOptions(),
            'round_discounts' => $wa->getSetting('round_discounts', 0),
            'round_shipping' => $wa->getSetting('round_shipping', 0),
            'round_services' => $wa->getSetting('round_services', 0),
            'product_count' => $this->getProductCount($primary),
        ));
    }

    protected function getDiscountDistributionStrategy()
    {
        $wa = wa('shop');
        $discount_distrbution_rounding = $wa->getSetting('discount_distrbution_rounding', 1);
        $discount_distrbution_split = $wa->getSetting('discount_distrbution_split', 0);

        // These are normal options
        if ($discount_distrbution_rounding && !$discount_distrbution_split) {
            return 'increase_discount';
        } else if (!$discount_distrbution_rounding && $discount_distrbution_split) {
            return 'split_order_item';
        }

        // These are hidden options
        if (!$discount_distrbution_rounding && !$discount_distrbution_split) {
            return 'increase_discount_no_rounding';
        }

        // $discount_distrbution_rounding && $discount_distrbution_split
        return 'split_order_item_with_rounding';
    }

    public function formatFloat($float)
    {
        $float = (float) $float;
        // prevent exponential view fo floating
        if ($float < 1 && $float > 0) {
            $float = preg_replace('/0+$/', '', sprintf('%.8f', $float));
        }
        return $float;
    }

    public function getProductCount($currency)
    {
        $product_model = new shopProductModel();
        return $product_model->countByField("currency", $currency);
    }

    public function getSystemCurrencies()
    {
        $system_currencies = waCurrency::getAll(true);
        ksort($system_currencies);
        return $system_currencies;
    }
}
