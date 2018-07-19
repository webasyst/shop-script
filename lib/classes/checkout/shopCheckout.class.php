<?php

/**
 * Abstract class for checkout step
 * @property-read shopCart $cart
 * @property-read shopPluginModel $plugin_model
 *
 */
abstract class shopCheckout
{
    const STEP_CONTACTINFO = 'contactinfo';
    const STEP_PAYMENT = 'payment';
    const STEP_SHIPPING = 'shipping';
    const STEP_CONFIRMATION = 'confirmation';
    const STEP_SUCCESS = 'success';
    const STEP_ERROR = 'error';
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
        $data = wa()->getStorage()->get('shop/checkout');
        if (!is_array($data)) {
            $data = array();
        }
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

    protected function addFlowStep()
    {
        $checkout_flow = new shopCheckoutFlowModel();
        $step_number = shopCheckout::getStepNumber($this->step_id);
// IF no errors
        $checkout_flow->add(array(
            'step' => $step_number,
        ));
// ELSE
//        $checkout_flow->add(array(
//            'step' => $step_number,
//            'description' => ERROR MESSAGE HERE
//        ));
    }

    /**
     * Get number of step in checkout by ID of step.
     * @param string|null $step_id If null get number of last step + 1
     * @return int|false
     */
    public static function getStepNumber($step_id = null)
    {
        $settings = self::getCheckoutSettings();
        $steps = array_keys($settings);
        if ($step_id === null) {
            return count($steps) + 1;
        }
        $n = array_search($step_id, $steps);
        if ($n === false) {
            return false;   // or throw Exception?
        }
        return $n + 1;
    }

    protected function assign($name, $value = null)
    {
        static $view;
        if (!$view) {
            $view = wa()->getView();
        }
        $view->assign($name, $value);
    }

    protected function getControls($custom_fields, $namespace = null)
    {
        $params = array();
        $params['namespace'] = $namespace;
        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<span class="hint">%s</span>';
        //$params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_wrapper'] = '<div class="wa-name">%s</div><div class="wa-value"><p><span>%3$s %2$s</span></p></div>';
        //$params['control_wrapper'] = '<div class="wa-name">%s</div><div class="wa-value">%s %s</div>';
        $params['control_separator'] = '</span><span>';

        $controls = array();
        foreach ($custom_fields as $name => $row) {
            $row = array_merge($row, $params);
            $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
        }

        return $controls;
    }

    protected static function getCheckoutSettings($name = null)
    {
        static $settings;
        if (!$settings) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $settings = $config->getCheckoutSettings();
        }

        return $name ? ifset($settings[$name]) : $settings;
    }

    public function __get($name)
    {
        static $instances = array();
        $value = null;
        if (!isset($instances[$name])) {
            switch ($name) {
                case 'cart':
                    $instances[$name] = new shopCart();
                    break;
                case 'plugin_model':
                    $instances[$name] = new shopPluginModel();
                    break;

            }
        }
        return isset($instances[$name]) ? $instances[$name] : null;
    }
}
