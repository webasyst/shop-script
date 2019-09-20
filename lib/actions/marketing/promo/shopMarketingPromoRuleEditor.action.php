<?php

class shopMarketingPromoRuleEditorAction extends waViewAction
{
    protected $options;
    protected $rule_type;
    protected $rule_id;
    protected $rule;

    protected function preExecute()
    {
        $this->options = waRequest::post('options', [], waRequest::TYPE_ARRAY_TRIM);
        $this->rule_type = waRequest::post('rule_type', null, waRequest::TYPE_STRING_TRIM);
        $this->rule_id = waRequest::post('rule_id', null, waRequest::TYPE_INT);
    }

    public function execute()
    {
        $promo_rules_model = new shopPromoRulesModel();
        $this->rule = !empty($this->rule_id) ? $promo_rules_model->getByField(['id' => $this->rule_id, 'rule_type' => $this->rule_type]) : null;

        $this->view->assign([
            'marketing_url' => shopMarketingViewAction::getMarketingUrl(),
            'options'       => $this->options,
            'rule_type'     => $this->rule_type,
            'rule_id'       => $this->rule_id,
            'rule'          => $this->rule,
        ]);

        $shop_rule_types = $promo_rules_model->getAvailableShopTypes();
        if (!isset($shop_rule_types[$this->rule_type])) {
            $html = '';
            wa('shop')->event('promo_rule_editor', ref([
                'options'   => $this->options,
                'rule_type' => $this->rule_type,
                'rule_id'   => $this->rule_id,
                'rule'      => $this->rule,
                'html'      => &$html,
            ]));
            $this->view->assign('html', $html);
        }

        $part_of_name = '';
        foreach (explode('_', $this->rule_type) as $part) {
            $part_of_name .= ucfirst($part);
        }
        $method_name = "workup{$part_of_name}Rule";
        if (method_exists($this, $method_name)) {
            $this->$method_name();
        }
    }

    protected function workupCustomPriceRule()
    {
        $products_data = [];

        $product_ids = array_keys(ifempty($this->rule, 'rule_params', []));
        if (!empty($this->rule) && !empty($product_ids)) {
            $hash = 'id/'.join(',', $product_ids);
            $collection = new shopProductsCollection($hash);
        }

        if (empty($this->rule_id) && !empty($this->options['products_hash'])) {
            $collection = new shopProductsCollection($this->options['products_hash']);
        }

        if (!empty($collection)) {
            $products_data = $collection->getProducts('id,name,images,currency,skus');
        }

        $this->view->assign([
            'products' => $products_data,
        ]);
    }

    protected function workupCouponRule()
    {
        $scm = new shopCouponModel();
        $selected_coupon_ids = ifempty($this->rule, 'rule_params', []);
        $coupons = $scm->getById($selected_coupon_ids);

        $max_coupons_count = 10;
        if ($max_coupons_count > count($coupons)) {
            $additional_coupons_count = $max_coupons_count - count($coupons);
            $where = 'id';
            if (!empty($coupons)) {
                $where = 'id NOT IN (?)';
            }
            $additional_coupons = $scm->where($where, array_keys($coupons))
                                      ->limit($additional_coupons_count)
                                      ->order('create_datetime DESC')
                                      ->fetchAll('id');
            $coupons = array_merge($coupons, $additional_coupons);
        }

        usort($coupons, function($c1, $c2) {
            $t1 = strtotime($c1['create_datetime']);
            $t2 = strtotime($c2['create_datetime']);
            return $t2 - $t1;
        });

        $this->view->assign([
            'selected_coupon_ids' => $selected_coupon_ids,
            'coupons'             => $coupons,
        ]);
    }
}