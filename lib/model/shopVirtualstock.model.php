<?php
class shopVirtualstockModel extends waModel
{
    protected $table = 'shop_virtualstock';

    public function add($data)
    {
        if (empty($data['name'])) {
            return false;
        }
        $data['sort'] = $this->query("SELECT MAX(sort) FROM {$this->table}")->fetchField() + 1;
        if (!isset($data['low_count']) || $data['low_count'] < 0) {
            $data['low_count'] = shopStockModel::LOW_DEFAULT;
        }
        if (!isset($data['critical_count']) || $data['critical_count'] < 0) {
            $data['critical_count'] = shopStockModel::CRITICAL_DEFAULT;
        }
        return $this->insert(array_intersect_key($data, $this->getMetadata()));
    }
}
