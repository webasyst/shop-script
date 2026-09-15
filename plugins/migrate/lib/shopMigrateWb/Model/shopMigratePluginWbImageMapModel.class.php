<?php

class shopMigratePluginWbImageMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_image_map';

    public function link($nm_id, $source_key, $shop_product_id, $shop_image_id, $actual_max_dimension)
    {
        $this->multipleInsert(array(array(
            'nm_id'           => (int) $nm_id,
            'source_key'      => (string) $source_key,
            'shop_product_id' => (int) $shop_product_id,
            'shop_image_id'   => (int) $shop_image_id,
            'max_dimension'   => (int) $actual_max_dimension,
            'created_at'      => date('Y-m-d H:i:s'),
        )), array('nm_id', 'shop_image_id', 'max_dimension'));
    }

    public function getByProduct($shop_product_id)
    {
        return $this->getByField('shop_product_id', (int) $shop_product_id, true);
    }
}
