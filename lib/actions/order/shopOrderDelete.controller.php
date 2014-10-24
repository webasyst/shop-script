<?php

class shopOrderDeleteController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            throw new waException(_w("Access denied"));
        }
        
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        if ($id) {
            $model = new shopOrderModel();
            if ($model->delete($id)) {
                $this->response = shopHelper::workupOrders($model->getOrder($id), true);
            }
        }
    }
}
