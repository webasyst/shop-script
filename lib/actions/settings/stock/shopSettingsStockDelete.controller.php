<?php

class shopSettingsStockDeleteController extends waJsonController
{
    public function execute()
    {
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
                $this->errors[] = _w("Unknow destination stock");
                return;
            }
        }
        if (!$model->delete($id, $dst_stock)) {
            $this->errors[] = _w("Error when deleting");
        }
    }
}