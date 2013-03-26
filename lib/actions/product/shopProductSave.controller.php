<?php
class shopProductSaveController extends waJsonController
{
    public function execute()
    {
        $update = waRequest::post('update'); // just update one or any field of product
        if ($update) {
            $this->update($update);
            return;
        }

        $data = waRequest::post('product');
        $id = (empty($data['id']) || !intval($data['id'])) ? null : $data['id'];
        if (!$id && isset($data['id'])) {
            unset($data['id']);
        }

        $product = new shopProduct($id);
        try {
            if ($product->save($data, true, $this->errors)) {
                $this->response['id'] = $product->getId();
                $this->response['name'] = $product->name;
                $this->response['url']  = $product->url;
                $this->response['frontend_url'] = wa()->getRouteUrl('/frontend/product', array('product_url' => $product->url), true);
                $this->response['raw'] = $this->workupData($product->getData());
            }
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

    public function update($data)
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            return;
        }
        $fields = array('name');
        $update = array();
        foreach ($data as $name => $value) {
            if (in_array($name, $fields) !== false) {
                $update[$name] = $value;
            }
        }
        if ($update) {
            $product_model = new shopProductModel();
            $product_model->updateById($id, $update);
        }
    }

    public function workupData($data)
    {
        $currency = $data['currency'] ? $data['currency'] : $this->getConfig()->getCurrency();
        foreach ($data['skus'] as & $sku) {
            $sku['price_str'] = wa_currency($sku['price'], $currency);
            $sku['stock_icon'] = array();
            $sku['stock_icon'][0] = shopHelper::getStockCountIcon($sku['count']);
            if (!empty($sku['stock'])) {
                foreach ($sku['stock'] as $stock_id => $count) {
                    $sku['stock_icon'][$stock_id] = shopHelper::getStockCountIcon($count, $stock_id);
                }
            }
        }
        unset($sku);
        return $data;
    }
}
