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
        $data['low_count'] = ifempty($data['low_count'], shopStockModel::LOW_DEFAULT);
        $data['critical_count'] = ifempty($data['critical_count'], shopStockModel::CRITICAL_DEFAULT);
        return $this->insert(array_intersect_key($data, $this->getMetadata()));
    }
}
