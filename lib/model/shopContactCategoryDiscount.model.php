<?php

class shopContactCategoryDiscountModel extends waModel
{
    protected $table = 'shop_contact_category_discount';
    protected $id = 'category_id';

    public function save($values)
    {
        $this->exec("DELETE FROM {$this->table}");
        if (!$values || !is_array($values)) {
            return;
        }

        $rows = array();
        foreach($values as $cid => $discount) {
            if ($discount) {
                $rows[] = array(
                    'category_id' => $cid,
                    'discount' => (float) str_replace(',', '.', $discount),
                );
            }
        }

        if ($rows) {
            $this->multipleInsert($rows);
        }
    }

    public function getDiscount($category_id)
    {
        if ( ( $row = $this->getById($category_id))) {
            return (float) $row['discount'];
        }
        return 0;
    }

    public function getByContact($contact_id)
    {
        $sql = "SELECT MAX(d.discount)
                FROM {$this->table} AS d
                    JOIN wa_contact_categories AS cc
                        ON cc.category_id=d.category_id
                WHERE cc.contact_id=:id";
        return (float) $this->query($sql, array('id' => $contact_id))->fetchField();
    }
}

