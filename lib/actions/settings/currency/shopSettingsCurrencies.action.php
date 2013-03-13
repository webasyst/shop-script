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
        $primary = $this->getConfig()->getCurrency();
        $system_currencies = $this->getSystemCurrencies();
        $this->view->assign(array(
            'currencies' => $currencies,
            'primary' => $primary,
            'use_product_currency' => wa()->getSetting('use_product_currency'),
            'system_currencies' => $system_currencies,
            'rest_system_currencies' => array_diff_key($system_currencies, $currencies),
            'product_count' => $this->getProductCount($primary)
        ));
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
