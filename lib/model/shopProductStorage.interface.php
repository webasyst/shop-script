<?php

interface shopProductStorageInterface
{
    public function getData(shopProduct $product);

    public function setData(shopProduct $product, $data);

    /**
     * @param int[] $product_ids
     * @return bool
     */
    public function deleteByProducts(array $product_ids);
}