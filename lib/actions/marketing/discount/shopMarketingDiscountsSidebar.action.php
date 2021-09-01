<?php

class shopMarketingDiscountsSidebarAction extends shopMarketingViewAction
{
    protected $current_type_id = 'coupons';

    public function setTypeId($type_id)
    {
        $this->current_type_id = (string)$type_id;
    }

    public function execute()
    {
        /**
         * Backend discount settings
         *
         * Allows to add custom discount types to discount settings page.
         *
         * Plugins are expected to return one item or a list of items to to add to discounts menu.
         * Each item is represented by an array:
         * array(
         *   'id'     => string,  // Optional discount type ID. Defaults to plugin_id.
         *   'name'   => string,  // Required. Human-readable name of dicount type.
         *   'url'    => string,  // Required (unless you hack into JS using 'html' parameter). Content for settings page is fetched from this URL.
         *   'status' => bool,    // Optional. Whether this discount type is active. Defaults to false (disabled).
         *   'html'   => string,  // Optional. Custom HTML to append to discounts settings page. E.g. for custom JS purposes.
         * )
         *
         * @event backend_settings_discounts
         */
        $plugin_types = wa()->event('backend_settings_discounts');

        $all_types = array();
        foreach($plugin_types + array('shop' => self::getCoreTypes()) as $plugin_id => $types) {
            if (isset($types['name'])) {
                $types['id'] = $plugin_id;
                $types = array($types);
            }
            foreach($types as $i => $type) {
                $type['is_custom'] = ($plugin_id !== 'shop') ? true : false;

                if (empty($type['name'])) {
                    continue;
                }
                if (empty($type['id'])) {
                    $type['id'] = $plugin_id.'_'.$i;
                }

                $type['status'] = ifempty($type['status'], false);

                $all_types[$type['id']] = $type;
            }
        }

        $this->view->assign(array(
            'current_type_id'      => $this->current_type_id,
            'types'                => $all_types,
            'combiner'             => wa()->getSetting('discounts_combine', 'max'),
            'discount_description' => wa()->getConfig()->getOption('discount_description'),
        ));
    }

    protected static function getCoreTypes()
    {
        $marketing_url = shopMarketingViewAction::getMarketingUrl();

        $core_types = array(
            array(
                'id' => 'coupons',
                'name' => _w('Coupons'),
                'url' => $marketing_url.'discounts/coupons/',
            ),
            array(
                'id' => 'category',
                'name' => _w('By customer category'),
                'url' => $marketing_url.'discounts/category/',
            ),
            array(
                'id' => 'order_total',
                'name' => _w('By order total'),
                'url' => $marketing_url.'discounts/order_total/',
            ),
            array(
                'id' => 'customer_total',
                'name' => _w('By customers overall purchases'),
                'url' => $marketing_url.'discounts/customer_total/',
            ),
        );

        $result = array();
        foreach ($core_types as $d) {
            $d['status'] = shopDiscounts::isEnabled($d['id']);
            $result[$d['id']] = $d;
        }

        return $result;
    }
}
