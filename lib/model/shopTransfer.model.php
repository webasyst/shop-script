<?php

class shopTransferModel extends waModel
{
    protected $table = 'shop_transfer';

    const STATUS_SENT = 'sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @param $transfer
     * @param array $items of { sku_id: sku_id, count: count }
     * @return bool|int
     */
    public function send($transfer, $items)
    {
        if (!empty($transfer['from'])) {
            $transfer['stock_id_from'] = (int) ifset($transfer['from']);
        } else if (!empty($transfer['stock_id_from'])) {
            $transfer['stock_id_from'] = (int) ifset($transfer['stock_id_from']);
        }

        if (empty($transfer['stock_id_from'])) {
            // means new arrival
            $transfer['stock_id_from'] = 0;
        }

        if (!empty($transfer['to'])) {
            $transfer['stock_id_to'] = (int) ifset($transfer['to']);
        } else if (!empty($transfer['stock_id_to'])) {
            $transfer['stock_id_to'] = (int) ifset($transfer['stock_id_to']);
        } else {
            $transfer['stock_id_to'] = 0; // write-off
        }

        if ($transfer['stock_id_to'] === $transfer['stock_id_from']) {
            return false;
        }

        $transfer['status'] = self::STATUS_SENT;
        $transfer['create_datetime'] = date('Y-m-d H:i:s');
        $transfer['finalize_datetime'] = null;


        if (empty($transfer['string_id'])) {
            $transfer['string_id'] = $this->generateStringId();
        }

        $id = $this->insert($transfer);
        if (!$id) {
            return false;
        }

        $items = $this->getTransferProductsModel()->attach($id, $items);

        $diff_map = array();
        foreach ($items as $item) {
            $diff_map[$item['product_id']][$item['sku_id']][$transfer['stock_id_from']] = -$item['count'];
        }

        $this->applyStockDiffs($diff_map, $id);

        return $id;
    }

    public function receive($transfer_id)
    {
        $transfer = $this->getById($transfer_id);
        if (!$transfer || $transfer['status'] != self::STATUS_SENT) {
            return false;
        }

        $this->updateById(
            $transfer_id,
            array(
                'finalize_datetime' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_COMPLETED
            )
        );

        $items = $this->getTransferProductsModel()->getByTransfer($transfer_id);

        $diff_map = array();
        foreach ($items as $item) {
            $diff_map[$item['product_id']][$item['sku_id']][$transfer['stock_id_to']] = $item['count'];
        }

        $this->applyStockDiffs($diff_map, $transfer_id);

        return true;
    }

    public function cancel($transfer_id)
    {
        $transfer = $this->getById($transfer_id);
        if (!$transfer || $transfer['status'] != self::STATUS_SENT) {
            return false;
        }

        $this->updateById(
            $transfer_id,
            array(
                'status' => self::STATUS_CANCELLED
            )
        );

        $items = $this->getTransferProductsModel()->getByTransfer($transfer_id);

        $diff_map = array();
        foreach ($items as $item) {
            $diff_map[$item['product_id']][$item['sku_id']][$transfer['stock_id_from']] = $item['count'];
        }

        $this->applyStockDiffs($diff_map, $transfer_id);

    }

