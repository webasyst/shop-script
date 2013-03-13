<?php

/**
 * Discounts settings page.
 * Shows inner sidebar, then loads content via XHR.
 */
class shopSettingsDiscountsAction extends waViewAction
{
    public function execute()
    {
        // !!! TODO: plugins hook
        $plugin_html = array();
        $plugin_types = array();

        $this->view->assign('types', $this->getTypes($plugin_types));
        $this->view->assign('combiner', wa()->getSetting('discounts_combine', 'max'));
        $this->view->assign('plugin_html', $plugin_html);
    }

    protected static function getTypes($plugin_types)
    {
        $result = array(
            array(
                'id' => 'coupons',
                'name' => _w('Coupons'),
            ),
            array(
                'id' => 'category',
                'name' => _w('By contact category'),
            ),
            array(
                'id' => 'order_total',
                'name' => _w('By order total'),
            ),
            array(
                'id' => 'customer_total',
                'name' => _w('By customers overall purchases'),
            ),
        );

        $result = array_merge($result, $plugin_types);
        foreach ($result as &$d) {
            $d['enabled'] = shopDiscounts::isEnabled($d['id']);
        }
        unset($d);

        return $result;
    }
}
