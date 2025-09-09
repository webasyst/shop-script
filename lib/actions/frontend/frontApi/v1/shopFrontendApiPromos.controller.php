<?php

/**
 * Class shopFrontendApiPromosController
 */
class shopFrontendApiPromosController extends shopFrontApiJsonController
{
    public function get()
    {
        $this->response = [];
        $promo_formatter = new shopFrontApiPromoFormatter();
        foreach ($this->promos() as $promo) {
            $this->response[] = $promo_formatter->format($promo);
        }
    }

    public function promos()
    {
        $storefront = shopPromoRoutesModel::FLAG_ALL;
        if ($domain = wa()->getRouting()->getDomain()) {
            $routing_url = wa()->getRouting()->getRootUrl();
            $storefront = $domain.($routing_url ? '/'.$routing_url : '');
        }

        $list_params = [
            'status'        => shopPromoModel::STATUS_ACTIVE,
            'ignore_paused' => true,
            'with_rules'    => true,
            'storefront'    => $storefront
        ];

        $host_url = rtrim(wa()->getConfig()->getHostUrl(), '/');
        $promos = (array) (new shopPromoModel())->getList($list_params);
        foreach ($promos as &$promo) {
            foreach ($promo['rules'] as &$rule) {
                if ($rule['rule_type'] !== 'banner') {
                    unset($rule['rule_params']);
                    continue;
                }
                if ($promo_banner = array_shift($rule['rule_params']['banners'])) {
                    $promo_banner['image'] = $host_url.shopPromoBannerHelper::getPromoBannerUrl($promo['id'], $promo_banner['image_filename']);
                    $rule['rule_params']['banners'][] = $promo_banner;
                }
            }
            $promo['rules'] = array_values($promo['rules']);
        }

        return array_values($promos);
    }
}
