<?php

class shopSettingsStockDeleteController extends waJsonController
{
    public function execute()
    {
        $vid = waRequest::request('vid', null, waRequest::TYPE_INT);
        if ($vid) {
            $virtualstock_model = new shopVirtualstockModel();
            $virtualstock_model->deleteById($vid);
            $virtualstock_stocks_model = new shopVirtualstockStocksModel();
            $virtualstock_stocks_model->deleteByField('virtualstock_id', $vid);
            return;
        }

        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$id) {
            $this->errors[] = _w("Unknown stock");
            return;
        }

        $model = new shopStockModel();
        $dst_stock = null;
        if (waRequest::post('delete_stock') == '1') {
            $dst_stock = waRequest::post('dst_stock', null, waRequest::TYPE_INT);
            if (!$dst_stock) {
                $this->errors[] = _w("Unknown destination stock");
                return;
            }
        }
        if (!$model->delete($id, $dst_stock)) {
            $this->errors[] = _w("Error when deleting");
        }
        if (!$this->errors) {
            $virtualstock_stocks_model = new shopVirtualstockStocksModel();
            $virtualstock_stocks_model->deleteByField('stock_id', $id);
        }
    }
}
