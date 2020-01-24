<?php

/**
 * General settings form, and submit controller for it.
 */
class shopSettingsGeneralAction extends waViewAction
{
    public function execute()
    {
        if (waRequest::post()) {
            $app_settings = new waAppSettingsModel();
            foreach ($this->getData() as $name => $value) {
                $app_settings->set('shop', $name, $value);
            }

            $app_settings->set('shop', 'disable_backend_customer_form_validation', waRequest::post('disable_backend_customer_form_validation') ? null : '1');

            //
            // Anti-spam
            //
            $anti_spam = waRequest::post('antispam');
            if (!empty($anti_spam['enabled'])) {
                $app_settings->set('shop', 'checkout_antispam', 1);
                $anti_spam_fields = array('email', 'captcha');
                foreach ($anti_spam_fields as $field) {
                    $v = ifset($anti_spam[$field]);
                    if ($v) {
                        $app_settings->set('shop', 'checkout_antispam_'.$field, $v);
                    } else {
                        $app_settings->del('shop', 'checkout_antispam_'.$field);
                    }
                }
            } else {
                $app_settings->del('shop', 'checkout_antispam');
            }

            $config_file = $this->getConfig()->getConfigPath('config.php');
            $lazy_loading = waRequest::post('lazy_loading', 0, waRequest::TYPE_INT);

            if (!$lazy_loading) {
                if (file_exists($config_file)) {
                    $config = include($config_file);
                } else {
                    $config = array();
                }
                $config['lazy_loading'] = 0;
                waUtils::varExportToFile($config, $config_file);
            } else {
                if (file_exists($config_file)) {
                    $config = include($config_file);
                    if (isset($config['lazy_loading'])) {
                        unset($config['lazy_loading']);
                        waUtils::varExportToFile($config, $config_file);
                    }
                }
            }
        }

        $this->view->assign('disable_backend_customer_form_validation', wa()->getSetting('disable_backend_customer_form_validation'));

        //
        // Anti-spam
        //
        foreach (array('checkout_antispam', 'checkout_antispam_email', 'checkout_antispam_captcha') as $k) {
            $this->view->assign($k, wa()->getSetting($k));
        }

        $cm = new waCountryModel();
        $settings = $this->getConfig()->getGeneralSettings();

        $this->view->assign('sort_order_items_variants', $this->getConfig()->getSortOrderItemsVariants());
        $this->view->assign('countries', $cm->all());
        $this->view->assign($settings);

        $this->view->assign('saved', waRequest::post());

        $routes = wa()->getRouting()->getByApp('shop');

        $domains = array_keys($routes);
        $domains = array_combine($domains, $domains);
        if (class_exists('waIdna')) {
            $idna = new waIdna();
            foreach ($domains as &$domain) {
                $domain = $idna->decode($domain);
            }
            unset($domain);
        }

        $this->view->assign('routes', $routes);

        $this->view->assign('domains', $domains);

        $this->view->assign('wa_settings', $this->getUser()->getRights('webasyst', 'backend'));

        $this->view->assign('lazy_loading', isset($lazy_loading) ? $lazy_loading : $this->getConfig()->getOption('lazy_loading'));

    }

    public function getData()
    {
        $data = array(
            'name'                          => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'email'                         => waRequest::post('email', '', waRequest::TYPE_STRING_TRIM),
            'phone'                         => waRequest::post('phone', '', waRequest::TYPE_STRING_TRIM),
            'country'                       => waRequest::post('country', '', waRequest::TYPE_STRING_TRIM),
            'order_format'                  => waRequest::post('order_format', '', waRequest::TYPE_STRING_TRIM),
            'use_gravatar'                  => waRequest::post('use_gravatar', '', waRequest::TYPE_INT),
            'gravatar_default'              => waRequest::post('gravatar_default', '', waRequest::TYPE_STRING_TRIM),
            'require_captcha'               => waRequest::post('require_captcha', 0, waRequest::TYPE_INT),
            'require_authorization'         => waRequest::post('require_authorization', 0, waRequest::TYPE_INT),
            'allow_image_upload'            => waRequest::post('allow_image_upload', 0, waRequest::TYPE_INT),
            'moderation_reviews'            => waRequest::post('moderation_reviews', 0, waRequest::TYPE_INT),
            'lazy_loading'                  => waRequest::post('lazy_loading', 0, waRequest::TYPE_INT),
            'review_service_agreement'      => waRequest::post('review_service_agreement', '', waRequest::TYPE_STRING),
            'review_service_agreement_hint' => waRequest::post('review_service_agreement_hint', '', waRequest::TYPE_STRING),
            'sort_order_items'              => waRequest::post('sort_order_items', 'user_cart', waRequest::TYPE_STRING),
            'merge_carts'                   => waRequest::post('merge_carts', 0, waRequest::TYPE_INT)
        );
        if (waRequest::post('map')) {
            $data['map'] = waRequest::post('map', '', waRequest::TYPE_STRING_TRIM);
        }
        return $data;
    }
}
