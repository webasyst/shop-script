<?php

class shopPromoRoutesModel extends waModel
{
    const FLAG_ALL = '%all%';

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

    public function reorderPromos($storefront, $ids)
    {
        if (!$ids || !$storefront) {
            return;
        }

        // make sure there's no duplicate entries like 'example.com' vs 'example.com/'
        $bad_storefront_alias = rtrim($storefront, '/');
        if ($bad_storefront_alias == $storefront) {
            $bad_storefront_alias .= '/';
        }
        $ids = (array) $ids;
        $this->deleteByField([
            'storefront' => $bad_storefront_alias,
            'promo_id' => $ids,
        ]);

        $sort = 0;
        $values = [];
        foreach($ids as $promo_id) {
            $promo_id = (int) $promo_id;
            $values[] = "({$promo_id}, '{$this->escape($storefront)}', '{$sort}')";
            $sort++;
        }

        $sql = "INSERT INTO {$this->table} (promo_id, storefront, sort)
                VALUES ".join(',', $values)."
                ON DUPLICATE KEY UPDATE
                sort = VALUES(sort)";
        $this->exec($sql);
    }

    public function deleteMissingStorefronts()
    {
        $storefronts = shopStorefrontList::getAllStorefronts();
        $storefronts[] = shopPromoRoutesModel::FLAG_ALL;
        $storefronts_values = "'" . implode("', '", $this->escape($storefronts)) . "'";
        $sql = "DELETE FROM `{$this->table}` WHERE `storefront` NOT IN ({$storefronts_values})";
        $this->exec($sql);
    }
}

