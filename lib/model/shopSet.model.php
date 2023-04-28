<?php

class shopSetModel extends waModel
{
    protected $table = 'shop_set';

    const TYPE_STATIC  = 0;
    const TYPE_DYNAMIC = 1;

    const TOTAL_COUNT_RULE = 'total_count';
    const BESTSELLERS_RULE = 'bestsellers';

    public function add($data)
    {
        if (!empty($data)) {
            $set_group_model = new shopSetGroupModel();
            $updated = $set_group_model->query("UPDATE {$set_group_model->getTableName()} SET sort = sort + 1");
            if ($updated && $this->query("UPDATE {$this->table} SET sort = sort + 1")) {
                if (!isset($data['create_datetime'])) {
                    $data['create_datetime'] = date('Y-m-d H:i:s');
                }
                $id = $this->escape($data['id']);
                if ($this->insert($data, 2)) {
                    return $id;
                }
            }
        }
        return false;
    }

    public function getAll($key = null, $normalize = false)
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY sort")->fetchAll($key, $normalize);
    }

    public function getSetsWithGroups($set_id = null, $empty_groups = true)
    {
        if (is_numeric($set_id) || is_array($set_id)) {
            $sets = $this->getById($set_id);
        } else {
            $sets = $this->getAll('id');
        }

        $set_group_model = new shopSetGroupModel();
        $groups = $set_group_model->getAll('id');

        foreach($groups as &$group) {
            $group['sets'] = [];
        }
        unset($group);

        $result = [];
        foreach($sets as $set) {
            $set = [
                'is_group' => false,
                'set_id' => $set['id'],
            ] + $set;
            if (empty($set['group_id']) || empty($groups[$set['group_id']])) {
                $set['group_id'] = null;
                $result[] = $set;
            } else {
                $groups[$set['group_id']]['sets'][] = $set;
            }
        }

        foreach($groups as $group) {
            if ($empty_groups || !empty($group['sets'])) {
                $group = [
                    'is_group' => true,
                    'group_id' => $group['id'],
                ] + $group;
                $result[] = $group;
            }
        }

        $sort = array_column($result, 'sort');
        array_multisort($sort, SORT_ASC, $result);

        return $result;
    }

    public function move($id, $before_id = null)
    {
        $id = $this->escape($id);
        $set_group_model = new shopSetGroupModel();
        if (!$before_id) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT GREATEST(MAX(s.sort), MAX(sg.sort)) AS `max_sort`
                FROM {$this->table} s
                JOIN {$set_group_model->getTableName()} sg")->fetchField('max_sort') + 1;
            $this->updateById($id, array('sort' => $sort));
        } else {
            $before_id = $this->escape($before_id);
            $items = $this->query("SELECT * FROM {$this->table} WHERE id IN ('$id', '$before_id') AND `group_id` IS NULL")->fetchAll('id');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_id]['sort'];
            $set_group_model->query("UPDATE {$set_group_model->getTableName()} SET sort = sort + 1 WHERE sort >= $sort");
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= $sort");
            $this->updateById($id, array('sort' => $sort));
        }
        return true;
    }

    public function delete($id)
    {
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }

        /**
         * @event set_delete
         */
        wa('shop')->event('set_delete', $item);

        $this->deleteById($id);

        // delete related info
        $set_products_model = new shopSetProductsModel();
        $set_products_model->deleteByField('set_id', $id);

        return true;
    }

    public function update($id, $data) {
        $change_id = false;
        if (isset($data['id'])) {
            if ($data['id'] == $id) {
                unset($data['id']);
            } else {
                $change_id = true;
            }
        }
        $this->updateById($id, $data);
        if ($change_id) {
            $set_products_model = new shopSetProductsModel();
            $set_products_model->updateByField(array('set_id' => $id), array('set_id' => $data['id']));
        }
    }

    public function recount($set_id = null)
    {
        $cond = "
            WHERE s.type = ".self::TYPE_STATIC."
            GROUP BY s.id
            HAVING s.count != cnt
        ";
        if ($set_id !== null) {
            $set_ids = array();
            foreach ((array)$set_id as $id) {
                $set_ids[] = $id;
            }
            if (!$set_ids) {
                return;
            }
            $cond = "
                WHERE s.id IN ('".implode("','", $this->escape($set_ids))."') AND s.type = ".self::TYPE_STATIC."
                GROUP BY s.id
            ";
        }
        $sql = "
        UPDATE `{$this->table}` s JOIN (
            SELECT s.id, s.count, count(sp.product_id) cnt
            FROM `{$this->table}` s
            LEFT JOIN `shop_set_products` sp ON sp.set_id = s.id
            $cond
        ) r ON s.id = r.id
        SET s.count = r.cnt";

        return $this->exec($sql);
    }

    public function suggestUniqueId($original_id)
    {
        $counter = 1;
        $id = $original_id;
        while ($this->idExists($id)) {
            $id = "{$original_id}_{$counter}";
            $counter++;
        }
        return $id;
    }

    public function idExists($id)
    {
        $where = "id = s:id";
        return !!$this->select('id')->where($where, array('id' => $id))->fetch();
    }

    /**
     * @return array[]
     */
    public static function getRuleOptions()
    {
        return [
            [
                'name' => _w('Date added'),
                'value' => ''
            ],
            [
                'name' => _w('Most expensive'),
                'value' => 'price DESC'
            ],
            [
                'name' => _w('Least expensive'),
                'value' => 'price ASC'
            ],
            [
                'name' => _w('Highest rated'),
                'value' => 'rating DESC'
            ],
            [
                'name' => _w('Lowest rated'),
                'value' => 'rating ASC'
            ],
            [
                'name' => _w('Bestsellers by sold amount'),
                'value' => 'total_sales DESC'
            ],
            [
                'name' => _w('Bestsellers by sold quantity'),
                'value' => self::TOTAL_COUNT_RULE
            ],
            [
                'name' => _w('Bestsellers by complex value of “quantity × amount × rating”'),
                'value' => self::BESTSELLERS_RULE
            ],
            [
                'name' => _w('Worst sellers'),
                'value' => 'total_sales ASC'
            ],
            [
                'name' => _w('By name'),
                'value' => 'name ASC'
            ],
            [
                'name' => _w('Compare at price is set'),
                'value' => 'compare_price DESC'
            ],
            [
                'name' => _w('Date edited'),
                'value' => 'edit_datetime DESC'
            ]
        ];
    }

    /**
     * @return array[]
     */
    public static function getSortProductsOptions()
    {
        $result = [
            [
                'name' => _w("manual"),
                'value' => ""
            ],
            [
                'name' => _w("product name"),
                'value' => "name ASC"
            ],
            [
                'name' => _w('price'),
                'value' => 'price ASC'
            ],
            [
                'name' => _w("compare at price"),
                'value' => "compare_price ASC"
            ],
            [
                'name' => _w("purchase price"),
                'value' => "purchase_price ASC"
            ],
            [
                'name' => _w("in stock"),
                'value' => "count ASC"
            ],
            [
                'name' => _w("rating"),
                'value' => "rating ASC"
            ],
            [
                'name' => _w("date added"),
                'value' => "create_datetime ASC"
            ]
        ];

        // TODO: добавить другие варианты сортировки товаров, которые добавляют плагины.
        // $result[] = [
        //     'name' => 'TODO:plugin variant',
        //     'value' => 'todo:plugin_id'
        // ];

        return $result;
    }
}
