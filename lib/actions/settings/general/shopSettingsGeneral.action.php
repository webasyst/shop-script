<?php

/**
 * General settings form, and submit controller for it.
 */
class shopSettingsGeneralAction extends waViewAction
{
    public function execute()
    {
        $web_push = new shopWebPushNotifications();
        if (waRequest::post()) {
            $app_settings = new waAppSettingsModel();
            foreach ($this->getData() as $name => $value) {
                $app_settings->set('shop', $name, $value);
            }

            $sms = waRequest::post('sms', array());
            $path = $this->getConfig()->getPath('config', 'sms');
            $save = array();
            foreach ($sms as $s) {
                $from = $s['from'];
                $adapter = $s['adapter'];
                unset($s['from']);
                unset($s['adapter']);
                $empty = true;
                foreach ($s as $v) {
                    if ($v) {
                        $empty = false;
                        break;
                    }
                }
                if (!$empty) {
                    if (!$from) {
                        $from = '*';
                    }
                    foreach (explode("\n", $from) as $from) {
                        $from = trim($from);
                        $save[$from] = $s;
                        $save[$from]['adapter'] = $adapter;
                    }
                }
            }
            waUtils::varExportToFile($save, $path);

            if (($captcha = waRequest::post('captcha', null, 'string_trim')) && preg_match('~^(wa|shop)\w*Captcha$~', $captcha)) {
                $config_path = $this->getConfig()->getConfigPath('config.php');
                if (file_exists($config_path)) {
                    $config = include($config_path);
                }
                if ($captcha == 'waCaptcha') {
                    if (!empty($config['factories']['captcha'])) {
                        unset($config['factories']['captcha']);
                        waUtils::varExportToFile($config, $config_path);
                    }
                } else {
                    $captcha_options = waRequest::post('captcha_options');
                    $tmp = array_values($captcha_options);
                    $tmp = array_filter($tmp, 'trim');
                    if ($tmp) {
                        $captcha_factory = array(
                            $captcha,
                            $captcha_options
                        );
                        if (ifset($config['factories']['captcha']) != $captcha_factory) {
                            $config['factories']['captcha'] = $captcha_factory;
                            waUtils::varExportToFile($config, $config_path);
                        }
                    }
                }
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

            //
            // Web push settings
            //
            $web_push_settings = $this->getRequest()->post('web_push_settings');
            if (empty($web_push_settings['manifest']['name'])) {
                $web_push_settings['manifest']['name'] = $this->getConfig()->getGeneralSettings('name');
            }
            $web_push->saveSettings($web_push_settings);

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

        $this->view->assign('web_push', $web_push);

        $this->view->assign('sort_order_items_variants', $this->getConfig()->getSortOrderItemsVariants());
        $this->view->assign('countries', $cm->all());
        $this->view->assign($settings);

        $sms_adapters = $this->getSMSAdapters();
        $this->view->assign('sms_adapters', $sms_adapters);

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
            'lazy_loading'                  => waRequest::post('lazy_loading', 0, waRequest::TYPE_INT),
            'review_service_agreement'      => waRequest::post('review_service_agreement', '', waRequest::TYPE_STRING),
            'review_service_agreement_hint' => waRequest::post('review_service_agreement_hint', '', waRequest::TYPE_STRING),
            'sort_order_items'              => waRequest::post('sort_order_items', 'user_cart', waRequest::TYPE_STRING),
        );
        if (waRequest::post('map')) {
            $data['map'] = waRequest::post('map', '', waRequest::TYPE_STRING_TRIM);
        }
        return $data;
    }

    protected function getSMSAdapters()
    {
        $path = $this->getConfig()->getPath('plugins').'/sms/';
        if (!file_exists($path)) {
            return array();
        }
        $dh = opendir($path);
        $adapters = array();
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..' || !is_dir($path.$f)) {
                continue;
            } elseif (file_exists($path.$f.'/lib/'.$f.'SMS.class.php')) {
                require_once($path.$f.'/lib/'.$f.'SMS.class.php');
                $class_name = $f.'SMS';
                $adapters[$f] = new $class_name(array());
            }
        }
        closedir($dh);
        $result = array();


        $config = wa()->getConfig()->getConfigFile('sms');

        $used = array();
        foreach ($config as $c_from => $c) {
            if (isset($adapters[$c['adapter']])) {
                $used[$c['adapter']] = 1;
                if (!isset($result[$c['adapter']])) {
                    $temp = $this->getSMSAdapaterInfo($adapters[$c['adapter']]);
                    $temp['config'] = $c;
                    $temp['config']['from'] = array($c_from);
                    $result[$c['adapter']] = $temp;
                } else {
                    $result[$c['adapter']]['config']['from'][] = $c_from;
                }
            }
        }
        $result = array_values($result);

        foreach ($adapters as $a) {
            /**
             * @var waSMSAdapter $a
             */
            if (!empty($used[$a->getId()])) {
                continue;
            }
            $result[] = $this->getSMSAdapaterInfo($a);
        }
        return $result;

    }

    protected function getSMSAdapaterInfo(waSMSAdapter $a)
    {
        $temp = $a->getInfo();
        $temp['id'] = $a->getId();
        $temp['controls'] = $a->getControls();
        return $temp;
    }
}
