<?php

class shopProductRelatedModel extends waModel
{
    protected $table = 'shop_product_related';

    public function getAllRelated($product_id)
    {
        $sql = "SELECT pr.*, p.id, p.name, p.price, p.currency FROM ".$this->table." pr
                JOIN shop_product p ON pr.related_product_id = p.id
                WHERE pr.product_id = i:id";
        $data = $this->query($sql, array('id' => $product_id));
        $result = array();
        foreach ($data as $row) {
            $result[$row['type']][] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'currency' => $row['currency']
            );
        }
        return $result;
    }

    /**
     * @param $product_id
     * @param $type
     * @param $product_ids
     */
    public function deleteAllProductsExcept($product_id, $type, $product_ids)
    {
        if (!empty($product_ids)) {
            $sql = "DELETE FROM {$this->getTableName()} WHERE `product_id` = ? AND `type` = ? AND `related_product_id` NOT IN (?)";
            $this->exec($sql, [$product_id, $type, $product_ids]);
        }
    }
}