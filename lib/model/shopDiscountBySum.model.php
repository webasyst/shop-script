<?php
/**
 * Stores settings for discount types: by order total and by client's total spent.
 * `sum` field contains value in shop default currency.
 */
class shopDiscountBySumModel extends waModel
{
    protected $table = 'shop_discount_by_sum';

    public function getByType($type)
    {
        return $this->where('type=?', $type)->order('sum')->fetchAll();
    }

    public function getDiscount($type, $sum)
    {
        $sql = "SELECT discount FROM {$this->table} WHERE type=? AND sum<=? ORDER BY sum DESC LIMIT 1";
        return (float) $this->query($sql, $type, (float) $sum)->fetchField();
    }
}

