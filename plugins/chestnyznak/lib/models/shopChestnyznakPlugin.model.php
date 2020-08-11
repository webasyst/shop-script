<?php

class shopChestnyznakPluginModel extends shopProductCodeModel
{
    const PRODUCT_CODE_NAME = 'Честный ЗНАК';

    protected $code = 'chestnyznak';

    public function getProductCode() {
        $code_model = new shopProductCodeModel();
        return $code_model->select('id, name')->where("`code` = '{$this->code}'")->fetchAssoc();
    }

    public function setupProductCode($images) {
        $code_model = new shopProductCodeModel();
        $code_model->updateByField('plugin_id', $this->code, array('plugin_id' => ''));

        $code = array(
            'code' => $this->code,
            'name' => self::PRODUCT_CODE_NAME,
            'protected' => 1,
            'plugin_id' => $this->code
        );
        $code_with_images = array_merge($code, $images);
        $code_model->insert($code_with_images);
    }

    public function setName($product_code_id)
    {
        $code_model = new shopProductCodeModel();
        $code_model->updateById($product_code_id, array('name' => self::PRODUCT_CODE_NAME));
    }
}
