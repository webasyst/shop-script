<?php

class shopPaymentDummy extends waPayment implements waIPayment
{
    /**
     * @return waShipping
     */
    public static function getDummy()
    {
        return new self('dummy');
    }

    public static function dummyInfo()
    {
        return array(
            'img'         => null,
            'name'        => _w('Manual payment'),
            'description' => _w(''),
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
}