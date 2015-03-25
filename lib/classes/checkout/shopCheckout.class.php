<?php

/**
 * Abstract class for checkout step
 */
abstract class shopCheckout
{
    protected $step_id;

    abstract public function display();

    abstract public function execute();

    /**
     * Validation
     * @return array
     */
    public function getErrors()
    {
        return array();
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getSessionData($key, $default = null)
    {
        $data = wa()->getStorage()->get('shop/checkout');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    protected function setSessionData($key, $value)
    {
        $data = wa()->getStorage()->get('shop/checkout', array());
        if ($value === null) {
            if (isset($data[$key])) {
                unset($data[$key]);
            }
        } else {
            $data[$key] = $value;
        }
        wa()->getStorage()->set('shop/checkout', $data);
    }

    /**
     * @return waContact
     */
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
        }
        return $contact ? $contact : new waContact();
    }


    public function getOptions($config)
    {
        return '';
    }

    public function setOptions($config)
    {
        return $config;
    }
    
    /**
     * Get number of step in checkout by ID of step. 
     * @param string|null $step_id If null get number of last step + 1
     * @return int|false
     */
    public static function getStepNumber($step_id = null)
    {
        $steps = array_keys(wa('shop')->getConfig()->getCheckoutSettings());
        if ($step_id === null) {
            return count($steps) + 1;
        }
        $n = array_search($step_id, $steps);
        if ($n === false) {
            return false;   // or throw Exception?
        }
        return $n + 1;
    }
}