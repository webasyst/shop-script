<?php

class shopPromoOrdersModel extends waModel
{
    protected $table = 'shop_promo_orders';

    /**
     * @param int $order_id
     * @param array $promo_ids
     * @return bool
     */
    public function relateOrderWithPromos($order_id, $promo_ids)
    {
        $order_id = (int)$order_id;
        if (empty($order_id) || empty($promo_ids) || !is_array($promo_ids)) {
            return false;
        }

        $values = array();
        foreach ($promo_ids as $promo_id) {
            $values[] = "('{$order_id}', '{$this->escape($promo_id, 'int')}')";
        }

        $sql = "INSERT IGNORE INTO {$this->table} (order_id, promo_id) VALUES ".join(',', $values);

        return $this->exec($sql);
    }

    public function getBasicStats($ids)
    {
        if (!$ids) {
            return [];
        }

        $result = [];
        foreach($ids as $id) {
            $result[$id] = [
                'promo_id' => $id,
                'orders_count' => 0,
                'sales' => 0,
                'profit' => 0,
                'expenses' => 0,
                'paid_for_itself' => true,
                'roi' => null,
            ];
        }

        //
        // Sales excluding taxes and shipping costs
        //
        $sql = "
            SELECT po.promo_id, SUM(o.total*o.rate) AS sales, SUM((o.total - o.tax - o.shipping)*o.rate) AS profit, COUNT(*) AS orders_count
            FROM shop_promo_orders AS po
                JOIN shop_order AS o
                    ON po.order_id=o.id
            WHERE po.promo_id IN (?)
                AND o.paid_date IS NOT NULL
            GROUP BY po.promo_id
        ";
        foreach($this->query($sql, [$ids]) as $row) {
            $id = $row['promo_id'];
            $result[$id]['orders_count'] = (int) $row['orders_count'];
            $result[$id]['profit'] = (float) $row['profit'];
            $result[$id]['sales'] = (float) $row['sales'];
        }

        //
        // Product purchase costs
        //
        $sql = "
            SELECT po.promo_id, SUM(IFNULL(oi.purchase_price, 0)*oi.quantity*o.rate) AS costs
            FROM shop_promo_orders AS po
                JOIN shop_order AS o
                    ON po.order_id=o.id
                JOIN shop_order_items AS oi
                    ON po.order_id=oi.order_id
            WHERE po.promo_id IN (?)
            GROUP BY po.promo_id
        ";
        foreach($this->query($sql, [$ids]) as $row) {
            $result[$row['promo_id']]['profit'] -= $row['costs'];
        }

        //
        // Marketing expenses
        //
        $sql = "
            SELECT e.name AS promo_id, SUM(e.amount) AS expenses
            FROM shop_expense AS e
            WHERE e.type='promo'
                AND e.name IN (?)
            GROUP BY e.name
        ";
        foreach($this->query($sql, [$ids]) as $row) {
            $id = $row['promo_id'];
            $result[$id]['expenses'] = (float) $row['expenses'];
            if ($row['expenses'] > 0) {
                $result[$id]['roi'] = max(0, $result[$id]['profit']) * 100 / $row['expenses'];
                $result[$id]['roi'] = round($result[$id]['roi']);
                $result[$id]['paid_for_itself'] = $result[$id]['roi'] >= 100;
            }
        }

        return $result;
    }

    /**
     * Updating orders placed with a coupon
     *
     * @param $promo_id
     * @param $orders_ids
     */
    public function refreshPromoOrders($promo_id, $orders_ids)
    {
        if (!$orders_ids) {
            return;
        }
        $sql = "INSERT IGNORE INTO {$this->table}
        (SELECT order_id, ? AS promo_id FROM shop_order_params WHERE name = 'coupon_id' AND value IN (?))";
        $this->query($sql, [$promo_id, $orders_ids]);
    }
}
