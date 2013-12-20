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

        $contact_id = $params;
        $om = new shopOrderModel();

        $total_orders = $om->countByField('contact_id', $contact_id);
        if (!$total_orders) {
            return null;
        }

        return array(
            'html' => '',
            'url' => wa()->getAppUrl('shop').'?module=customers&action=profileTab&id='.$contact_id,
            'count' => 0,
            'title' => _wd('shop', 'Shop').($total_orders ? ' ('.$total_orders.')' : ''),
        );
    }
}

