<?php

class shopContactsDeleteHandler extends waEventHandler
{
    /**
     * @param int[] $params Deleted contact_id
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $contact_ids = $params;

        // We need some info about (not yet) deleted contacts to save into order params.
        // The idea is to pretend that orders were created by guests with no auth.
        $c = new waContactsCollection('id/'.implode(',', $contact_ids));
        $contacts = $c->getContacts('name,phone,email');
        foreach ($contacts as &$contact) {
            if (is_array($contact['phone'])) {
                $phone = reset($contact['phone']);
                if (is_array($phone)) {
                    if (isset($phone['value'])) {
                        $phone = $phone['value'];
                    } else {
                        $phone = '';
                    }
                }
                $contact['phone'] = $phone;
            }
            if (is_array($contact['email'])) {
                $email = reset($contact['email']);
                $contact['email'] = $email;
            }
        }

        $order_model = new shopOrderModel();
        $order_params_model = new shopOrderParamsModel();
        $product_reviews_model = new shopProductReviewsModel();
        foreach ($contacts as $contact) {
            // Insert customer info into params of their orders
            $order_ids = array_keys($order_model->select('id')->where('contact_id=:contact_id', array('contact_id' => $contact['id']))->fetchAll('id'));
            $order_params_model->set($order_ids, $this->extractContactInfo($contact), false);

            // Insert contact name into their reviews
            $product_reviews_model->updateByField('contact_id', $contact['id'], array(
                'contact_id' => 0,
                'name' => $contact['name'],
                'auth_provider' => null
            ));
        }

        // Update orders as if they were created by guests with no auth
        $order_model->updateByField('contact_id', $contact_ids, array('contact_id' => null));

        // Forget the customer
        $scm = new shopCustomerModel();
        $scm->deleteById($contact_ids);

        // Forget that this user created coupons
        $coupm = new shopCouponModel();
        $coupm->updateByField('create_contact_id', $contact_ids, array(
            'create_contact_id' => 0,
        ));

        // !!! TODO: take a look to other models related with contacts

        /**
         * @event contacts_delete
         * @param array[] int $contact_ids array of contact's ID
         * @return void
         */
        wa()->event(array('shop', 'contacts_delete'), $params);

    }

    public function extractContactInfo($contact)
    {
        $contact_info = array();
        if (!empty($contact['name'])) {
            $contact_info['contact_name'] = $contact['name'];
        }
        if (!empty($contact['phone'])) {
            $contact_info['contact_phone'] = $contact['phone'];
        }
        if (!empty($contact['email'])) {
            $contact_info['contact_email'] = $contact['email'];
        }
        return $contact_info;
    }
}