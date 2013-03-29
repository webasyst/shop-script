<?php

class shopTagModel extends waModel
{
    protected $table = 'shop_tag';

    const CLOUD_MAX_SIZE = 150;
    const CLOUD_MIN_SIZE = 80;
    const CLOUD_MAX_OPACITY = 100;
    const CLOUD_MIN_OPACITY = 30;

    public function getCloud($key = null)
    {
        $tags = $this->where('count > 0')->fetchAll($key);
        if (!empty($tags)) {
            $first = current($tags);
            $max_count = $min_count = $first['count'];
            foreach ($tags as $tag) {
                if ($tag['count'] > $max_count) {
                    $max_count = $tag['count'];
                }
                if ($tag['count'] < $min_count) {
                    $min_count = $tag['count'];
                }
            }
            $diff = $max_count - $min_count;
            $diff = $diff <= 0 ? 1 : $diff;
            $step_size = (self::CLOUD_MAX_SIZE - self::CLOUD_MIN_SIZE) / $diff;
            $step_opacity = (self::CLOUD_MAX_OPACITY - self::CLOUD_MIN_OPACITY) / $diff;
            foreach ($tags as &$tag) {
                $tag['size'] = ceil(self::CLOUD_MIN_SIZE + ($tag['count'] - $min_count) * $step_size);
                $tag['opacity'] = number_format((self::CLOUD_MIN_OPACITY + ($tag['count'] - $min_count) * $step_opacity) / 100, 2, '.', '');
                $tag['uri_name'] = urlencode($tag['name']);
            }
            unset($tag);
        }
        return $tags;
    }

    public function getByName($name, $return_id = false)
    {
        $sql = "SELECT * FROM ".$this->table." WHERE name LIKE '".$this->escape($name, 'like')."'";
        $row = $this->query($sql)->fetch();
        return $return_id ? (isset($row['id']) ? $row['id'] : null) : $row;
    }

    public function getIds($tags)
    {
        $result = array();
        foreach ($tags as $t) {
            $t = trim($t);
            if ($id = $this->getByName($t, true)) {
                $result[] = $id;
            } else {
                $result[] = $this->insert(array('name' => $t));
            }
        }
        return $result;
    }

    public function incCounters($tag_id, $inc = 1)
    {
        $inc = (int)$inc;
        if ($where = $this->getWhereByField('id', $tag_id)) {
            $counts_list = $this->query('SELECT id, count FROM '. $this->table . ' WHERE ' . $where)->fetchAll('id', true);
            $zero = array();
            $update = array();
            foreach ($counts_list as $id => $count) {
                if ($count + $inc <= 0) {
                    $zero[] = $id;
                } else {
                    $update[] = $id;
                }
            }
            if (!empty($zero)) {
                $this->query("UPDATE {$this->table} SET count = 0 WHERE ".$this->getWhereByField('id', $zero));
            }
            if (!empty($update)) {
                $this->query("UPDATE {$this->table} SET count = count + ($inc) WHERE ".$this->getWhereByField('id', $update));
            }
        }
    }

    public function recount($tag_id = null)
    {
        $cond = "
            GROUP BY t.id
            HAVING t.count != cnt
        ";
        if ($tag_id !== null) {
            $tag_ids = array();
            foreach ((array)$tag_id as $id) {
                $tag_ids[] = $id;
            }
            if (!$tag_ids) {
                return;
            }
            $cond = "
                WHERE t.id IN ('".implode("','", $this->escape($tag_ids))."')
                GROUP BY t.id
            ";
        }
        $sql = "
        UPDATE `{$this->table}` t JOIN (
            SELECT t.id, t.count, count(pt.product_id) cnt
            FROM `{$this->table}` t
            LEFT JOIN `shop_product_tags` pt ON pt.tag_id = t.id
            $cond
        ) r ON t.id = r.id
        SET t.count = r.cnt";

        return $this->exec($sql);
    }
}