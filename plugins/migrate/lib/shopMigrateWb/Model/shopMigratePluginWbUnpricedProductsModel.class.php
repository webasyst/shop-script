<?php

class shopMigratePluginWbUnpricedProductsModel extends shopMigratePluginWbModel
{
    const COLLECTION_HASH = 'migrate_wb_unpriced';

    protected $table = 'shop_migrate_wb_unpriced_products';
    protected $id = array('run_id', 'shop_product_id');

    public function record($run_id, $snapshot_id, $product_id)
    {
        // Finalization can be replayed after interruption: record each product once.
        $this->insert(array(
            'run_id' => (int) $run_id,
            'snapshot_id' => (int) $snapshot_id,
            'shop_product_id' => (int) $product_id,
        ), 2);
    }

    public function countForRun($run_id)
    {
        $products = (new shopProductModel())->getTableName();
        return (int) $this->query(
            "SELECT COUNT(*) FROM {$this->table} u
             INNER JOIN {$products} p ON p.id = u.shop_product_id
             WHERE u.run_id = i:run_id",
            array('run_id' => (int) $run_id)
        )->fetchField();
    }

    public function applyToCollection(shopProductsCollection $collection, $run_id)
    {
        $collection->addWhere('p.id IN (SELECT shop_product_id FROM '.$this->table.' WHERE run_id = '.(int) $run_id.')');
    }
}
