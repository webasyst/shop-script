<?php

class shopMarketingPromoWorkflow
{
    /**
     * @var shopOrder
     */
    protected $order;

    /**
     * @var array
     */
    protected $active_promos;

    /**
     * @var shopPromoModel
     */
    protected $promo_model;

    /**
     * @var shopPromoOrdersModel
     */
    protected $promo_orders_model;

    /**
     * @var array
     */
    protected $related_promos_rule_ids = [];

    public function __construct(shopOrder $order, $active_promos = [])
    {
        $order_id = $order->getId();
        if (empty($order_id)) {
            throw new waException(_w('Order not found'), 404);
        }

        $this->order = $order;

        $promo_params = [
            'status'        => shopPromoModel::STATUS_ACTIVE,
            'ignore_paused' => true,
            'with_routes'   => true,
            'with_rules'    => true,
        ];

        $this->active_promos = !empty($active_promos) ? $active_promos : $this->getPromoModel()->getList($promo_params);
    }

    /**
     * Running an order compliance check for a promo.
     * @return array with promo ids for which is checked the relations with the current order. Value means related result.
     */
    public function run()
    {
        $this->related_promos_rule_ids = [];

        if (empty($this->active_promos)) {
            return [];
        }

        foreach ($this->active_promos as $promo) {
            if (empty($promo['rules'])) {
                continue;
            }

            foreach ($promo['rules'] as $rule) {
                $order_storefront_for_promo = $this->validateStorefront($promo, $rule);
                if ($order_storefront_for_promo) {
                    $workup_method = $this->getRuleWorkupMethod($rule);
                    if (empty($workup_method)) {
                        continue;
                    }

                    $rule_workup_result = $this->$workup_method($promo, $rule);
                }

                if (!empty($rule_workup_result)) {
                    $this->related_promos_rule_ids[$promo['id']][] = (int)$rule['id'];
                }
            }
        }

        /**
         * Get related promo ids for current order
         *
         * @param shopOrder $order Current order
         * @param array     $active_promos Active promos currently
         *
         * @event promo_workflow_run
         */
        $params = [
            'order'         => $this->order,
            'active_promos' => $this->active_promos,
        ];
        $event_related_promos_rule_ids = (array)wa('shop')->event('promo_workflow_run', $params);

        // Merge related promos by event with related promos by Shop-Script
        foreach ($event_related_promos_rule_ids as $event_promo_rule_ids) {
            foreach ($event_promo_rule_ids as $event_promo_id => $event_rule_ids) {
                $related_promo_rule_ids = ifempty($this->related_promos_rule_ids, $event_promo_id, []);
                $this->related_promos_rule_ids[$event_promo_id] = array_merge($event_rule_ids, $related_promo_rule_ids);
            }
        }

        // Remove not active promos
        foreach ($this->related_promos_rule_ids as $promo_id => $rule_ids) {
            $promo = ifempty($this->active_promos, $promo_id, null);
            if (empty($promo)) {
                unset($this->related_promos_rule_ids[$promo_id]);
            }
        }

        $this->relateOrderWithPromos();

        // Clear data for charts
        (new shopSalesModel())->deletePeriod(null);

        return $this->related_promos_rule_ids;
    }

    protected function relateOrderWithPromos()
    {
        if (empty($this->related_promos_rule_ids)) {
            return false;
        }

        $pom = new shopPromoOrdersModel();
        $res = $pom->relateOrderWithPromos($this->order->getId(), array_keys($this->related_promos_rule_ids));

        if (empty($res)) {
            $this->related_promos_rule_ids = [];
        }

        return $res;
    }

    protected function getRuleWorkupMethod(array $rule)
    {
        $part_of_name = '';
        foreach (explode('_', $rule['rule_type']) as $part) {
            $part_of_name .= ucfirst($part);
        }

        /**
         * @uses shopMarketingPromoWorkflow::workupCustomPriceRule();
         * @uses shopMarketingPromoWorkflow::workupUtmRule();
         * @uses shopMarketingPromoWorkflow::workupCouponRule();
         */
        $method_name = "workup{$part_of_name}Rule";

        if (method_exists($this, $method_name)) {
            return $method_name;
        }

        return null;
    }

    protected function validateStorefront(array $promo, array $rule)
    {
        $order_params = $this->order['params'];
        $order_storefront = ifempty($order_params, 'storefront', null);
        if (empty($order_storefront) || !is_scalar($order_storefront)) {
            return false;
        }

        // Check promo routes and rule params
        $promo_with_out_routes = (empty($promo['routes']) || !is_array($promo['routes']));
        $invalid_rule_params = (empty($rule['rule_params']) || !is_array($rule['rule_params']));
        if ($promo_with_out_routes || $invalid_rule_params) {
            return false;
        }

        $promo_routes = $promo['routes'];

        // Check order and promo storefronts
        $promo_for_all_storefronts = array_key_exists(shopPromoRoutesModel::FLAG_ALL, $promo_routes);
        $order_storefront_not_for_promo = array_key_exists($order_storefront, $promo_routes);
        if (!$promo_for_all_storefronts && !$order_storefront_not_for_promo) {
            return false;
        }
        return true;
    }

    protected function workupCustomPriceRule(array $promo, array $rule)
    {
        $order_items = $this->order['items'];
        $rule_products = $rule['rule_params'];

        // If there is at least one product in the promo that is in the promo -
        // then relate such an order with a promo
        foreach ($order_items as $order_item) {
            if (array_key_exists($order_item['product_id'], $rule_products)) {
                $rule_item = $rule_products[$order_item['product_id']];
                if (!empty($rule_item['skus']) && is_array($rule_item['skus']) && array_key_exists($order_item['sku_id'], $rule_item['skus'])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function workupUtmRule(array $promo, array $rule)
    {
        $order_params = $this->order['params'];
        $rule_params = $rule['rule_params'];

        foreach ($rule_params as $rule_utm_key => $rule_utm_values) {
            if (substr($rule_utm_key, 0, 4) !== 'utm_') {
                // In this cycle, only utm_tags are considered.
                continue;
            }

            if (empty($order_params[$rule_utm_key])) {
                return false;
            }

            $order_utm_value = $order_params[$rule_utm_key];

            if (!in_array($order_utm_value, $rule_utm_values)) {
                return false;
            }
        }

        return true;
    }

    protected function workupCouponRule(array $promo, array $rule)
    {
        $order_params = $this->order['params'];
        $order_coupon_id = ifempty($order_params, 'coupon_id', null);

        $rule_coupons = $rule['rule_params'];

        if (empty($order_coupon_id) || !in_array($order_coupon_id, $rule_coupons)) {
            return false;
        }

        return true;
    }

    /**
     * @return shopPromoModel
     */
    protected function getPromoModel()
    {
        if ($this->promo_model === null) {
            $this->promo_model = new shopPromoModel();
        }

        return $this->promo_model;
    }

    /**
     * @return shopPromoOrdersModel
     */
    protected function getPromoOrdersModel()
    {
        if ($this->promo_orders_model === null) {
            $this->promo_orders_model = new shopPromoOrdersModel();
        }

        return $this->promo_orders_model;
    }
}