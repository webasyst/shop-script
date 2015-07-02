<?php

class shopMigratePlugin extends shopPlugin
{
    private static $developer = false;

    public static function getTransports()
    {
        $transports = array(
            ''               => array(
                'value'       => '',
                'title'       => _wp('Select platform'),
                'description' => '',
            ),
            'webasystsame'   => array(
                'value'       => 'webasystsame',
                'title'       => _wp('WebAsyst Shop-Script (old version) on the same server'),
                'description' => _wp(
                    'Migrate aux pages, categories, products with params, features, images and eproduct files'
                ),
                'group'       => 'Webasyst',
            ),
            'webasystremote' => array(
                'value'       => 'webasystremote',
                'title'       => _wp('WebAsyst Shop-Script (old version) on a remote server'),
                'description' => '',
                'group'       => 'Webasyst',
            ),
            'opencart'       => array(
                'value'       => 'opencart',
                'title'       => _wp('OpenCart 1.5.x with Web API 1.0 plugin'),
                'description' => _wp('Migrate data from <a href="http://www.opencart.com" target="_blank">OpenCart</a>-powered online store via Web API 1.0 OpenCart plugin.<br><br><strong>IMPORTANT:</strong> <a href="http://www.opencart.com/index.php?route=extension/extension/info&extension_id=14754" target="_blank">Opencart API plugin</a> must be installed to export your data from OpenCart.'),
                'group'       => '3rdParty',
            ),
            'magento'        => array(
                'value'       => 'magento',
                'title'       => 'Magento',
                'description' => _wp('Import data from an online store powered by <a href="http://magento.com" target="_blank">Magento</a> CE 1.7 and later or Magento EE 1.12 and later.'),
                'group'       => '3rdParty',
            ),
            'insales'        => array(
                'value'       => 'insales',
                'title'       => 'InSales',
                'description' => 'Перенос данных из интернет-магазина на основе сервис <a href="http://www.insales.ru" target="_blank">InSales</a>. Получите ключ доступ InSales API в режиме администрирования вашего интернет-магазина InSales в разделе Приложения > Разработчикам и введите данные этого ключа, чтобы импортировать данные.',
                'group'       => '3rdParty',
            ),
            'simpla'         => array(
                'value'       => 'simpla',
                'title'       => 'Simpla',
                'description' => 'Импорт данных из интернет-магазина на основе <a href="http://www.simplacms.ru" target="_blank">Simpla</a> через Simpla REST API.',
                'group'       => '3rdParty',
            ),
            'phpshop'        => array(
                'value'       => 'phpshop',
                'title'       => 'PHPShop',
                'description' => 'Импорт данных из интернет-магазина на основе <a href="http://www.phpshop.ru" target="_blank">PHPShop</a> производится через файл YML (Яндекс.Маркет). К сожалению, у PHPShop нет открытого API, через который мы бы смогли загрузить все данные вашего интернет-магазина автоматически. Экспортируйте товары вашего интернет-магазина на основе PHPShop в файл YML и укажите ниже адрес этого файла.',
                'group'       => '3rdParty',
            ),
            'woocommerce'    => array(
                'value'       => 'woocommerce',
                'title'       => 'Wordpress WooCommerce',
                'description' => _wp('Migrate data from <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a>-powered online store (a plugin that enables ecommerce functionality for WordPress).'),
                'group'       => '3rdParty',
            ),
            'storelandru'    => array(
                'value'       => 'storelandru',
                'title'       => 'StoreLand',
                'description' => 'Импорт данных из интернет-магазина на основе <a href="http://www.storeland.ru" target="_blank">StoreLand</a> производится через файл YML (Яндекс.Маркет). К сожалению, у StoreLand нет открытого API, через который мы бы смогли загрузить все данные вашего интернет-магазина автоматически. Экспортируйте товары вашего интернет-магазина на основе StoreLand в файл YML и укажите ниже адрес этого файла.',
                'group'       => '3rdParty',
                'locale'      => array(
                    'ru_RU',
                )

            ),
            'yml'            => array(
                'value'       => 'yml',
                'title'       => _wp('YML feed file'),
                'description' => '',
                'group'       => _wp('YML'),
                'locale'      => array(
                    'ru_RU',
                )
            ),
        );


        return self::$developer ? array(
                '' => array(
                    'value'       => '',
                    'title'       => _wp('Select platform'),
                    'description' => '',
                )
            ) + shopMigrateTransport::enumerate() : $transports;
    }

    public function getWelcomeUrl($data = array())
    {
        $platform = ifset($data['platform']);
        return $platform ? sprintf('?action=importexport#/migrate/%s/', $platform) : null;
    }

    public function backendWelcomeHandler()
    {
        return array(
            'name'        => _wp('Migrate to Shop-Script'),
            'description' => _wp('Transfer data from third-party ecommerce platforms and sources to Shop-Script.'),
            'controls'    => array(
                'platform' => array(
                    'control_type' => waHtmlControl::SELECT,
                    'title'        => _wp('Import data'),
                    'options'      => $this->getTransports(),
                ),
            ),
        );
    }
}
