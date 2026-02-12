<?php

class shopMigrateOzonProductMapModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_product_map';

    public function linkOffer($offer_id, $shop_product_id, $shop_sku_id = null, $ozon_product_id = null)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'offer_id'        => (string) $offer_id,
            'shop_product_id' => (int) $shop_product_id,
            'shop_sku_id'     => $shop_sku_id !== null ? (int) $shop_sku_id : null,
            'ozon_product_id' => $ozon_product_id !== null ? (int) $ozon_product_id : null,
            'created_at'      => $now,
            'updated_at'      => $now,
        );
        $this->multipleInsert(array($row), array('shop_product_id', 'shop_sku_id', 'ozon_product_id', 'updated_at'));
    }

    public function getByOffer($offer_id)
    {
        return $this->getByField('offer_id', (string) $offer_id);
    }

    public function getByShopProductId($product_id)
    {
        return $this->getByField('shop_product_id', (int) $product_id, true);
    }
}
