<?php

class shopMigratePluginOzonProductMapModel extends shopMigratePluginOzonModel
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
        $sql = "
            SELECT m.*
            FROM {$this->table} m
            JOIN shop_product p ON p.id = m.shop_product_id
            WHERE m.offer_id = s:offer_id
            LIMIT 1
        ";
        return $this->query($sql, array('offer_id' => (string) $offer_id))->fetchAssoc();
    }

    public function getByShopProductId($product_id)
    {
        return $this->getByField('shop_product_id', (int) $product_id, true);
    }

    public function getMappedOfferIdsBySnapshot($snapshot_id)
    {
        $sql = "
            SELECT m.offer_id
            FROM {$this->table} m
            JOIN shop_migrate_ozon_products p ON p.offer_id = m.offer_id
            JOIN shop_product shop_p ON shop_p.id = m.shop_product_id
            WHERE p.snapshot_id = i:snapshot_id
        ";
        return $this->query($sql, array('snapshot_id' => (int) $snapshot_id))->fetchAll(null, true);
    }

    public function countMappedProductsBySnapshot($snapshot_id)
    {
        $sql = "
            SELECT COUNT(*)
            FROM {$this->table} m
            JOIN shop_migrate_ozon_products p ON p.offer_id = m.offer_id
            JOIN shop_product shop_p ON shop_p.id = m.shop_product_id
            WHERE p.snapshot_id = i:snapshot_id
        ";
        return (int) $this->query($sql, array('snapshot_id' => (int) $snapshot_id))->fetchField();
    }

    public function cleanupMissingShopEntitiesForSnapshot($snapshot_id)
    {
        $params = array('snapshot_id' => (int) $snapshot_id);
        $delete_sql = "
            DELETE m
            FROM {$this->table} m
            JOIN shop_migrate_ozon_products ozon_p
                ON ozon_p.offer_id = m.offer_id
            LEFT JOIN shop_product shop_p ON shop_p.id = m.shop_product_id
            WHERE ozon_p.snapshot_id = i:snapshot_id
                AND shop_p.id IS NULL
        ";
        $deleted = (int) $this->exec($delete_sql, $params);

        $sku_sql = "
            UPDATE {$this->table} m
            JOIN shop_migrate_ozon_products ozon_p
                ON ozon_p.offer_id = m.offer_id
            JOIN shop_product shop_p ON shop_p.id = m.shop_product_id
            LEFT JOIN shop_product_skus sku
                ON sku.id = m.shop_sku_id
                AND sku.product_id = m.shop_product_id
            SET m.shop_sku_id = NULL, m.updated_at = s:updated_at
            WHERE ozon_p.snapshot_id = i:snapshot_id
                AND m.shop_sku_id IS NOT NULL
                AND sku.id IS NULL
        ";
        $cleared_skus = (int) $this->exec($sku_sql, array(
            'snapshot_id' => (int) $snapshot_id,
            'updated_at'  => date('Y-m-d H:i:s'),
        ));

        return array(
            'deleted_products' => $deleted,
            'cleared_skus'     => $cleared_skus,
            'total'            => $deleted + $cleared_skus,
        );
    }
}
