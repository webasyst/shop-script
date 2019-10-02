<?php

class shopShippingDummy extends waShipping
{
    /**
     * @return waShipping
     * @throws waException
     */
    public static function getDummy()
    {
        return new self('dummy');
    }

    public static function info($id, $options = array(), $type = null)
    {
        return array(
            'img'              => null,
            'icon'             => null,
            'logo'             => null,
            'version'          => '1.44',
            'name'             => _w('Free shipping by courier'),
            'description'      => '',
            'services_by_type' => true,
        );
    }

    public function getSettingsHTML($params = array())
    {
        return '';
    }

    public function allowedCurrency()
    {
        $config = wa('shop')->getConfig();
        /** @var shopConfig $config */
        return $config->getCurrency();
    }

    public function allowedWeightUnit()
    {
        return 'kg';
    }

    protected function calculate()
    {
        return [
            'courier' => [
                'type'         => waShipping::TYPE_TODOOR,
                'currency'     => $this->getPackageProperty('currency'),
                'est_delivery' => '',
                'rate'         => 0,
                'custom_data'  => [
                    waShipping::TYPE_TODOOR => [
                        'payment' => [
                            waShipping::PAYMENT_TYPE_CARD    => true,
                            waShipping::PAYMENT_TYPE_CASH    => true,
                            waShipping::PAYMENT_TYPE_PREPAID => true,
                        ],
                    ],
                ],
            ],
        ];
    }
}
