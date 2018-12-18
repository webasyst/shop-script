<?php

/**
 * Single customer details.
 */
class shopCustomersInfoAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id', null, waRequest::TYPE_INT);

        $scm = new shopCustomerModel();
        $customer = $scm->getById($id);

        try {
            $contact = new waContact($id);
            $contact->getName();
        } catch (waException $e) {
            // !!! What to do when shop_customer exists, but no wa_contact found?
            throw $e;
        }

        $contact_categories = $this->getContactCategories($id);

        $contacts_url = wa()->getAppUrl('contacts');

        // Info above tabs
        $top = array();
        foreach (array('email', 'phone', 'im') as $f) {
            if (($v = $contact->get($f, 'top,html'))) {
                $top[] = array(
                    'id'    => $f,
                    'name'  => waContactFields::get($f)->getName(),
                    'value' => is_array($v) ? implode(', ', $v) : $v,
                );
            }
        }

        // Get photo
        $photo = $contact->get('photo');
        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */
        $use_gravatar = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');
        if (!$photo && $use_gravatar) {
            $photo = shopHelper::getGravatar($contact->get('email', 'default'), 96, $gravatar_default);
        } else {
            $photo = $contact->getPhoto(96);
        }
        $contact['photo'] = $photo;

        // Customer orders info
        $orders_collection = new shopOrdersCollection('search/contact_id='.$id);
        $total_paid_sum = $orders_collection->getTotalPaidSum();
        $total_paid_num = $orders_collection->getTotalPaidNum();
        $days_ago = $this->getLastOrderDaysAgo($orders_collection);

        $primary_currency = $config->getCurrency();

        if (abs($customer['total_spent'] - $total_paid_sum) > 1) {
            $scm->recalcTotalSpent($id);
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

        if (!empty($contact['password'])) {
            $llm = new waLoginLogModel();
            $contact['last_login_datetime'] = $llm->select('datetime_in')->where('contact_id = i:contact_id', array(
                'contact_id' => $contact['id']
            ))->order('id DESC')->limit(1)->fetchField();
        }

        $shipping_address = $this->getAddressForMap($contact, 'shipping');
        $shipping_map = $shipping_address ? $this->getMap(null, $shipping_address) : '';

        $billing_address = $this->getAddressForMap($contact, 'billing');
        $billing_map = $billing_address && $billing_address !== $shipping_address ? $this->getMap(null, $billing_address) : '';

        $this->view->assign('shipping_address', $shipping_address);
        $this->view->assign('shipping_map', $shipping_map);
        $this->view->assign('billing_address', $billing_address);
        $this->view->assign('billing_map', $billing_map);

        $this->view->assign('top', $top);
        $this->view->assign('orders_html', $this->getOrdersHtml($id));
        $this->view->assign('reviews', $reviews);
        $this->view->assign('contact', $contact);
        $this->view->assign('customer', $customer);
        $this->view->assign('contacts_url', $contacts_url);
        $this->view->assign('affiliate_history', $affiliate_history);
        $this->view->assign('contact_categories', $contact_categories);
        $this->view->assign('def_cur_tmpl', str_replace('0', '%s', waCurrency::format('%{h}', 0, $primary_currency)));
        $this->view->assign('point_rate', str_replace(',', '.', (float)str_replace(',', '.', wa()->getSetting('affiliate_usage_rate'))));
        $fields = waContactFields::getAll('person');
        if (isset($fields['name'])) {
            unset($fields['name']);
        }
        $this->view->assign('fields', $fields);
        $this->view->assign('orders_default_view', $config->getOption('orders_default_view'));
        $this->view->assign('total_paid_sum', waCurrency::format('%{s}', $total_paid_sum, $primary_currency));
        $this->view->assign('total_paid_num', $total_paid_num);

        $total_paid_str = $this->getTotalPaidStr($total_paid_sum, $total_paid_num);
        $this->view->assign('total_paid_str', $total_paid_str);

        $this->view->assign('days_ago', (int)$days_ago);

        /*
         * @event backend_customer
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['info_section'] html output
         * @return array[string][string]string $return[%plugin_id%]['name_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['header'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_link'] html output
         */
        $this->view->assign('backend_customer', wa()->event('backend_customer', $customer));
    }

    public function getContactCategories($id)
    {
        $all_categories = shopCustomer::getAllCategories();
        $customer = new shopCustomer($id);
        $contact_categories = $customer->getCategories();
        foreach ($contact_categories as $category) {
            if (isset($all_categories[$category['id']])) {
                $all_categories[$category['id']]['checked'] = true;
            }
        }
        return $all_categories;
    }

    /**
     * @param waContact $contact
     * @param $ext
     * @return string
     */
    public function getAddressForMap($contact, $ext)
    {
        $address = array();
        $addresses = $contact->get('address.'.$ext);
        if ($addresses) {
            foreach ((array)$addresses as $adr) {
                if (!empty($adr['data'])) {
                    $address = $adr['data'];
                    break;
                }
            }
        }
        if ($address) {
            $address_f = array();
            foreach (array('country', 'region', 'zip', 'city', 'street') as $k) {
                if (empty($address[$k])) {
                    continue;
                } elseif ($k == 'country') {
                    $address_f[$k] = waCountryModel::getInstance()->name(ifempty($address['country']));
                } elseif ($k == 'region') {
                    $address_f['region'] = '';
                    if (!empty($address['country']) && !empty($address['region'])) {
                        $model = new waRegionModel();
                        if ($region = $model->get($address['country'], $address['region'])) {
                            $address_f['region'] = $region['name'];
                        }
                    }
                } else {
                    $address_f[$k] = $address[$k];
                }
            }
            return implode(', ', $address_f);
        }
        return '';
    }

    public function getMap($adapter, $address)
    {
        $map = '';
        try {
            $map = wa()->getMap($adapter)->getHTML($address, array(
                'width'  => '200px',
                'height' => '200px',
                'zoom'   => 13,
                'static' => true,
            ));
        } catch (waException $e) {
        }
        return $map;
    }

    public function getTotalPaidStr($total_paid_sum, $total_paid_num)
    {
        $adapter = waLocale::getAdapter();
        $str = $adapter->ngettext('<strong>%s</strong> on %d paid order', '<strong>%s</strong> on %d paid orders', $total_paid_num);
        return sprintf($str, shop_currency_html($total_paid_sum), $total_paid_num);
    }

    protected function getLastOrderDaysAgo(shopOrdersCollection $collection)
    {
        // assume that collection ordered by 'create_datetime'
        $orders = $collection->getOrders('*', 0, 1);
        if (!$orders) {
            return null;
        }
        $orders = array_values($orders);
        $order = $orders[0];
        $create_date = date('Y-m-d 00:00:00', strtotime($order['create_datetime']));
        $now_date = date('Y-m-d 00:00:00');
        return (strtotime($now_date) - strtotime($create_date)) / 86400;
    }

    protected function getOrdersHtml($id)
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $action = new shopCustomersOrdersAction(array('id' => $id));
        $html = $action->display();
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }
}
