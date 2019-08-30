<?php

class shopPaymentDummy extends waPayment implements waIPayment
{
    /**
     * @return waPayment
     */
    public static function getDummy()
    {
        return new self('dummy');
    }

    public static function dummyInfo()
    {
        return array(
            'img'         => null,
            'logo'        => null,
            'name'        => _w('Manual payment'),
            'description' => '',
            'type'        => waPayment::TYPE_MANUAL,
        );
    }

    public function getSettingsHTML($params = array())
    {
        return '';
    }

    public function allowedCurrency()
    {
        return true;
    }

    public function getGuide($params = array())
    {
        return '';
    }
}
