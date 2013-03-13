<?php

abstract class shopCheckout
{
    protected $step_id;

    abstract public function display();

    public function validate()
    {

    }

    abstract public function execute();


    protected function getSessionData($key, $default = null)
    {
        $data = wa()->getStorage()->get('shop/checkout');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    protected function setSessionData($key, $value)
    {
        $data = wa()->getStorage()->get('shop/checkout', array());
        $data[$key] = $value;
        wa()->getStorage()->set('shop/checkout', $data);
    }

    protected function getContact()
    {
        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
        } else {
            $contact = $this->getSessionData('contact');
        }
        if ($contact) {
            if (!$contact->get('address.shipping') && $addresses = $contact->get('address')) {
                $contact->set('address.shipping', $addresses[0]);
            }
            if (!$contact->get('address.billing') && $addresses = $contact->get('address.shipping')) {
                $contact->set('address.billing', $addresses[0]);
            }
        }
        return $contact;
    }


    public function getOptions($config)
    {
        return '';
    }

    public function setOptions($config)
    {
        return $config;
    }

}