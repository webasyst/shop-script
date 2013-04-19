<?php

/**
 * Single customer details.
 */
class shopCustomersInfoAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id');

        $scm = new shopCustomerModel();
        $customer = $scm->getById($id);

        try {
            $contact = new waContact($id);
            $contact->getName();
        } catch (waException $e) {
            // !!! What to do when shop_customer exists, but no wa_contact found?
            throw $e;
        }

        $ccsm = new waContactCategoriesModel();
        $contact_categories = $ccsm->getContactCategories($id);

        $contacts_url = wa()->getAppUrl('contacts');

        // Info above tabs
        $top = array();
        foreach (array('email', 'phone', 'im') as $f) {
            if ( ( $v = $contact->get($f, 'top,html'))) {
                $top[] = array(
                    'id' => $f,
                    'name' => waContactFields::get($f)->getName(),
                    'value' => is_array($v) ? implode(', ', $v) : $v,
                );
            }
        }

        // Get photo
        $photo = $contact->get('photo');
        $config = $this->getConfig();
        $use_gravatar     = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');
        if (!$photo && $use_gravatar) {
            $photo = shopHelper::getGravatar($contact->get('email', 'default'), 96, $gravatar_default);
        } else {
            $photo = $contact->getPhoto(96);
        }
        $contact['photo'] = $photo;

        // Customer orders
        $om = new shopOrderModel();
        $im = new shopOrderItemsModel();
        $orders = $om->getList('*,params', array( // shipping_name,shipping_plugin_id,payment_name,payment_plugin_id
            'where' => array('contact_id' => $id),
        ));
        shopHelper::workupOrders($orders);
        foreach($orders as &$o) {
            $o['items'] = array();
            foreach($im->getItems($o['id'], true) as $i) {
                $o['items'][] = array(
                    'name' => $i['item']['name'],
                    'quantity' => $i['item']['quantity'],
                );
            }
            $o['total_formatted'] = waCurrency::format('%{s}', $o['total'], $o['currency']);
            $o['shipping_name'] = ifset($o['params']['shipping_name'], '');
            $o['payment_name'] = ifset($o['params']['payment_name'], '');
            // !!! TODO: shipping and payment icons
        }

        // Customer reviews
        $prm = new shopProductReviewsModel();
        $reviews = $prm->getList('*,is_new,product', array(
            'escape' => false,
            'where'  => array('contact_id' => $id),
            'limit'  => false
        ));

        // Customer affiliate transactions history
        $atm = new shopAffiliateTransactionModel();
        $affiliate_history = $atm->getByContact($id);

        $this->view->assign('top', $top);
        $this->view->assign('orders',  $orders);
        $this->view->assign('reviews', $reviews);
        $this->view->assign('contact', $contact);
        $this->view->assign('customer', $customer);
        $this->view->assign('contacts_url', $contacts_url);
        $this->view->assign('affiliate_history', $affiliate_history);
        $this->view->assign('contact_categories', $contact_categories);
        $this->view->assign('def_cur_tmpl', str_replace('0', '%s', waCurrency::format('%{s}', 0, wa()->getConfig()->getCurrency())));
        $this->view->assign('point_rate', str_replace(',', '.', (float) str_replace(',', '.', wa()->getSetting('affiliate_usage_rate'))));
        $this->view->assign('fields', waContactFields::getAll('person'));
    }
}

