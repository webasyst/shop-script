<?php

class shopWorkflowPayAction extends shopWorkflowAction
{

    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        $result = array(
            'update' => array(),
        );
        // from payment callback
        if (is_array($params)) {
            $order_id = $params['order_id'];
            if (isset($params['plugin'])) {
                $result['text'] = $params['plugin'].' (';
                if (!empty($params['view_data'])) {
                    $result['text'] .= $params['view_data'].' - ';
                }
                $result['text'] .= $params['amount'].' '.ifset($params, 'currency_id', '').')';
                $result['update']['params'] = array(
                    'payment_transaction_id' => $params['id'],
                );
            } else {
                if (isset($params['text'])) {
                    $result['text'] = $params['text'];
                }
                if (isset($params['update'])) {
                    $result['update'] = $params['update'];
                }
            }
        } else {
            $order_id = $params;
            $result['text'] = nl2br(htmlspecialchars(waRequest::post('text', ''), ENT_QUOTES, 'utf-8'));
        }
        $order = $this->order_model->getById($order_id);

        if (wa()->getEnv() == 'backend') {
            $this->waLog('order_pay', $order_id);
        } else {
            $this->waLog('order_pay_callback', $order_id, $order['contact_id']);
        }

        $this->preparePayData($result, $order);

        return $result;

    }

    protected function preparePayData(&$result, $order)
    {
        if (!$order['paid_year']) {
            shopAffiliate::applyBonus($order['id']);
            if ($this->getConfig()->getOption('order_paid_date') == 'create') {
                $time = strtotime($order['create_datetime']);
            } else {
                $time = time();
            }
            if (!isset($result['update'])) {
                $result['update'] = array();
            }
            $result['update'] = array_merge(array(
                'paid_year'     => date('Y', $time),
                'paid_quarter'  => floor((date('n', $time) - 1) / 3) + 1,
                'paid_month'    => date('n', $time),
                'paid_date'     => date('Y-m-d', $time),
                'paid_datetime' => date('Y-m-d H:i:s', $time),
            ), $result['update']);
            if (!$this->order_model->where("contact_id = ? AND paid_date IS NOT NULL", $order['contact_id'])->limit(1)->fetch()) {
                $result['update']['is_first'] = 1;
            }
        }

        $fields = array('total', 'currency');
        $changes = array();
        foreach ($fields as $field) {
            if (isset($result['update'][$field]) && ($result['update'][$field] != $order[$field])) {
                $change = sprintf(_w('Field %s changed %s → %s'), $field, $order[$field], $result['update'][$field]);
                $changes[] = sprintf('<li>%s</li>', $change);
            }
        }
        if ($changes) {
            if (!isset($result['text'])) {
                $result['text'] = '';
            }
            $result['text'] .= '<ul class="menu-v">'.implode($changes).'</ul>';
        }
    }

    public function postExecute($params = null, $result = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }
        $data = parent::postExecute($order_id, $result);

        if (is_array($order_id)) {
            $order = $order_id;
            $order_id = $order['id'];
        } else {
            $order = $this->order_model->getById($order_id);
        }

        if ($order !== null) {
            shopCustomer::recalculateTotalSpent($order['contact_id']);
            $this->order_model->recalculateProductsTotalSales($order_id);
        }

        $app_settings_model = new waAppSettingsModel();
        $update_on_create = $app_settings_model->get('shop', 'update_stock_count_on_create_order');
        $disable_stock_count = $app_settings_model->get('shop', 'disable_stock_count');

        if (!$disable_stock_count && !$update_on_create) {

            // for logging changes in stocks
            shopProductStocksLogModel::setContext(
                shopProductStocksLogModel::TYPE_ORDER,
                _w('Order %s was paid'),
                array(
                    'order_id' => $order_id,
                )
            );

            // jump through 'processing' state - reduce
            $this->order_model->reduceProductsFromStocks($order_id);
            shopProductStocksLogModel::clearContext();
        }

        $this->setPackageState(waShipping::STATE_DRAFT, $order, array('log' => true));

        return $data;
    }
}
