<?php

/**
 * Class shopFrontendApiCustomerTokenController
 */
class shopFrontendApiCustomerTokenController extends shopFrontApiJsonController
{
    public function get()
    {
        $customer_token = $this->getRequest()->request('customer_token', '', waRequest::TYPE_STRING_TRIM);
        if (empty($customer_token)) {
            $customer_token = self::generateToken();
        }

        $this->response = [
            'customer_token' => $customer_token,
        ];

        $antispam_cart_key = shopApiCart::getAntispamCartKey($customer_token);
        if ($antispam_cart_key !== null) {
            $this->response['antispam_cart_key'] = $antispam_cart_key;
        }

        $checkout_url = $this->getStorefrontCheckoutUrl($customer_token);
        if ($checkout_url) {
            $this->response['checkout_url'] = $checkout_url;
        }
    }
}
