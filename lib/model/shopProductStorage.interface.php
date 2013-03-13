<?php

interface shopProductStorageInterface
{
    public function getData(shopProduct $product);

    public function setData(shopProduct $product, $data);
}