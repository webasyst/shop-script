<?php

class shopMigrateOzonProductsModel extends shopMigrateOzonModel
{
    protected $table = 'shop_migrate_ozon_products';

    public function addBatch($snapshot_id, array $products)
    {
        if (!$products) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array();
        foreach ($products as $product) {
            $rows[] = array(
                'snapshot_id'              => (int) $snapshot_id,
                'product_id'               => (int) ifset($product['product_id']),
                'offer_id'                 => (string) ifset($product['offer_id']),
                'description_category_id'  => (int) ifset($product['description_category_id']),
                'type_id'                  => (int) ifset($product['type_id']),
                'name'                     => (string) ifset($product['name'], ''),
                'flags'                    => json_encode(ifset($product['flags'], array())),
                'details'                  => isset($product['details']) ? json_encode($product['details']) : null,
                'created_at'               => $now,
                'updated_at'               => $now,
            );
        }
        $this->multipleInsert($rows, array('offer_id', 'name', 'flags', 'details', 'updated_at'));
    }

    public function updateDetails($snapshot_id, $product_id, array $details)
    {
        $data = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'details'    => json_encode($details),
        );
        if (!empty($details['name'])) {
            $data['name'] = (string) $details['name'];
        }
        if (isset($details['description_category_id'])) {
            $data['description_category_id'] = (int) $details['description_category_id'];
        }
        if (isset($details['type_id'])) {
            $data['type_id'] = (int) $details['type_id'];
        }
        $this->updateByField(
            array(
                'snapshot_id' => (int) $snapshot_id,
                'product_id'  => (int) $product_id,
            ),
            $data
        );
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll('product_id');
    }

    public function getIds($snapshot_id)
    {
        return $this->select('product_id')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll(null, true);
    }
}
