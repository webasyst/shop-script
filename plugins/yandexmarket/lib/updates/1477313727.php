<?php
# move profile settings into campaign settings
/**
 * Получить список кампаний
 * Скопировать/перенести market_token
 *
 * Скопировать время работы магазина
 * Скопировать сроки/стоимость доставки по умолчанию
 *
 * Скопировать самовывоз, доставку, бесплатную доставку,
 *
 *
 * удалить market_token из общих настроек
 */
if (!empty($this) && (get_class($this) == 'shopYandexmarketPlugin')) {
    $plugin = $this;
} else {
    $plugin = wa('shop')->getPlugin('yandexmarket');
}


/**
 * @var shopYandexmarketPlugin $plugin
 */
if ($plugin->checkCpa()) {
    $settings = $plugin->getSettings();
    try {
        $campaigns = $plugin->getCampaigns();
        if ($campaigns) {
            $model = new shopYandexmarketCampaignsModel();
            foreach ($campaigns as $campaign_id => $campaign) {
                if (!empty($campaign['feeds'])) {
                    $feed = reset($campaign['feeds']);
                    if (!empty($feed['profile_id']) && !$model->getByField('id', $campaign_id)) {

                        $profile_helper = new shopImportexportHelper('yandexmarket');
                        $profile = $profile_helper->getConfig($feed['profile_id']);

                        $config = $profile['config'];
                        unset($config['map']);
                        unset($config['types']);
                        $shop = ifset($profile['config']['shop']);

                        $campaign = array(
                            'market_token'        => ifset($settings['market_token']),
                            'over_sell'           => !!ifempty($config['export']['zero_stock']),
                            'pickup'              => ifset($shop['pickup']) === 'true',
                            'delivery'            => ifset($shop['delivery']) === 'true',
                            'local_delivery_only' => true,
                            'deliveryIncluded'    => ifset($shop['deliveryIncluded']) === 'true',
                            'order_before_mode'   => '',
                            'order_before'        => ifset($shop['local_delivery_order_before'], ''),
                            'payment'             => array(
                                'CASH_ON_DELIVERY' => true,
                            ),
                        );

                        $campaign['shipping_methods'] = array(
                            'dummy' => array(
                                'estimate' => $shop['local_delivery_estimate'],
                                'cash'     => true,
                            ),
                        );

                        if (isset($shop['local_delivery_cost']) && ($shop['local_delivery_cost'] !== '')) {
                            $campaign['shipping_methods']['dummy']['cost'] = $shop['local_delivery_cost'];
                        }

                        $model->set($campaign_id, $campaign);
                    }
                }
            }
        }
    } catch (waException $ex) {
        waLog::log($ex->getMessage(), 'shop/plugins/yandexmarket/updates.error.log');
    }
    $settings['market_token'] = null;
    $plugin->saveSettings($settings);
}
