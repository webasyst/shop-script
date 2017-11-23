<?php

class shopSettingsGetContactFieldsMethod extends shopApiMethod
{
    protected $courier_allowed = true;
    public function execute()
    {
        $this->response = array(
            'fields' => self::getFieldSettings(),
        );
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
