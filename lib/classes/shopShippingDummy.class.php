<?php

class shopShippingDummy extends waShipping
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
            'name'        => _w('Free shipping'),
            'description' => _w(''),
        );
    }

    public function getSettingsHTML($params = array())
    {
        return '';
    }

    public function allowedCurrency()
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        return $config->getCurrency();
    }

    public function allowedWeightUnit()
    {
        return 'kg';
    }

    protected function calculate()
    {
        return array(
            'delivery' => array(
                'est_delivery' => '',
                'currency'     => $this->getPackageProperty('currency'),
                'rate'         => 0,
            ),
        );
    }
}