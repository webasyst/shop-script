<?php

class shopDialogCurrencyDeleteAction extends waViewAction
{
    /**
     * @var array
     */
    protected $settings;

    public function execute()
    {
        $code = waRequest::get('code', '', waRequest::TYPE_STRING_TRIM);
        $model = new shopCurrencyModel();
        $currency = $model->getById($code);
        if (!$currency) {
            throw new waException(_w("Unknown currency"));
        }

        $this->view->assign(array(
            'currency' => $currency,
            'currencies' => $model->getCurrencies(),
            'product_count' => $this->getProductCount($code)
        ));
    }

    public function getProductCount($currency)
    {
        $product_model = new shopProductModel();
        return $product_model->countByField("currency", $currency);
    }

}
