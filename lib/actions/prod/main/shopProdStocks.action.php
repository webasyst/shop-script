<?php
class shopProdStocksAction extends waViewAction
{
    public function execute()
    {
        $this->setTemplate('templates/actions/prod/main/Stocks.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
