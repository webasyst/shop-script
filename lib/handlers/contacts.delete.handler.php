<?php

class shopContactsDeleteHandler extends waEventHandler
{
    /**
     * @param int[] $params Deleted contact_id
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute($params)
    {
        // TODO: take a look to other models related with contacts

        $c = new waContactsCollection('id/'.implode(',', $params));
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

        $product_reviews_model = new shopProductReviewsModel();
        $order_model = new shopOrderModel();
        $order_params_model = new shopOrderParamsModel();
        foreach ($contacts as $contact) {
            $product_reviews_model->updateByField('contact_id', $contact['id'],
                array(
                    'contact_id' => 0,
                    'name' => $contact['name'],
                    'auth_provider' => null
                )
            );

            $order_ids = array_keys($order_model->select('id')->where('contact_id=:contact_id', array('contact_id' => $contact['id']))->fetchAll('id'));
            $order_params_model->set($order_ids, $this->extractContactInfo($contact));
            $order_model->updateByField('contact_id', $contact['id'], array('contact_id' => null));
        }

        $scm = new shopCustomerModel();
        $scm->deleteById($params);
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