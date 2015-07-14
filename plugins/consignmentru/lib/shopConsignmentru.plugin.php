<?php

class shopConsignmentruPlugin extends shopPrintformPlugin
{
    public function allowedCurrency()
    {
        return array('RUB', 'UAH');
    }

    protected function prepareForm(waOrder &$order, waView &$view)
    {
        $view->assign('items', $order->items);
    }
}
