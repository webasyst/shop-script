<?php

/**
 * Class shopFrontendApiCustomerTokenController
 */
class shopFrontendApiCustomerTokenController extends shopFrontApiJsonController
{
    public function get()
    {
        $this->response = [
            'customer_token' => self::generateToken()
        ];
    }
}
