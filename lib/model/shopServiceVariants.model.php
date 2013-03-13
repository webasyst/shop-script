<?php


class shopServiceVariantsModel extends waModel
{
    protected $table = 'shop_service_variants';

    public function delete($id)
    {
        if (!$this->deleteById($id)) {
            return false;
        }

        $product_services = new shopProductServicesModel();
        $product_services->deleteByField('service_variant_id', $id);

        return true;
    }

    public function get($service_id)
    {
        return $this->query("SELECT * FROM `{$this->table}`WHERE service_id = ".(int)$service_id." ORDER BY id")->fetchAll();
    }
}