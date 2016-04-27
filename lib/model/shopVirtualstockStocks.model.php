<?php
class shopVirtualstockStocksModel extends waModel
{
    protected $table = 'shop_virtualstock_stocks';

    public function set($virtualstock_id, $stock_ids)
    {
        if (!$virtualstock_id || !$stock_ids) {
            return;
        }

        $i = 0;
        $data = array();
        foreach($stock_ids as $stock_id) {
            $data[$stock_id] = array(
                'stock_id' => $stock_id,
                'virtualstock_id' => $virtualstock_id,
                'sort' => $i++,
            );
        }

        $this->deleteByField('virtualstock_id', $virtualstock_id);
        $this->multipleInsert(array_values($data));
    }
}
