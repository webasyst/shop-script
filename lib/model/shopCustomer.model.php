<?php
/**
 * Note: total_spent is stored in shop default currency.
 */
class shopCustomerModel extends waModel
{
    protected $table = 'shop_customer';
    protected $id = 'contact_id';

    public function createFromContact($contact_id, $customer=array())
    {
        if ($this->getById($contact_id)) {
            return;
        }

        $customer['contact_id'] = $contact_id;
        $this->insert($customer);
    }

    public function updateFromNewOrder($customer_id, $order_id, $source=null)
    {
        $customer = $this->getById($customer_id);
        if ($customer) {
            $sql = "UPDATE {$this->table}
                    SET number_of_orders = number_of_orders + 1,
                        last_order_id = i:oid
                    WHERE contact_id = i:cid";
            $this->exec($sql, array(
                'oid' => $order_id,
                'cid' => $customer_id,
            ));
        } else {
            $this->insert(array(
                'contact_id' => $customer_id,
                'last_order_id' => $order_id,
                'number_of_orders' => 1,
                'source' => ifempty($source),
            ));
        }
    }

    public function getList($category_id, $search, $start=0, $limit=50, $order='name')
    {
        $start = (int) $start;
        $limit = (int) $limit;

        $join = array();
        $where = array();
        $select = array(
            'sc.*, c.*, o.create_datetime AS last_order_datetime'
        );

        if ($category_id) {
            $join[] = 'JOIN wa_contact_categories AS cc ON cc.contact_id=c.id';
            $where[] = 'cc.category_id='.((int)$category_id);
        }
        if ($search) {
            // When input looks like a phone, look up by phone.
            // Otherwise, loop up by name and email.
            if (preg_match('~^[0-9\s\-\(\)]+$~', $search)) {
                $search_escaped = $this->escape(preg_replace('~[^0-9]~', '', $search), 'like');
                if ($search_escaped) {
                    $join[] = "LEFT JOIN wa_contact_data AS p ON p.contact_id=c.id AND p.field='phone'";
                    $where[] = "p.value LIKE '%{$search_escaped}%'";
                    $select[] = 'p.value AS phone';
                }
            } else {
                $search_escaped = $this->escape($search, 'like');
                $join[] = 'LEFT JOIN wa_contact_emails AS e ON e.contact_id=c.id';
                $where[] = "CONCAT(c.name, ' ', IFNULL(e.email, '')) LIKE '%{$search_escaped}%'";
            }
        }

        if ($where) {
            $where = 'WHERE ('.implode(') AND (', $where).')';
        } else {
            $where = '';
        }

        if ($join) {
            $join = implode("\n", $join);
        } else {
            $join = '';
        }

        $possible_orders = array(
            'name' => 'c.name',
            '!name' => 'c.name DESC',
            'total_spent' => 'sc.total_spent',
            '!total_spent' => 'sc.total_spent DESC',
            'affiliate_bonus' => 'sc.affiliate_bonus',
            '!affiliate_bonus' => 'sc.affiliate_bonus DESC',
            'number_of_orders' => 'sc.number_of_orders',
            '!number_of_orders' => 'sc.number_of_orders DESC',
            'last_order' => 'sc.last_order_id',
            '!last_order' => 'sc.last_order_id DESC',
            'registered' => 'c.create_datetime',
            '!registered' => 'c.create_datetime DESC',
        );

        if (!$order || empty($possible_orders[$order])) {
            $order = key($possible_orders);
        }
        $order = 'ORDER BY '.$possible_orders[$order];

        // Fetch basic contact and customer info
        $sql = "SELECT SQL_CALC_FOUND_ROWS ".implode(', ', $select)."
                FROM wa_contact AS c
                    JOIN shop_customer AS sc
                        ON c.id=sc.contact_id
                    LEFT JOIN shop_order AS o
                        ON o.id=sc.last_order_id
                    $join
                $where
                GROUP BY c.id
                $order
                LIMIT {$start}, {$limit}";

        $customers = $this->query($sql)->fetchAll('id');

        $total = $this->query('SELECT FOUND_ROWS()')->fetchField();

        // get emails
        $ids = array_keys($customers);
        if ($ids) {
            foreach ($this->query("
                SELECT contact_id, email, MIN(sort)
                FROM `wa_contact_emails`
                WHERE contact_id IN (".implode(',', $ids).")
                GROUP BY contact_id") as $item)
            {
                $customers[$item['contact_id']]['email'] = $item['email'];
            }
        }

        if (!$customers) {
            return array(array(), 0);
        }

        // Format names
        foreach($customers as &$c) {
            $c['name'] = waContactNameField::formatName($c);
            $c['address'] = array();
        }
        unset($c);

        // Fetch addresses
        $sql = "SELECT *
                FROM wa_contact_data
                WHERE contact_id IN (i:ids)
                    AND sort=0
                    AND field LIKE 'address:%'
                ORDER BY contact_id";
        foreach ($this->query($sql, array('ids' => array_keys($customers))) as $row) {
            $customers[$row['contact_id']]['address'][substr($row['field'], 8)] = $row['value'];
        }

        return array($customers, $total);
    }

    /**
     * @param null $category_id
     * @return array|int
     */
    public function getCategoryCounts($category_id = null)
    {
        $category_ids = array_map('intval', (array) $category_id);
        if (!$category_ids && $category_id !== null) {
            return array();
        }

        $where = "";
        if ($category_id !== null) {
            $where = "WHERE cc.category_id IN (i:0)";
        }

        $sql = "SELECT cc.category_id, count(*) AS cnt
                FROM wa_contact_categories AS cc
                JOIN wa_contact wc ON wc.id = cc.contact_id
                {$where}
                GROUP BY cc.category_id";

        $res = $this->query($sql, array($category_ids))->fetchAll('category_id', true);
        if ($category_id === null) {
            return $res;
        }

        foreach ($category_ids as $id) {
            $res[$id] = ifset($res[$id], 0);
        }

        return is_array($category_id) ? $res : $res[(int) $category_id];
    }

    public function getAllCoupons()
    {
        $coupons_ids = $this->query("SELECT DISTINCT sop.value FROM `{$this->table}` sc
        JOIN `shop_order` so ON so.contact_id = sc.contact_id
        JOIN `shop_order_params` sop ON sop.order_id = so.id AND name = 'coupon_id'
        WHERE sop.value <> '' AND sop.value <> '0'")->fetchAll(null, true);
        $cm = new shopCouponModel();

        return $cm->getById($coupons_ids, true);
    }

    public function recalcTotalSpent($contact_id)
    {
        $om = new shopOrderModel();
        $this->updateById($contact_id, array(
            'total_spent' => $om->getTotalSalesByContact($contact_id)
        ));
    }
}

