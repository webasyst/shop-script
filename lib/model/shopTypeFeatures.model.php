<?php

class shopTypeFeaturesModel extends shopSortableModel
{
    protected $table = 'shop_type_features';
    protected $id = array('type_id', 'feature_id',);
    protected $context = 'type_id';

    public function getSkuTypeSelectableTypes()
    {
        $sql = "SELECT DISTINCT type_id FROM ".$this->table." tf JOIN
                shop_feature f ON tf.feature_id = f.id
                WHERE f.multiple
                    AND f.selectable";
        if ($rows = $this->query($sql)->fetchAll($this->context)) {
            $rows = array_keys($rows);
            $types = array_combine($rows, $rows);
        } else {
            $types = array();
        }

        return $types;
    }

    /**
     * @param int $type_id Product Type ID
     * @return array Features
     */
    public function getByType($type_id)
    {
        $sql = "SELECT * FROM ".$this->table." tf JOIN
                shop_feature f ON tf.feature_id = f.id
                WHERE tf.type_id = i:id
                ORDER BY tf.sort";
        return $this->query($sql, array('id' => $type_id))->fetchAll();
    }

    public function addType($type_id)
    {
        $sql = "INSERT INTO `{$this->table}`  (`type_id`,`feature_id`,`sort`)
  SELECT i:type_id, `b`.`feature_id`, 0
  FROM `{$this->table}` `b`
  WHERE `b`.`type_id`=0";
        return $this->query($sql, array('type_id' => $type_id));
    }

    public function updateByFeature($feature_id, $types = array(), $delete_obsolete_types = true)
    {
        $feature_id = intval($feature_id);
        if (!is_array($types)) {
            throw new waException('Invalid types '.var_export($types, true));
        }
        $types = array_unique(array_map('intval', array_values($types)));

        $sql = "SELECT type_id, sort FROM {$this->table} WHERE {$this->getWhereByField('feature_id', $feature_id)}";
        $current_types = array_keys($sort = $this->query($sql)->fetchAll('type_id', true));
        if ($delete_obsolete_types && ($obsolete_types = array_map('intval', array_diff($current_types, $types)))) {
            $this->deleteByField(array('feature_id' => $feature_id, 'type_id' => $obsolete_types));
            //fix sort holes
            $where = array();
            foreach ($obsolete_types as $type) {
                $obsolete_sort = intval($sort[$type]);
                $where[] = "(feature_id = {$feature_id} AND type_id = {$type} AND sort > {$obsolete_sort})";
            }
            if ($where) {
                $sql = "UPDATE {$this->table} SET sort = sort - 1 WHERE ".implode(' OR ', $where);
                $this->exec($sql);
            }
        }

        if ($new_types = array_diff($types, $current_types)) {
            $sql = "SELECT MAX(sort) sort, type_id FROM {$this->table} WHERE ".$this->getWhereByField('type_id', $new_types)." GROUP BY type_id";
            $sort = $this->query($sql)->fetchAll('type_id', true);
            $data = array('feature_id' => $feature_id, 'type_id' => $new_types);
            $values = array();
            foreach ($new_types as $type) {
                $values[] = sprintf("(%d, %d, %d)", $feature_id, $type, isset($sort[$type]) ? ++$sort[$type] : 0);
            }
            //TODO use multipleInsert
            $sql = "INSERT INTO {$this->table} (feature_id, type_id, sort) VALUES ".implode(", ", $values);
            $this->exec($sql);
        }
        return $types;
    }

    public function countFeatures(&$types, $all = true)
    {
        $sql = <<<SQL
    SELECT `type_id` `id`, COUNT(*) `features_count`
    FROM `{$this->table}`
    GROUP BY `id`
SQL;
        $result = $this->query($sql);
        while ($row = $result->fetchAssoc()) {
            if (isset($types[$row['id']])) {
                $types[$row['id']] += $row;
            }
        }

    }

    public function fillTypes(&$features, &$types = null)
    {

        $sql = "SELECT * FROM ".$this->table;

        $map = array();
        foreach ($features as $id => & $feature) {
            if (!isset($feature['types'])) {
                $feature['types'] = array();
            }
            $feature_id = empty($feature['id']) ? $id : $feature['id'];
            $map[$feature_id] = $id;
        }
        unset($feature);

        if (count($features) == 1) {
            reset($features);
            $feature = current($features);
            $feature_id = empty($feature['id']) ? key($features) : $feature['id'];
            $sql .= ' WHERE '.$this->getWhereByField('feature_id', $feature_id);
        } elseif ($types === null) {
            $sql .= ' WHERE '.$this->getWhereByField('feature_id', array_keys($map));
        } elseif ($types) {
            $sql .= ' WHERE '.$this->getWhereByField('type_id',  array_keys($types));
        }

        $result = $this->query($sql);
        $all = array('id' => 0, 'name' => _w('All product types'), 'icon' => '');
        while ($row = $result->fetchAssoc()) {
            if (isset($map[$row['feature_id']])) {
                $id = $map[$row['feature_id']];
                if (isset($features[$id])) {
                    $f =& $features[$id];

                    $type = $row['type_id'];

                    if (!isset($f['sort'])) {
                        $f['sort'] = array();
                    } elseif (!is_array($f['sort'])) {
                        $f['sort'][$type] = array($f['sort']);
                    }
                    if ($types === null) {
                        if (!$type) {
                            $f['types'][$type] =& $all;
                            $f['sort'][$type] = intval($row['sort']);
                        } else {
                            $f['types'][$type] = array('id' => $type);
                            $f['sort'][$type] = intval($row['sort']);
                        }
                    } elseif (isset($types[$type])) {
                        $f['types'][$type] =& $types[$type];
                        $f['sort'][$type] = intval($row['sort']);
                    } elseif (!$type) {
                        $f['types'][$type] =& $all;
                        $f['sort'][$type] = intval($row['sort']);
                    }
                    unset($f);
                }
            } else {
                // obsolete feature types
                //$this->deleteByField('feature_id', $row['feature_id']);
            }
        }

    }
}
