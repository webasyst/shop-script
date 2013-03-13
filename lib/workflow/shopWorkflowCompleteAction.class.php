<?php

class shopWorkflowCompleteAction extends shopWorkflowAction
{
    public function execute($order_id = null)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if ($order['paid_year']) {
            return true;
        } else {
            shopAffiliate::applyBonus($order_id);
            $result = array(
                'update' => array(
                    'paid_year' => date('Y'),
                    'paid_quarter' => floor((date('n') - 1) / 3) + 1,
                    'paid_month' => date('n'),
                    'paid_date' => date('Y-m-d'),
                )
            );
            if (!$order_model->where("contact_id = ? AND paid_date IS NOT NULL", $order['contact_id'])->limit(1)->fetch()) {
                $result['update']['is_first'] = 1;
            }
            return $result;
        }
    }

    public function postExecute($order_id = null, $result = null)
    {
        parent::postExecute($order_id, $result);

        $order_model = new shopOrderModel();
        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $order_model->getById($order_id);
        }

        shopCustomers::recalculateTotalSpent($order['contact_id']);
        if ($order !== null) {
            $order_model->recalculateProductsTotalSales($order_id);
        }
    }
}
