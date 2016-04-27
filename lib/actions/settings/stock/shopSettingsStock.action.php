<?php
class shopSettingsStockAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign('stocks', shopHelper::getStocks());
    }
}
