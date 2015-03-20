<?php
class shopPromoModel extends waModel
{
    protected $table = 'shop_promo';

    public function getByStorefront($storefront, $type='link')
    {
        if (!$storefront) {
            return array();
        }

        $sql = "SELECT p.*, r.sort
                FROM {$this->table} AS p
                    JOIN shop_promo_routes AS r
                        ON p.id=r.promo_id
                WHERE r.storefront IN (?)
                    AND type=?
                ORDER BY r.sort, p.id";
        $result = $this->query($sql, array($storefront, $type))->fetchAll('id');

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
}

