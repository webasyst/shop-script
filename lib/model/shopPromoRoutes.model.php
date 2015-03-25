<?php
class shopPromoRoutesModel extends waModel
{
    protected $table = 'shop_promo_routes';

    public function getMaxSorts()
    {
        return $this->query("SELECT storefront, MAX(sort) FROM {$this->table} GROUP BY storefront")->fetchAll('storefront', true);
    }

    public function getStorefrontCounts()
    {
        $sql = "SELECT storefront, count(*) FROM {$this->table} GROUP BY storefront";
        return $this->query($sql)->fetchAll('storefront', true);
    }
}

