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
        return $this->query($sql, array($storefront, $type))->fetchAll('id');
    }
}

