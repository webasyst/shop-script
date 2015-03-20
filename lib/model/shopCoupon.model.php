<?php

class shopCouponModel extends waModel
{
    //
    // Clarification about table columns.
    // * `type` can be a currency iso-3 code, or '%', or '$FS'. Latter means "Free Shipping".
    // * `limit` is max number of uses for the code. NULL means unlimited use.
    // * `used` is how many times the code has already been used.
    //   Therefore, (`limit` IS NOT NULL) AND (`used` >= `limit`) means the code is inactive.
    // * `expire_datetime` is the moment when coupon stops working. NULL means no time limit.
    //   Therefore, NOW() > `expire_datetime` means the code is inactive.
    //
    protected $table = 'shop_coupon';

    public function useOne($id)
    {
        $sql = "UPDATE {$this->table} SET used = used + 1 WHERE id = :id";
        $this->exec($sql, array('id' => $id));
    }

    public function countActive()
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE ((`limit` IS NULL) OR (`used` < `limit`))
                    AND ((`expire_datetime` IS NULL) OR (`expire_datetime` > ?))";
        return (int) $this->query($sql, date('Y-m-d H:i:s'))->fetchField();
    }

    public function delete($id)
    {
        $coupon = $this->getById($id);
        if ($coupon) {
            $opm = new shopOrderParamsModel();
            $order_ids = array_keys($opm->getByField(array(
                'name' => 'coupon_id', 'value' => $id
            ), 'order_id'));
            if ($order_ids) {
                $opm->set($order_ids, array(
                    'coupon_code' => $coupon['code']
                ), false);
            }
            $this->deleteById($id);
        }
    }

    public function getById($value, $with_empty_rows = false) {
        $res = parent::getById($value);
        if ($with_empty_rows && is_array($value)) {
            $empty = $this->getEmptyRow();
            $all = array();
            foreach ($value as $v) {
                $all[$v] = ifset($res[$v], $empty);
                $all[$v][$this->id] = $v;
            }
            return $all;
        }
        return $res;
    }

}