    public function getList($options = array())
    {
        $options = $this->prepareListOptions($options);

        $joins = array();
        if (strpos($options['order'], 'stock_to_') || strpos($options['fields'], 'stock_to_')) {
            $joins[] = "LEFT JOIN shop_stock stock_to ON stock_to.id = t.stock_id_to";
        }
        if (strpos($options['order'], 'stock_from_') || strpos($options['fields'], 'stock_from_')) {
            $joins[] = "LEFT JOIN shop_stock stock_from ON stock_from.id = t.stock_id_from";
        }
        if (strpos($options['order'], 'total_amount') || strpos($options['fields'], 'total_amount')) {
            $joins[] = "LEFT JOIN shop_transfer_products AS tp ON tp.transfer_id = t.id";
        }
        $joins = join(' ', $joins);
        $group_by = $joins ? 'GROUP BY t.id' : '';

        $where = $options['filter'] ? 'WHERE ' . $options['filter'] : '';

        return $this->query("
            SELECT {$options['fields']}
            FROM {$this->table} t
            {$joins}
            {$where}
            {$group_by}
            ORDER BY {$options['order']}
            LIMIT {$options['offset']}, {$options['limit']}
        ")->fetchAll('id');
    }

    public function getListCount($options = array())
    {
        $options = $this->prepareListOptions($options);

        $joins = array();
        if (strpos($options['order'], 'stock_to_') || strpos($options['fields'], 'stock_to_')) {
            $joins[] = "LEFT JOIN shop_stock stock_to ON stock_to.id = t.stock_id_to";
        }
        if (strpos($options['order'], 'stock_from_') || strpos($options['fields'], 'stock_from_')) {
            $joins[] = "LEFT JOIN shop_stock stock_from ON stock_from.id = t.stock_id_from";
        }
        $joins = join(' ', $joins);
        $group_by = $joins ? 'GROUP BY t.id' : '';

        $where = $options['filter'] ? 'WHERE ' . $options['filter'] : '';

        return $this->query("
            SELECT COUNT(*)
            FROM {$this->table} t
            {$where}
            {$joins}
            {$group_by}
        ")->fetchField();
    }

    public function mapIdToStringId($id = null)
    {
        $where = '';
        $ids = array();
        if ($id !== null) {
            $ids = array_map('intval', (array) $id);
            $where = 'WHERE id IN(i:0)';
        }
        $res = $this->query("SELECT `id`, `string_id` FROM `{$this->table}` {$where}", array($ids))->fetchAll('id', true);
        return !is_array($id) && $id !== null ? $res[(int) $id] : $res;
    }

    public function updateStringId($id, $string_id)
    {
        $this->exec("UPDATE IGNORE `{$this->table}` SET string_id = :string_id WHERE id = :id",
            array(
                'id' => $id,
                'string_id' => $string_id
            )
        );
    }

    public function isStringIdUnique($string_id, $id = 0)
    {
        return !$this->query("
            SELECT id FROM `{$this->table}`
            WHERE string_id = :string_id AND id != :id LIMIT 1",
            array(
                'id' => $id,
                'string_id' => $string_id
            ))->fetchField();
    }

    private function prepareListOptions($options)
    {
        $sm = new shopStockModel();

        $tables = array();

        $order = array();
        foreach (explode(',', ifset($options['order'], '')) as $part) {

            $part = explode(' ', $part);
            $field = trim($part[0]);
            $desc = trim(strtolower(ifset($part[1], 'asc'))) === 'desc';

            $tbl = 't';
            $period_pos = strpos($field, '.');
            if ($period_pos !== false) {
                $tbl = substr($field, 0, $period_pos);
                $field = substr($field, $period_pos + 1);
            }

            $m = $this;
            if ($tbl === 'stock_to' || $tbl === 'stock_from') {
                $m = $sm;
            }

            $tables[] = $tbl;

            if (!$m->fieldExists($field)) {
                continue;
            }

            if ($field === 'string_id') {
                if ($desc) {
                    $order[] = 't.string_id DESC,t.id DESC';
                } else {
                    $order[] = 't.string_id,t.id';
                }
            } else {
                $order[] = $tbl . ($tbl !== 't' ? '_' : '.') . $field . ($desc ? ' DESC' : '');
                if ($tbl === 'stock_to') {
                    $order[] = 'stock_id_to' . ($desc ? ' DESC' : '');
                } else if ($tbl === 'stock_from') {
                    $order[] = 'stock_id_from' . ($desc ? ' DESC' : '');
                }
            }
        }

        $order = join(',', $order);
        $options['order'] = ifempty($order, 't.create_datetime DESC,t.id DESC');

        $fields = array();
        $star = false;
        foreach (explode(',', ifset($options['fields'], '*')) as $field) {

            $star = $star || $field === '*';

            $tbl = 't';
            $period_pos = strpos($field, '.');
            if ($period_pos !== false) {
                $tbl = substr($field, 0, $period_pos);
                $field = substr($field, $period_pos + 1);
            }

            $m = $this;
            if ($tbl === 'stock_to' || $tbl === 'stock_from') {
                $m = $sm;
            }

            $tables[] = $tbl;

            if ($field === '*') {
                $tbl_fields = array_keys($m->getMetadata());
                foreach ($tbl_fields as $tbl_field) {
                    $fields[] = $tbl . '.' . $tbl_field . ($tbl !== 't' ? ' AS ' . $tbl . '_' . $tbl_field : '');
                }
            } else if ($field === 'total_amount') {
                $fields[] = 'SUM(tp.count * tp.price) AS total_amount';
            } else if ($field === 'total_count') {
                $fields[] = 'SUM(tp.count) AS total_count';
            } else if ($m->fieldExists($field)) {
                $fields[] = $tbl . '.' . $field . ($tbl !== 't' ? ' AS ' . $tbl . '_' . $field : '');
            }
        }

        if (empty($fields) || $star) {
            $tables = array_unique($tables);
            foreach ($tables as $tbl) {
                $m = $this;
                if ($tbl === 'stock_to' || $tbl === 'stock_from') {
                    $m = $sm;
                }
                $tbl_fields = array_keys($m->getMetadata());
                foreach ($tbl_fields as $tbl_field) {
                    $fields[] = $tbl . '.' . $tbl_field . ($tbl !== 't' ? ' AS ' . $tbl . '_' . $tbl_field : '');
                }
            }
        }

        $fields = array_unique($fields);
        $fields = join(',', $fields);
        $options['fields'] = $fields;

        $options['offset'] = (int) ifset($options['offset'], 0);
        $options['limit'] = (int) ifset($options['limit'], 0);

        // TODO: make more clever, take into account other tables
        $filter = array();
        foreach (explode('&', ifset($options['filter'], '')) as $token) {
            $token = trim($token);
            $parts = explode('=', $token);
            $field = trim($parts[0]);
            $val = trim(ifset($parts[1], ''));
            if ($this->fieldExists($field) && $val) {
                $filter[] = 't.' . $field . "='" . $this->escape($val) . "'";
            }
        }
        $options['filter'] = join(' AND ', $filter);

        return $options;
    }

    private function getTransferProductsModel()
    {
        return new shopTransferProductsModel();
    }

    public function generateStringId()
    {
        $today = date('Ymd');

        $last_string_id_today = $this->query("
              SELECT string_id FROM {$this->table}
              WHERE string_id LIKE '{$today}/%'
              ORDER BY LENGTH(string_id) DESC, string_id DESC, id DESC
              LIMIT 1
        ")->fetchField();
        $split = explode('/', $last_string_id_today);
        $num = ifset($split[1], 0) + 1;

        return $today . '/' . $num;
    }

    private function applyStockDiffs($diff_map, $transfer_id)
    {
        $stock_model = new shopStockModel();

        $all_stocks = $stock_model->getAll('id');

        foreach ($diff_map as $product_id => $sku_diff_map) {
            $product = new shopProduct($product_id);

            // IMPORTANT: shopProduct works correctly only with full list of skus
            // Otherwise there are some side-effects: change of sorts, change of MAIN sku (change of product.sku_id)
            // That is why we get ALL skus and than workup each sku
            $data = $product->skus;

            // if not changed, do not call ->save
            $changed = false;

            foreach ($data as $sku_id => &$sku) {

                if (empty($sku['stock'])) {
                    continue;
                }

                if (!isset($sku_diff_map[$sku_id])) {
                    continue;
                }

                $stock_diff_map = $sku_diff_map[$sku_id];
                foreach ($stock_diff_map as $stock_id => $diff) {
                    if (!isset($all_stocks[$stock_id])) {
                        continue;
                    }

                    $diff = (double) $diff;
                    if ($diff == 0) {
                        continue;
                    }

                    if (isset($sku['stock'][$stock_id])) {
                        $sku['stock'][$stock_id] += $diff;
                    } else {
                        $sku['stock'][$stock_id] = $diff;
                    }

                    $changed = true;

                }
            }
            unset($sku);

            if ($changed) {
                shopProductStocksLogModel::setContext(
                    shopProductStocksLogModel::TYPE_TRANSFER,
                    /*_w*/
                    ('Transfer %s'),
                    array(
                        'transfer_id' => $transfer_id
                    )
                );

                $product->save(array('skus' => $data));

                shopProductStocksLogModel::clearContext();
            }

        }
    }
}
