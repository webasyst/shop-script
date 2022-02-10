<?php

class shopTransferProductsModel extends waModel
{
    protected $table = 'shop_transfer_products';

    public function attach($transfer_id, $data)
    {
        $transfer_id = (int) $transfer_id;
        if (!$transfer_id) {
            return false;
        }

        $items = array();
        foreach ($data as $item) {
            $sku_id = (int) ifset($item['sku_id'], 0);
            $count = (double) ifset($item['count'], 1);
            if ($sku_id > 0 && $count > 0) {
                $item['product_id'] = '';
                $item['sku_id'] = $sku_id;
                $item['transfer_id'] = $transfer_id;
                $item['count'] = $count;
                $items[$sku_id] = $item;
            }
        }

        if (!$items) {
            return false;
        }

        $sku_id_product_id_map = $this->query("
            SELECT id, product_id 
            FROM `shop_product_skus` 
            WHERE id IN(:0)",
            array(array_keys($items))
        )->fetchAll('id', true);

        foreach ($items as $sku_id => &$item) {
            $product_id = ifset($sku_id_product_id_map[$sku_id], 0);
            if ($product_id <= 0) {
                unset($items[$sku_id]);
                continue;
            }
            $item['product_id'] = $product_id;
        }
        unset($item);

        $this->multipleInsert(array_values($items));

        return $items;
    }

    public function getByTransfer($id)
    {
        $ids = array_map('intval', (array) $id);
        $items = array_fill_keys($ids, array());
        foreach ($this->getByField('transfer_id', $ids, true) as $item) {
            $items[$item['transfer_id']] = ifset($items[$item['transfer_id']], array());
            $items[$item['transfer_id']][$item['sku_id']] = $item;
        }
        if (is_array($id)) {
            return $items;
        }
        return $items[(int) $id];
    }
}