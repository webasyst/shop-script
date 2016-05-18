<?php
class shopPromoModel extends waModel
{
    protected $table = 'shop_promo';

    public function getByStorefront($storefront, $type='link', $enable_status = null)
    {
        if (!$storefront) {
            return array();
        }

        $sql = "SELECT p.*, r.sort
                FROM {$this->table} AS p
                    JOIN shop_promo_routes AS r
                        ON p.id=r.promo_id
                WHERE r.storefront IN (?)
                    AND type=? :enable
                ORDER BY r.sort, p.id";

        if ($enable_status === null) {
            $sql = str_replace(':enable', '', $sql);
        } else if ($enable_status) {
            $sql = str_replace(':enable', 'AND p.enabled > 0', $sql);
        } else {
            $sql = str_replace(':enable', 'AND p.enabled <= 0', $sql);
        }

        $storefronts = array(
            rtrim($storefront, '/') . '/',
            rtrim($storefront, '/')
        );
        $result = $this->query($sql, array($storefronts, $type))->fetchAll('id');

        $result_all = array_diff_key($this->query($sql, array('%all%', $type))->fetchAll('id'), $result);
        if ($result_all) {

            $max_sort = 0;
            foreach($result as $row) {
                $max_sort = max($max_sort, $row['sort']);
            }

            $values = array();
            foreach($result_all as $row) {
                $max_sort++;
                $row['sort'] = $max_sort;
                $values[] = "('{$row['id']}', '".$this->escape($storefront)."', '{$max_sort}')";
                $result[$row['id']] = $row;
            }

            $sql = "INSERT IGNORE INTO shop_promo_routes (promo_id, storefront, sort) VALUES ".join(',', $values);
            $this->exec($sql);
        }

        return $result;
    }

    public function getDisabled($type='link')
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE type=? AND enabled <= 0
                ORDER BY id";
        return $this->query($sql, array($type))->fetchAll('id');
    }

    public function countDisabled($type='link')
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->table}
                WHERE type=? AND enabled <= 0";
        return $this->query($sql, array($type))->fetchField();
    }
}

