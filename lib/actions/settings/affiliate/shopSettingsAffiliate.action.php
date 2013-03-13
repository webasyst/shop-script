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
        $this->view->assign('def_cur_sym', ifset($def_cur['sign'], wa()->getConfig()->getCurrency()));
    }
}

