<?php
/**
 * Note: total_spent is stored in shop default currency.
 */
class shopCustomerModel extends waModel
{
    const ID_CODE_LENGTH = 12;
    const ID_CODE_MAX_ATTEMPTS = 128;

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

    public function createFromContacts(array $contact_ids)
    {
        if (!$contact_ids) {
            return;
        }
        $res = $this->exec("
            INSERT IGNORE INTO `{$this->table}` (contact_id)
            SELECT id FROM wa_contact
            WHERE id IN (?)
        ", [$contact_ids]);
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

    public function normalizeIdCode($id_code) {
        return trim((string)$id_code);
    }

    public function isValidIdCode($id_code) {
        return !$id_code || (bool)preg_match('~^[1-9][0-9]{' . (self::ID_CODE_LENGTH - 1) . '}$~', $id_code);
    }

    public function generateIdCode($contact_id, $attempt = 0) {
        $seed = $this->hashIdCodeSeed($contact_id . ':' . $attempt . ':shop-customer-id-code');
        $digits = '';

        for ($i = 0; $i < self::ID_CODE_LENGTH; $i++) {
            $seed = $this->nextIdCodeSeed($seed, $i);
            $digit = $seed % 10;

            if ($i === 0) {
                $digit = ($seed % 9) + 1;
            }

            $digits .= $digit;
        }

        return $digits;
    }

    public function getAvailableIdCode($contact_id, $preferred_id_code = null) {
        $preferred_id_code = $this->normalizeIdCode($preferred_id_code);
        if ($preferred_id_code !== '' && $this->isIdCodeAvailable($preferred_id_code, $contact_id)) {
            return $preferred_id_code;
        }

        for ($attempt = 0; $attempt < self::ID_CODE_MAX_ATTEMPTS; $attempt++) {
            $id_code = $this->generateIdCode($contact_id, $attempt);
            if ($this->isIdCodeAvailable($id_code, $contact_id)) {
                return $id_code;
            }
        }

        return null;
    }

    public function getAvailableIdCodeBatch(array $contact_ids): array 
    {
        // make sure contact ids are unique
        $contact_ids = array_keys(array_flip($contact_ids));

        $result_id_codes = [];
        for ($attempt = 0; $attempt < self::ID_CODE_MAX_ATTEMPTS; $attempt++) {
            if (!$contact_ids) {
                break;
            }

            // Generate a batch of codes
            $id_codes = [];
            $retry_contact_ids = [];
            foreach ($contact_ids as $contact_id) {
                $new_code = $this->generateIdCode($contact_id, $attempt);
                if (isset($id_codes[$new_code])) {
                    // two contacts in batch happened to have the same code
                    $retry_contact_ids[] = $contact_id;
                    continue;
                }
                $id_codes[$new_code] = $contact_id;
            }

            // Check if any of codes generated belong to a different contact saved in DB
            $sql = "SELECT contact_id, id_code FROM {$this->table} WHERE id_code IN (?)";
            $used_codes = $this->query($sql, [array_keys($id_codes)]);
            foreach ($used_codes as $row) {
                if ($row['contact_id'] != $id_codes[$row['id_code']]) {
                    $retry_contact_ids[] = $id_codes[$row['id_code']];
                    unset($id_codes[$row['id_code']]);
                }
            }
            foreach ($id_codes as $code => $contact_id) {
                $result_id_codes[$contact_id] = $code;
            }

            // Continue with contacts that have failed to generate unique code
            $contact_ids = $retry_contact_ids;
        }

        return $result_id_codes;
    }

    public function isIdCodeAvailable($id_code, $contact_id = null) {
        $where = "id_code = s:id_code";
        $params = [
            'id_code' => $id_code,
        ];

        if ($contact_id) {
            $where .= " AND contact_id <> i:contact_id";
            $params['contact_id'] = $contact_id;
        }

        return !$this->select('contact_id')->where($where, $params)->fetchField();
    }

    protected function hashIdCodeSeed($value) {
        $hash = 2166136261;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($value[$i]);
            $hash = ($hash * 16777619) & 0xffffffff;
        }

        return $hash;
    }

    protected function nextIdCodeSeed($seed, $step) {
        $seed = ((($seed ^ ($step + 1)) * 1597334677) + 12345) & 0xffffffff;
        return $seed ?: 1;
    }
}
