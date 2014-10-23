<?php

/**
 * Discounts settings page.
 * Shows inner sidebar, then loads content via XHR.
 */
class shopSettingsDiscountsAction extends waViewAction
{
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
         *   'id'   => string,  // Optional discount type ID. Defaults to plugin_id.
         *   'name' => string,  // Required. Human-readable name of dicount type.
         *   'url'  => string,  // Required (unless you hack into JS using 'html' parameter). Content for settings page is fetched from this URL.
         *   'status' => bool,  // Optional. Whether this discount type is active. Defaults to false (disabled).
         *   'html' => string,  // Optional. Custom HTML to append to discounts settings page. E.g. for custom JS purposes.
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

        $this->view->assign('types', $all_types);
        $this->view->assign('combiner', wa()->getSetting('discounts_combine', 'max'));
    }

    protected static function getCoreTypes()
    {
        $core_types = array(
            array(
                'id' => 'coupons',
                'name' => _w('Coupons'),
                'url' => '?module=settings&action=discountsCoupons',
            ),
            array(
                'id' => 'category',
                'name' => _w('By contact category'),
                'url' => '?module=settings&action=discountsCategory',
            ),
            array(
                'id' => 'order_total',
                'name' => _w('By order total'),
                'url' => '?module=settings&action=discountsOrderTotal',
            ),
            array(
                'id' => 'customer_total',
                'name' => _w('By customers overall purchases'),
                'url' => '?module=settings&action=discountsCustomerTotal',
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

