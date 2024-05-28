<?php

class shopTransferActions extends waJsonActions
{
    public function sendAction()
    {
        $transfer_id = $this->send();
        if (!$transfer_id) {
            return;
        }

        $transfer = $this->getTransferModel()->getById($transfer_id);
        $this->response = array(
            'transfer' => $transfer,
            'html' => $this->getTransferListHtml($transfer_id)
        );
    }

    public function send()
    {
        $ids = array_map('intval', (array) $this->getRequest()->request('id'));
        $counts = (array)$this->getRequest()->request('count');
        $prices = (array)$this->getRequest()->request('price');
        $currency = waRequest::request('currency', null, 'string');
        $from = (int) $this->getRequest()->request('from');
        $to = (int) $this->getRequest()->request('to');

        foreach ($counts as $key => &$value) {
            $value = floatval(str_replace(',', '.', $value));
            if ($value <= 0) {
                $this->errors[] = array(
                    'name' => "count[$key]",
                    'msg' => _w('Product count must be more than 0')
                );
            }
        }
        unset($value);

        if ($currency) {
            $currency = strtoupper($currency);
            $currency_model = new shopCurrencyModel();
            if (!$currency_model->getById($currency)) {
                $this->errors[] = array(
                    'name' => "currency",
                    'msg' => _w('Unknown currency'),
                );
            }

            foreach ($prices as $key => &$value) {
                if ($value !== '') {
                    $value = floatval(str_replace(',', '.', $value));
                    if ($value < 0) {
                        $this->errors[] = array(
                            'name' => "price[$key]",
                            'msg' => _w('Product prices cannot be negative.')
                        );
                    }
                }
            }
            unset($value);
        } else {
            $currency = null;
            $prices = [];
        }

        if (empty($ids)) {
            $this->errors[] = array(
                'name' => 'id',
                'msg' => _w('Add at least one product to the transfer')
            );
        }

        if (!$to && !$from) {
            $this->errors[] = array(
                'name' => 'to',
                'msg' => _w('Destination stock is required')
            );
        }

        $string_id = $this->getRequest()->request('string_id');
        $string_id = trim($string_id);

        $m = $this->getTransferModel();
        if (strlen($string_id) > 0 && !$m->isStringIdUnique($string_id)) {
            $this->errors[] = array(
                'name' => 'string_id',
                'msg' => _w('Transfer ID is already in use')
            );
        }

        if ($from == $to) {
            $this->errors[] = array(
                'name' => 'from',
                'msg' => ''
            );
            $this->errors[] = array(
                'name' => 'to',
                'msg' => _w('Source and destination stocks must not be the same')
            );
        }

        if ($this->errors) {
            return false;
        }

        $items = array();
        foreach ($ids as $index => $id) {
            if (!isset($items[$id])) {
                $items[$id] = array(
                    'sku_id' => $id,
                    'price' => ifempty($prices, $index, null),
                    'count' => (double) ifset($counts[$index], 1)
                );
            } else {
                $items[$id]['count'] += (double) ifset($counts[$index], 1);
            }
        }

        $spsm = new shopProductStocksModel();
        $stocks_info = $spsm->getByField(array('stock_id' => $from, 'sku_id'=> $ids), true);

        //Replace count
        foreach ($stocks_info as $info) {
            if (isset($items[$info['sku_id']]) && $info['count'] < $items[$info['sku_id']]['count']) {
                $items[$info['sku_id']]['count'] = $info['count'];
            }
        }

        //If count infinity
        if(!$stocks_info) {
            $from = null;
        }

        $transfer_id = $this->getTransferModel()->send(
            array(
                'string_id' => $string_id,
                'currency' => $currency,
                'from' => $from,
                'to' => $to
            ),
            $items
        );

        $this->waLog('transfer_sent', $transfer_id);

        return $transfer_id;
    }

    public function sendAndReceiveAction()
    {
        $transfer_id = $this->send();
        $this->getTransferModel()->receive($transfer_id);
        $transfer = $this->getTransferModel()->getById($transfer_id);
        $this->response = array(
            'transfer' => $transfer,
            'html' => $this->getTransferListHtml($transfer_id)
        );

        $this->waLog('transfer_completed', $transfer_id);
    }

    public function receiveAction()
    {
        $transfer_id = (int) $this->getRequest()->request('id');
        $this->getTransferModel()->receive($transfer_id);
        $transfer = $this->getTransferModel()->getById($transfer_id);
        $this->response = array(
            'transfer' => $transfer,
            'html' => $this->getTransferListHtml($transfer_id)
        );

        $this->waLog('transfer_completed', $transfer_id);
    }

    public function cancelAction()
    {
        $transfer_id = (int) $this->getRequest()->request('id');
        $this->getTransferModel()->cancel($transfer_id);
        $transfer = $this->getTransferModel()->getById($transfer_id);
        $this->response = array(
            'transfer' => $transfer,
            'html' => $this->getTransferListHtml($transfer_id)
        );

        $this->waLog('transfer_cancelled', $transfer_id);
    }

    public function getTransferListHtml($id)
    {
        $action = new shopTransferListAction(array(
            'disabled_lazyload' => true,
            'disabled_sort' => true,
            'limit' => 1,
            'filter' => 'id=' . $id
        ));
        return $action->display();
    }

    /**
     * @return shopTransferModel
     */
    public function getTransferModel()
    {
        return new shopTransferModel();
    }

    protected function waLog($action, $transfer_id)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add($action, $transfer_id);
    }
}
