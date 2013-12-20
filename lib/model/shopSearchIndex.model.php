<?php

class shopSearchIndexModel extends waModel
{
    protected $table = 'shop_search_index';

    /**
     * @param array $product_ids
     */
    public function deleteByProducts(array $product_ids)
    {
        return $this->deleteByField('product_id', $product_ids);
    }
}