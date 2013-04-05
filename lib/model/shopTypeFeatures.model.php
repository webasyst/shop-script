<?php
class shopTypeFeaturesModel extends shopSortableModel
{
    protected $table = 'shop_type_features';
    protected $id = array('type_id', 'feature_id', );
    protected $context = 'type_id';

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

    public function fillTypes(&$features, &$types)
    {

        $sql = "SELECT * FROM ".$this->table;
        if (count($features) == 1) {
            reset($features);
            $feature = current($features);
            $feature_id = empty($feature['id']) ? key($features) : $feature['id'];
            $sql .= ' WHERE '.$this->getWhereByField('feature_id', $feature_id);
        }

        $result = $this->query($sql);
        $all = array('id' => 0, 'name' => _w('All product types'), 'icon' => '');
        $map = array();
        foreach ($features as $id => & $feature) {
            if (!isset($feature['types'])) {
                $feature['types'] = array();
            }
            $feature_id = empty($feature['id']) ? $id : $feature['id'];
            $map[$feature_id] = $id;
        }
        unset($feature);
        while ($row = $result->fetchAssoc()) {
            if (isset($map[$row['feature_id']])) {
                $id = $map[$row['feature_id']];
                if (isset($features[$id])) {
                    $f =& $features[$id];

                    if (!isset($f['sort']) || !is_array($f['sort'])) {
                        $f['sort'] = array();
                    }
                    $type = $row['type_id'];
                    if (isset($types[$type])) {
                        $f['types'][$type] =& $types[$type];
                        $f['sort'][$type] = intval($row['sort']);
                    } elseif (!$type) {
                        $f['types'][$type] =& $all;
                        $f['sort'][$type] = intval($row['sort']);
                    }
                    unset($f);
                }
            } else {
                // undeleted feature types
                //$this->deleteByField('feature_id', $row['feature_id']);
                }
        }

    }
}
