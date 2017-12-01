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
        $counts = array_map('intval', (array) $this->getRequest()->request('count'));
        $from = (int) $this->getRequest()->request('from');
        $to = (int) $this->getRequest()->request('to');
        $count = (int) $this->getRequest()->request('count');

        if (empty($ids)) {
            $this->errors[] = array(
                'name' => 'id',
                'msg' => _w('Add at least one product to the transfer')
            );
        }

        if ($count <= 0) {
            $this->errors[] = array(
                'name' => 'count',
                'msg' => _w('Product count must be not less than 1')
            );
        }

        if (!$to) {
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
                    'count' => (int)ifset($counts[$index], 1)
                );
            } else {
                $items[$id]['count'] += (int)ifset($counts[$index], 1);
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
                'from' => $from,
                'to' => $to
            ),
            $items
        );

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
}
