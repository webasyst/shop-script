<?php
/**
 * Tabs with additional info in contact profile page.
 */
class shopContactsProfileTabHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            return null;
        }

        $contact_id = (is_array($params) ? ifset($params, 'id', 0) : $params);
        $counter_inside = is_array($params) ? ifset($params, 'counter_inside', true) : waRequest::param('profile_tab_counter_inside', true);
        $om = new shopOrderModel();

        $total_orders = $om->countByField('contact_id', $contact_id);

        return array(
            'html' => '',
            'url' => wa()->getAppUrl('shop').'?module=customers&action=profileTab&id='.$contact_id,
            'count' => ($counter_inside ? 0 : $total_orders),
            'title' => _wd('shop', 'Shop').($counter_inside && $total_orders ? ' ('.$total_orders.')' : ''),
        );
    }
}

