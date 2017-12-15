<?php

class shopSettingsGetContactFieldsMethod extends shopApiMethod
{
    protected $courier_allowed = true;
    public function execute()
    {
        $type = waRequest::request('customer_fields', null, waRequest::TYPE_INT);

        if ($type == 1) {
            $fields = self::getCheckoutFieldSettings();
        } else {
            $fields = self::getFieldSettings();
        }
        $this->response = array(
            'fields' => $fields
        );
    }

    protected static function getCheckoutFieldSettings()
    {
        $result = array();
        foreach(shopHelper::getCustomerForm()->fields as $f) {
            $result[] = $f->getInfo();
        }
        return $result;
    }

    protected static function getFieldSettings()
    {
        $result = array();
        foreach(waContactFields::getAll() as $f) {
            $result[] = $f->getInfo();
        }
        return $result;
    }
}
