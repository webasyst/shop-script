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

    const PAGES_STEP = 50;

    public function useOne($id)
    {
        $sql = "UPDATE {$this->table} SET used = used + 1 WHERE id = :id";
        $this->exec($sql, array('id' => $id));
    }

    public function setUnused($order_id)
    {
        $order_params_model = new shopOrderParamsModel();
        $row = $order_params_model->getByField(array('order_id' => $order_id, 'name' => 'coupon_id'));
        if (!empty($row['value'])) {
            $old_coupon = $this->getById($row['value']);
            if (!empty($old_coupon['id'])) {
                $sql = "UPDATE {$this->table} SET used = used - 1 WHERE id = :id AND used > 0";
                $this->exec($sql, array('id' => $old_coupon['id']));
            }
        }
    }

    public function countActive()
    {
        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->table}
WHERE
((`limit` IS NULL ) OR (`used` < `limit`))
AND
((`expire_datetime` IS NULL ) OR (`expire_datetime` > ?))
SQL;
        return (int)$this->query($sql, date('Y-m-d H:i:s'))->fetchField();
    }

    public function delete($id)
    {
        $coupon = $this->getById($id);
        if ($coupon) {
            $opm = new shopOrderParamsModel();
            $order_ids = array_keys($opm->getByField(array(
                'name'  => 'coupon_id',
                'value' => $id,
            ), 'order_id'));
            if ($order_ids) {
                $opm->set($order_ids, array(
                    'coupon_code' => $coupon['code'],
                ), false);
            }
            $this->deleteById($id);
        }
    }

    public function getById($value, $with_empty_rows = false)
    {
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

    public function getActiveCoupons($timestamp = null)
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE
((`limit` IS NULL ) OR (`used` < `limit`))
AND
((`expire_datetime` IS NULL ) OR (`expire_datetime` > ?))
SQL;
        if ($timestamp) {
            $datetime = date('Y-m-d H:i:s', $timestamp);
        } else {
            $datetime = date('Y-m-d H:i:s');
        }
        return $this->query($sql, $datetime)->fetchAll($this->id);
    }

    public static function isEnabled($coupon)
    {
        $result = $coupon['limit'] === null || $coupon['limit'] > $coupon['used'];
        return $result && ($coupon['expire_datetime'] === null || strtotime($coupon['expire_datetime']) > time());
    }

    public static function formatValue($coupon, $curr = null)
    {
        static $currencies = null;
        if ($currencies === null) {
            if ($curr) {
                $currencies = $curr;
            } else {
                $curm = new shopCurrencyModel();
                $currencies = $curm->getAll('code');
            }
        }

        if ($coupon['type'] == '$FS') {
            return _w('Free shipping');
        } elseif ($coupon['type'] === '%') {
            return waCurrency::format('%0', $coupon['value'], 'USD').'%';
        } elseif (!empty($currencies[$coupon['type']])) {
            return waCurrency::format('%0{s}', $coupon['value'], $coupon['type']);
        } else {
            // Coupon of unknown type. Possibly from a plugin?..
            return '';
        }
    }

    public function getPageNumber($coupon_id, $coupon_name)
    {
        $coupon_id = (int)$coupon_id;
        $pages_count = $this->select('COUNT(*) AS `count`')
            ->where("`id` >= {$coupon_id} AND `code` LIKE ?", ['%' . $coupon_name . '%'])
            ->order('id')->fetchField('count');
        $page_number = ceil($pages_count / self::PAGES_STEP);
        if ($page_number < 1) {
            $page_number = 1;
        }

        return (int)$page_number;
    }
}
