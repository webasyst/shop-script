<?php

class shopChestnyznakPluginModel extends shopProductCodeModel
{
    protected $code = 'chestnyznak';

    public function getProductCodeId() {
        $code_model = new shopProductCodeModel();
        return $code_model->select('id')->where("`code` = '{$this->code}'")->fetchField();
    }

    public function setupProductCode($images) {
        $code_model = new shopProductCodeModel();
        $code = array(
            'code' => $this->code,
            'name' => 'Честный ЗНАК'
        );
        $code_with_images = array_merge($code, $images);
        $code_model->insert($code_with_images);
    }
}
