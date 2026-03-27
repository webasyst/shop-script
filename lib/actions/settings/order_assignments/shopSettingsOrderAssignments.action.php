<?php

class shopSettingsOrderAssignmentsAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign([
            'actions'          => $this->getActions(),
            'conditions'       => self::getConditions(),
            'assignments'      => $this->getAssignments(),
            'assignment_rules' => $this->getRules(),
            'storefronts'      => shopStorefrontList::getAllStorefronts(),
            'sales_channel'    => $this->getSalesChannel(),
            'payments'         => $this->getPayments(),
            'shipping'         => $this->getShipping(),
            'customer_groups'  => $this->getCustomerGroups(),
            'user_groups'      => $this->getTeamGroups(),
            'product_types'    => $this->getProductTypes(),
            'stocks'           => shopHelper::getStocks()
        ]);
    }

    public static function getConditions()
    {
        return [
            ''                     => _w('Add condition...'),
            'by_storefront'        => _w('Storefront'),
            'by_amount'            => _w('Order total'),
            'by_channel_id'        => _w('Sales channel'),
            'by_payment_id'        => _w('Payment'),
            'by_shipping_id'       => _w('Shipping'),
            'by_customer_group_id' => _w('Customer category'),
            'by_prod_type_id'      => _w('Product in the order'),
            'by_sku_id'            => _w('SKU in the order'),
            'by_stock_id'          => _w('Stock'),
        ];
    }

    private function getActions()
    {
        $workflow = new shopWorkflow();
        $actions = $workflow->getAvailableActions();

        return array_combine(array_keys($actions), array_column($actions, 'name'));
    }

    private function getAssignments()
    {
        return [
            'user_action'   => _w('Action performer'),
            'user_id'       => _w('Specific user...'),
            'user_low_busy' => _w('Least busy user'),
            'user_reset'    => _w('Remove assignment'),
        ];
    }

    private function getSalesChannel()
    {
        $sales_channel_model = new shopSalesChannelModel();
        $sales_channel = $sales_channel_model->getAll();

        return array_combine(array_column($sales_channel, 'id'), array_column($sales_channel, 'name'));
    }

    private function getPayments()
    {
        $plugin_model = new shopPluginModel();
        $payments = $plugin_model->listPlugins('payment');

        return array_combine(array_keys($payments), array_column($payments, 'name'));
    }

    private function getShipping()
    {
        $plugin_model = new shopPluginModel();
        $shipping = $plugin_model->listPlugins('shipping');

        return array_combine(array_keys($shipping), array_column($shipping, 'name'));
    }

    private function getCustomerGroups()
    {
        $categories = [];
        $ccm = new waContactCategoryModel();
        foreach ($ccm->getAll('id') as $c) {
            if ($c['app_id'] == 'shop') {
                $categories[$c['id']] = ifset($c, 'name', '');
            }
        }

        return $categories;
    }

    private function getProductTypes()
    {
        $type_model = new shopTypeModel();
        $product_types = $type_model->getTypes();

        return array_combine(array_keys($product_types), array_column($product_types, 'name'));
    }

    private function getTeamGroups()
    {
        $groups = shopHelper::getTeamGroups();
        $groups[] = [
            'id' => 'all',
            'name' => _w('All users'),
        ];

        return $groups;
    }

    private function getRules()
    {
        $assign_rules_model = new shopOrderAssignRulesModel();
        $rules = $assign_rules_model->getRules();

        foreach ($rules as &$_rule) {
            if (isset($_rule['rule_data']['user_id'])) {
                $user = new waContact($_rule['rule_data']['user_id']);
                $_rule['rule_data']['user_name'] = waContactNameField::formatName($user);
            }
        }

        return $rules;
    }
}
