<?php

class shopMigratePluginWbProductMapModel extends shopMigratePluginWbModel
{
    protected $table = 'shop_migrate_wb_product_map';

    public function link($group_id, $imt_id, $nm_id, $shop_product_id)
    {
        $now = date('Y-m-d H:i:s');
        $this->multipleInsert(array(array(
            'group_id'        => (int) $group_id,
            'imt_id'          => (int) $imt_id,
            'nm_id'           => (int) $nm_id,
            'shop_product_id' => (int) $shop_product_id,
            'created_at'      => $now,
            'updated_at'      => $now,
        )), array('imt_id', 'nm_id', 'shop_product_id', 'updated_at'));
    }

    public function getByGroupId($group_id)
    {
        return $this->getByField('group_id', (int) $group_id);
    }

    public function getByShopProductId($shop_product_id)
    {
        return $this->getByField('shop_product_id', (int) $shop_product_id, true);
    }

    public function countCompletedForSnapshot($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id <= 0) {
            return 0;
        }

        $cards = (new shopMigratePluginWbCardsModel())->getTableName();
        $maps = $this->getTableName();
        $products = (new shopProductModel())->getTableName();
        $group_id = 'IF(c.imt_id > 0, c.imt_id * 2, c.nm_id * 2 + 1)';

        return (int) $this->query(
            "SELECT COUNT(DISTINCT m.group_id)
             FROM {$cards} c
             INNER JOIN {$maps} m ON m.group_id = {$group_id}
                AND m.completed_at IS NOT NULL
             INNER JOIN {$products} p ON p.id = m.shop_product_id
             WHERE c.snapshot_id = i:snapshot_id",
            array('snapshot_id' => $snapshot_id)
        )->fetchField();
    }

    public function findNextCompletedGroupIdForSnapshot($snapshot_id, $after_group_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id <= 0) {
            return 0;
        }

        $cards = (new shopMigratePluginWbCardsModel())->getTableName();
        $maps = $this->getTableName();
        $products = (new shopProductModel())->getTableName();
        $group_id = 'IF(c.imt_id > 0, c.imt_id * 2, c.nm_id * 2 + 1)';

        return (int) $this->query(
            "SELECT m.group_id
             FROM {$cards} c
             INNER JOIN {$maps} m ON m.group_id = {$group_id}
                AND m.completed_at IS NOT NULL
             INNER JOIN {$products} p ON p.id = m.shop_product_id
             WHERE c.snapshot_id = i:snapshot_id
               AND m.group_id > i:after_id
             GROUP BY m.group_id
             ORDER BY m.group_id
             LIMIT 1",
            array(
                'snapshot_id' => $snapshot_id,
                'after_id'    => max(0, (int) $after_group_id),
            )
        )->fetchField();
    }

    public function markCompleted($group_id)
    {
        $this->updateByField('group_id', (int) $group_id, array(
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ));
        $mapping = $this->getByGroupId($group_id);
        return $mapping && !empty($mapping['completed_at']);
    }
}
