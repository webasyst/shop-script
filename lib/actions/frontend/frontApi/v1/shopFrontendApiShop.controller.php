<?php

/**
 * /shop
 */
class shopFrontendApiShopController extends shopFrontApiJsonController
{
    public function post()
    {
        return $this->get();
    }

    public function get()
    {
        $customer_token = $this->getRequest()->request('customer_token', '', waRequest::TYPE_STRING_TRIM);
        if (empty($customer_token)) {
            $customer_token = self::generateToken();
        }

        $sales_channel_id = $this->getRequest()->request('sales_channel_id', '', waRequest::TYPE_STRING_TRIM);

        $shop_config = wa()->getConfig();
        $checkout_config = $this->getCheckoutConfig();

        $currency_formatter = new shopFrontApiCurrencyFormatter();
        $currencies = array_values((new shopCurrencyModel())->getCurrencies());
        $currencies = array_map([$currency_formatter, 'format'], $currencies);

        $review_service_agreement = $shop_config->getGeneralSettings('review_service_agreement');

        $route = wa()->getRouting()->getRoute();
        $this->response = [
            'customer_token'        => $customer_token,
            'ignore_stock_count'    => (int) $shop_config->getGeneralSettings('ignore_stock_count'),
            'stock_counting_action' => $this->getCountingAction(),
            'server_timezone'       => date_default_timezone_get(),
            'server_timezone_shift' => (int) date('Z'),
            'server_time'           => date('Y-m-d H:i:s'),
            'frac_enabled'          => (bool) shopFrac::isEnabled(),
            'stock_units_enabled'   => (bool) shopUnits::baseUnitsEnabled(),
            'base_units_enabled'    => (bool) shopUnits::stockUnitsEnabled(),
            'debug_mode'            => waSystemConfig::isDebug(),
            'is_premium'            => shopLicensing::isPremium(),
            'storefront_enabled'    => ifset($route, 'storefront_mode', '') === 'storefront_api',
            'moderation_reviews'    => (bool)$shop_config->getGeneralSettings('moderation_reviews'),
            'require_auth_to_add_review' => (bool)$shop_config->getGeneralSettings('require_authorization'),
            'allow_review_image_upload' => (bool)$shop_config->getGeneralSettings('allow_image_upload'),
            'address_fields'        => self::getAddressSubfieldsOrder(),
            'default_currency'      => $shop_config->getCurrency(false), // storefront currency
            'currencies'            => $currencies,
            'shop_name'             => $shop_config->getGeneralSettings('name'),
            'shop_phone'            => $shop_config->getGeneralSettings('phone'),
            'shop_email'            => $shop_config->getGeneralSettings('email'),
            'shop_country'          => $shop_config->getGeneralSettings('country'),
            'shop_version'          => wa('shop')->getVersion(),
            'shop_schedule'         => (new shopFrontApiScheduleFormatter())->format($shop_config->getSchedule()),
            'locale'                => wa()->getLocale(),
            'customer_agreement'    => [
                'setting' => ifset($checkout_config, 'customer', 'service_agreement', ''), // 'checkbox' | 'notice' | ''
                'text' => ifset($checkout_config, 'customer', 'service_agreement', '') ? ifset($checkout_config, 'customer', 'service_agreement_hint', '') : '',
            ],
            'service_terms'    => [
                'setting' => ifset($checkout_config, 'confirmation', 'terms', '') ? 'checkbox' : '',
                'text' => ifset($checkout_config, 'confirmation', 'terms', '') ? ifset($checkout_config, 'confirmation', 'terms_text', '') : '',
            ],
            'review_service_agreement' => [
                'setting' => $review_service_agreement, // 'checkbox' | 'notice' | ''
                'text' => (empty($review_service_agreement) ? '' : $shop_config->getGeneralSettings('review_service_agreement_hint'))
            ],
        ];

        $antispam_cart_key = shopApiCart::getAntispamCartKey($customer_token);
        if ($antispam_cart_key !== null) {
            $this->response['antispam_cart_key'] = $antispam_cart_key;
        }

        if ($sales_channel_id) {
            $channel_data = $this->getSalesChannelData($sales_channel_id);
            if ($channel_data) {
                $this->response['sales_channel'] = $channel_data;
            }
        }
    }

    /**
     * Return stock update Settings
     * @return string
     * @throws waDbException
     * @throws waException
     */
    protected function getCountingAction()
    {
        if (wa('shop')->getSetting('disable_stock_count')) {
            $stock_counting_action = 'none';
        } elseif (wa('shop')->getSetting('update_stock_count_on_create_order')) {
            $stock_counting_action = 'create';
        } else {
            $stock_counting_action = 'processing';
        }

        return $stock_counting_action;
    }

    protected static function getAddressSubfieldsOrder()
    {
        $f = waContactFields::get('address');
        if (!$f || !$f instanceof waContactField) {
            return array();
        }
        $subfields = $f->getParameter('fields');
        if (!$subfields || !is_array($subfields)) {
            return array();
        }
        $result = array();
        foreach ($subfields as $sf) {
            if (!$sf instanceof waContactHiddenField) {
                $result[] = $sf->getId();
            }
        }
        return $result;
    }

    protected function getSalesChannelData($id)
    {
        if (strpos($id, ':') !== false) {
            list($type, $id) = explode(':', $id, 2);
        }
        $id = (int) $id;

        if ($id <= 0) {
            return null;
        }

        $sales_channel_model = new shopSalesChannelModel();
        $channel = $sales_channel_model->getById($id);
        if (empty($channel) || $channel['status'] <= 0) {
            return null;
        }

        try {
            $sales_channel_params_model = new shopSalesChannelParamsModel();
            $channel['params'] = $sales_channel_params_model->get($id);
            $channel['params'] = shopSalesChannelType::factory($channel['type'])->getPublicStorefrontParams($channel);
            return [
                'id' => (int) $id,
                'type' => $channel['type'],
                'params' => $channel['params'],
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
}
