<?php

class shopFrontendMyOrderDownloadAction extends shopFrontendAction
{
    public function execute()
    {
        $encoded_order_id = waRequest::param('id');
        $order_id = shopHelper::decodeOrderId($encoded_order_id);
        if (!$order_id) {
            // fall back to non-encoded id
            $order_id = $encoded_order_id;
            $encoded_order_id = shopHelper::encodeOrderId($order_id);
        }

        $om = new shopOrderModel();
        $order = $om->getOrder($order_id);
        if (!$order) {
            throw new waException(_w('Order not found'), 404);
        }
        if (!$this->isAuth($order)) {
            throw new waException(_w('The file will be available for download after the order is paid and processed.'), 404);
        }

        // Check auth code
        $opm = new shopOrderParamsModel();
        $params = $opm->get($order_id);
        $code = waRequest::param('code');
        if (ifset($params['auth_code']) !== $code) {
            throw new waException(_w('Order not found'), 404);
        }

        if ($item = ifempty($order['items'][waRequest::param('item')])) {
            $skus_model = new shopProductSkusModel();
            $sku = $skus_model->getById(ifempty($item['sku_id']));
            if ($sku['file_name'] && $sku['file_size']) {
                $file_path = shopProductSkusModel::getPath($sku);
                waFiles::readFile($file_path, $sku['file_name']);
            } else {
                throw new waException(_w('File not found'), 404);
            }
        } else {
            throw new waException(_w('Order item not found'), 404);
        }
    }

    protected function isAuth($order)
    {
        return $order && $order['paid_date'] && (!in_array($order['state_id'], array('deleted', 'refunded')));
    }
}
