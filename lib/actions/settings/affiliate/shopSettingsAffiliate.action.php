<?php

class shopSettingsAffiliateAction extends waViewAction
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        if (waRequest::post()) {
            $conf = waRequest::post('conf');
            if ($conf && is_array($conf)) {
                $conf['affiliate_credit_rate'] = str_replace(',', '.', (float) str_replace(',', '.', ifset($conf['affiliate_credit_rate'], '0')));
                $conf['affiliate_usage_rate'] = str_replace(',', '.', (float) str_replace(',', '.', ifset($conf['affiliate_usage_rate'], '0')));
                foreach($conf as $k => $v) {
                    $asm->set('shop', $k, $v);
                }
            }
        }

        $enabled = shopAffiliate::isEnabled();
        $def_cur = waCurrency::getInfo(wa()->getConfig()->getCurrency());

        $tm = new shopTypeModel();
        $product_types = $tm->getAll();

        $conf = $asm->get('shop');

        if (!empty($conf['affiliate_product_types'])) {
            $conf['affiliate_product_types'] = array_fill_keys(explode(',', $conf['affiliate_product_types']), true);
        } else {
            $conf['affiliate_product_types'] = array();
        }

        $this->view->assign('conf', $conf);
        $this->view->assign('enabled', $enabled);
        $this->view->assign('product_types', $product_types);
        $this->view->assign('def_cur_sym', ifset($def_cur['sign_html'], ifset($def_cur['sign'], wa()->getConfig()->getCurrency())));

        /**
         * Backend affiliate settings
         *
         * Plugins are expected to return one item or a list of items to to add to affiliate menu.
         * Each item is represented by an array:
         * array(
         *   'id'   => string,  // Required.
         *   'name' => string,  // Required.
         *   'url'  => string,  // Required (unless you hack into JS using 'html' parameter). Content for settings page is fetched from this URL.
         * )
         *
         * @event backend_settings_discounts
         */
        $plugins = wa()->event('backend_settings_affiliate');
        $config = wa('shop')->getConfig();
        if ($plugins) {
            foreach ($plugins as $k => &$p) {
                if (substr($k, -7) == '-plugin') {
                    $plugin_id = substr($k, 0, -7);
                    $plugin_info = $config->getPluginInfo($plugin_id);
                    if (isset($plugin_info['img'])) {
                        $p['img'] = $plugin_info['img'];
                    }
                }
            }
            unset($p);
        }
        $this->view->assign('plugins', $plugins);
        $this->view->assign('installer', $this->getUser()->getRights('installer', 'backend'));
    }
}

