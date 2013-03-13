<?php

class shopTaxZipCodesModel extends waModel
{
    protected $table = 'shop_tax_zip_codes';

    public function getByTax($tax_id)
    {
        if (!$tax_id) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE tax_id IN (:tax_id) ORDER BY `sort`";
        return $this->query($sql, array('tax_id' => $tax_id))->fetchAll();
    }

    public function getByZip($zip, $addr_type, $tax_ids)
    {
        $sql = "SELECT t.id, tzc.tax_value
                FROM shop_tax AS t
                    JOIN shop_tax_zip_codes AS tzc
                        ON tzc.tax_id=t.id
                WHERE t.id IN (:ids)
                    AND t.address_type = :type
                    AND :zip LIKE tzc.zip_expr
                ORDER BY tzc.sort DESC";
        return $this->query($sql, array(
            'ids' => $tax_ids,
            'type' => $addr_type,
            'zip' => $zip,
        ))->fetchAll('id', true);
    }

}

