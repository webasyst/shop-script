<?php

class shopConsignmentruPlugin extends shopPrintformPlugin
{
    public function allowedCurrency()
    {
        return array('RUB', 'UAH');
    }

    /**
     * For backward compatibility with SS6 use this method
     * @param waOrder $order
     * @param waView $view
     */
    public function prepareForm(waOrder &$order, waView &$view)
    {
        $view->assign('items', $order->items);
    }

    public function preparePrintform($data, waView $view)
    {
        $order = $data['order'];
        /**
         * @var waOrder $order
         */
        $view->assign('items', $order->items);

        return $data;
    }
}
