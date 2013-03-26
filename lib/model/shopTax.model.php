<?php

class shopTaxModel extends waModel
{
    protected $table = 'shop_tax';

    public function getByName($name)
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `name` LIKE s:0";
        return $this->query($sql, $name)->fetch();
    }
}
