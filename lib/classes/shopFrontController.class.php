<?php

class shopFrontController extends waFrontController
{
    protected function runController($controller, $params = null)
    {
        $class = get_class($controller);
        if ($class === 'waDefaultViewController' && $controller->getAction()) {
            $class = $controller->getAction();
            if (is_object($class)) {
                $class = get_class($class);
            }
        }
        $evt_params = array(
            'controller' => $controller,
            'params' => &$params,
        );
        $handled = wa('shop')->event('controller_before.'.$class, $evt_params);
        if ($handled) {
            return;
        }
        $result = parent::runController($controller, $params);
        $evt_params['result'] = $result;
        wa('shop')->event('controller_after.'.$class, $evt_params);
        return $evt_params['result'];
    }
}
