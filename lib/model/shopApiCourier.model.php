<?php
class shopApiCourierModel extends waModel
{
    protected $table = 'shop_api_courier';

    public function getAll($key = null, $normalize = false, $active_only = false)
    {
        $active_only_sql = '';
        if ($active_only) {
            $active_only_sql = "WHERE enabled=1";
        }
        $sql = "SELECT cr.*, cn.photo
                FROM ".$this->table." AS cr
                    LEFT JOIN wa_contact AS cn
                        ON cr.contact_id = cn.id
                {$active_only_sql}
                ORDER BY cr.name";
        return $this->query($sql)->fetchAll($key, $normalize);
    }

    public function getEnabled()
    {
        return $this->getAll('id', false, true);
    }

    public function getByStorefront($storefront, $all_couriers = null)
    {
        $storefront = rtrim($storefront, '/');

        // courier_ids bound to specific storefront
        $courier_ids = array();
        $sql = "SELECT courier_id, storefront FROM shop_api_courier_storefronts WHERE storefront LIKE ?";
        foreach($this->query($sql, array($storefront.'%')) as $row) {
            if ($storefront == rtrim($row['storefront'], '/')) {
                $courier_ids[$row['courier_id']] = true;
            }
        }

        // Filter couriers with access to all storefronts OR access to specific $storefront
        $couriers = is_array($all_couriers) ? $all_couriers : $this->getAll('id');
        foreach($couriers as $courier_id => $courier) {
            if (!$courier['all_storefronts'] && !isset($courier_ids[$courier_id])) {
                unset($couriers[$courier_id]);
            }
        }

        return $couriers;
    }

    public static function generateAuthCode()
    {
        $alphabet = "1234567890";

        $hash = 0;
        $result = '';
        while(strlen($result) < 7) {
            $digit = intval($alphabet[mt_rand(0, strlen($alphabet)-1)]);
            $hash = (($hash << 1) - $hash + $digit) & 0xff;
            $result .= $digit;
        }
        $result .= $hash % 10;
        return substr($result, 0, 4).'-'.substr($result, 4);
    }

    public function incrOrdersProcessed($courier_id)
    {
        if ($courier_id) {
            $sql = "UPDATE {$this->table}
                    SET orders_processed = orders_processed + 1
                    WHERE id IN (?)";
            $this->exec($sql, array($courier_id));
        }
    }

    public function getByToken($token)
    {
        return $this->getByField('api_token', $token);
    }

    public function getOrderCounts(&$couriers)
    {
        if (!$couriers) {
            return;
        }

        $sql = "SELECT op.value, count(*)
                FROM shop_order_params AS op
                    JOIN shop_order AS o
                        ON o.id=op.order_id
                WHERE op.name='courier_id'
                    AND op.value IN (?)
                    AND o.state_id NOT IN ('completed', 'refunded', 'deleted')
                GROUP BY op.value";
        $counts = $this->query($sql, array(array_keys($couriers)))->fetchAll('value', true);
        foreach($couriers as &$c) {
            $c['count'] = ifset($counts[$c['id']], 0);
        }
        unset($c);
    }
}
