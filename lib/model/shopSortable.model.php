<?php
abstract class shopSortableModel extends waModel
{
    protected $sort = 'sort';
    protected $context = null;

    public function move($id, $after_id, $context = null)
    {
        $entry = $this->getById($id);
        if (!$entry) {
            throw new waException(sprintf(_w("%s entry not found"), $this->table) . var_export($id, true) . __LINE__);

        }

        if (!empty($after_id)) {
            $after_item = $this->getById($after_id);
            if (!$after_item) {
                throw new waException(sprintf(_w("%s entry not found"), $this->table) . var_export($after_id, true) . __LINE__);

            }
            $sort = $after_item[$this->sort];
        } else {
            $sort = -1;
        }

        $sql = null;
        if ($sort > $entry['sort']) {
            $sql = "UPDATE {$this->table} SET {$this->sort} = {$this->sort} - 1 WHERE {$this->sort} > i:sort_old AND {$this->sort} <= i:sort";
        } elseif ($sort < $entry['sort']) {
            $sort++;
            $sql = "UPDATE {$this->table} SET {$this->sort} = {$this->sort} + 1 WHERE {$this->sort} >= i:sort AND {$this->sort} < i:sort_old";
        }
        if ($sql) {
            $params = array('sort' => $sort, 'sort_old' => $entry[$this->sort]);
            if ($context !== null) {
                $sql .= ' AND ' . $this->getWhereByField($this->context, $context);
            }
            $this->exec($sql, $params);
            $this->updateById($id, array($this->sort => (int)$sort));
        }
        return $sort;
    }

    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY " . $this->sort;
        return $this->query($sql)->fetchAll($key, $normalize);
    }

    public function getPage($offset = 0, $limit = 50, $key = null, $normalize = false)
    {
        $sql = "SELECT * FROM " . $this->table . " ORDER BY " . $this->sort . " LIMIT i:offset,i:limit";
        $params = array(
            'offset' => max(0, $offset),
            'limit' => max(1, $limit),
        );
        return $this->query($sql, $params)->fetchAll($key, $normalize);

    }

    private function remapId($id)
    {
        if (is_array($this->id)) {
            $field = array_fill_keys($this->id, null);
            foreach ($this->id as $n => $name) {
                if (isset($id[$name])) {
                    $field[$name] = $id[$name];
                } elseif (isset($id[$n])) {
                    $field[$name] = $id[$n];
                }
            }
        } else {
            $field = array($this->id => $id);
        }
        return $field;
    }

    /**
     * FIX
     */
    public function getById($value)
    {
        return self::getByField($this->remapId($value), !is_array($this->id) && is_array($value));
    }

    /**
     * FIX
     */
    public function updateById($id, $data, $options = null, $return_object = false)
    {
        return self::updateByField($this->remapId($id), $data, $options, $return_object);
    }

    public function insert($data, $type = 0)
    {
        if (!isset($data['sort'])) {
            $sql = "SELECT MAX(`sort`) `sort`, COUNT(1) `cnt` FROM {$this->table}";
            $where = array();
            if (is_array($this->id)) {
                $fields = $this->remapId($data);

                foreach ($fields as $field => $value) {
                    if ($value !== null) {
                        $where[] = $this->getWhereByField($field, $value);
                    }
                }

            }
            if ($this->context && isset($data[$this->context])) {
                $where[] = $this->getWhereByField($this->context, $data[$this->context]);
            }
            if ($where) {
                $sql .= ' WHERE (' . implode(') AND (', $where) . ')';
            }
            $sort = $this->query($sql)->fetchAssoc();
            if ($sort['cnt']) {
                ++$sort['sort'];
            }
            $data['sort'] = $sort['sort'];
        }
        return parent::insert($data, $type);
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $data = parent::getByField($field, $value, $all, $limit);
        if (is_array($data) && $data && ($all || (is_array($field) && $value))) {
            $this->sortRows($data);
        }
        return $data;
    }

    protected function sort($a, $b)
    {
        $sort = intval($a[$this->sort]) - intval($b[$this->sort]);
        return max(-1, min(1, $sort));
    }

    protected function sortRows(&$rows)
    {
        uasort($rows, array($this, 'sort'));
    }

    public function getTableContext()
    {
        return $this->context;
    }
}
