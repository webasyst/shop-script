<?php

class shopMigratePluginWbSkuMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_sku_map';

    public function link($nm_id, $chrt_id, $shop_product_id, $shop_sku_id)
    {
        $now = date('Y-m-d H:i:s');
        $this->multipleInsert(array(array(
            'nm_id'           => (int) $nm_id,
            'chrt_id'         => (int) $chrt_id,
            'shop_product_id' => (int) $shop_product_id,
            'shop_sku_id'     => (int) $shop_sku_id,
            'created_at'      => $now,
            'updated_at'      => $now,
        )), array('nm_id', 'shop_product_id', 'shop_sku_id', 'updated_at'));
    }

    public function getByChrtId($chrt_id)
    {
        return $this->getByField('chrt_id', (int) $chrt_id);
    }

    public function getByNmId($nm_id)
    {
        return $this->getByField('nm_id', (int) $nm_id, true);
    }
}
