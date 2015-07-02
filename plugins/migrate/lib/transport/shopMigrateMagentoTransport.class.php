<?php

/**
 * @link http://www.magentocommerce.com/api/rest/permission_settings/permission_settings.html
 *
 *
 * Class shopMigrateDummyTransport
 * @title Magento
 * @description Code prototype for custom migrate cases
 * @link http://www.magentocommerce.com/api/rest/introduction.html Magento API
 * @link http://www.magentocommerce.com/api/rest/authentication/oauth_authentication.html
 *
 * http://tools.ietf.org/html/rfc5849#section-3.3
 *
 *
 * @help http://www.magentocommerce.com/api/rest/authentication/oauth_configuration.html#OAuthConfiguration-AddingaNewConsumer
 * @link http://tools.ietf.org/html/rfc6749 OAuth 2.0
 */
class shopMigrateMagentoTransport extends shopMigrateTransport
{
    const API_PRODUCT_PER_PAGE = 5;//The maximum number is 100 items. 10 is default
    const API_CUSTOMER_PER_PAGE = 5;//The maximum number is 100 items. 10 is default
    const API_ORDER_PER_PAGE = 5;//The maximum number is 100 items. 10 is default

    protected function initOptions()
    {
        waHtmlControl::registerControl('RepeatControl', array(&$this, "settingRepeatControl"));
        waHtmlControl::registerControl('StatusControl', array(&$this, "settingStatusControl"));
        $this->addOption('url', array(
            'value'        => '',
            'title'        => _wp('Magento URL'),
            'class'        => 'long',
            'description'  => _wp('Enter the URL of your Magento storefront. Make sure to enter storefront URL, not admin URL.'),
            'placeholder'  => 'http://your-magento-store-url.com',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('admin_path', array(
            'value'        => 'admin',
            'title'        => _wp('Admin mode path'),
            'description'  => _wp('Path to Magento admin (backend)'),
            'placeholder'  => 'admin',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('consumer_key', array(
            'title'        => _wp('Consumer Key'),
            'value'        => '',
            'placeholder'  => '',
            'class'        => 'long',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('consumer_secret', array(
            'title'        => _wp('Consumer Secret'),
            'value'        => '',
            'class'        => 'long',
            'description'  => _wp('Obtain your Consumer Key and Secret in your Magento admin mode as such.<br><br>

1) Create <b>REST role</b>:

<ol><li>Log in to the Magento Admin Panel as an administrator.</li>
<li>Click <strong>System</strong> &gt; <strong>Web Services</strong> &gt; <strong>REST - Roles</strong>.</li>
<li>On the REST—Roles page, click <strong>Add Admin Role</strong>.</li>
<li>In the <strong>Role Name</strong> field, enter some name, e.g. <code>shopscript</code>.</li>
<li>Click <strong>Save Role</strong>.</li>
<li>In the left navigation bar, click <strong>Role API Resources</strong>.<br>
The Role Resources page contains a hierarchical list of resources to which you can grant or deny the <code>shopscript</code> role access.</li>
<li>From the <strong>Resource Access</strong> list, click <strong>Custom</strong> and select all checkboxes in the list.</li>
<li>Click <strong>Save Role</strong>.<br>
Magento saves the resource API permissions you granted to this REST role.</li></ol>

2) Now that you have a role, you must <b>add users to give them permission</b> to call the API as follows:

<ol><li>In the left navigation bar, click <strong>Role Users</strong>.</li>
<li>Click <strong>Reset Filter</strong> (in the upper-right corner of the page).<br>
The page displays all registered users as the following figure shows.</li>
<li>Select the check box next to each user to grant the user privileges to access the resources available to the <code>shopscript</code> REST role—that is, permission to call the API.<br>
</li>
<li>When done, click <strong>Save Role</strong>.<br>
The specified user(s) can now grant an external program the right to call the <code>shopscript</code> API.</li></ol>

3) Now you will need to <b>set up REST Attributes for the API</b>:

<ol><li>Click <strong>System</strong> &gt; <strong>Web Services</strong> &gt; <strong>REST - Attributes</strong>.</li>
<li>On the REST Attributes page, under User Type, click <strong>Admin</strong>.</li>
<li>In the User Type Resources section, from the <strong>Resource Access</strong> list, click <strong>Custom</strong> and select all checkboxes in the list.</li>
<li>Click <strong>Save</strong>.<br>
Any user with the REST Admin role can now read from and write to the API.</li></ol>

4) Finally, you will need to <b>create an OAuth Consumer</b> for your script:

<ol><li>In the Magento Admin Panel, click <strong>System</strong> &gt; <strong>Web Services</strong> &gt; <strong>REST - OAuth Consumers</strong>.</li>
<li>Click <strong>Add New</strong> (in the upper-right corner of the page).<br>
The <strong>New Consumer</strong> page displays as the following figure shows.</li>
<li>In the <strong>Name</strong> field, enter <code>shopscript</code>.</li>
<li>Leave the other fields blank.</li>
<li><em>Write down</em> the values displayed in the <strong>Key</strong> and <strong>Secret</strong> text boxes. <br>
<strong>Note</strong>: The key and secret values are stored in the Magento database in the table <code>oauth_consumer</code>. It might be more convenient for you to use <code>phpmyadmin</code> or database tools to retrieve them from the database after you save the role.<br>
You must include these values in the test script you will write in the next section. The script uses these values to identify itself to Magento.</li>
<li>Click <strong>Save</strong> (in the upper-right corner of the page).</li>
<li>Log out of the Magento Admin Panel.</li></ol>

For more information please refer to Magento Manual: <a href="http://devdocs.magento.com/guides/m1x/other/ht_extend_magento_rest_api.html#secure-role" target="_blank">http://devdocs.magento.com/guides/m1x/other/ht_extend_magento_rest_api.html#secure-role</a>'),
            'placeholder'  => '',
            'control_type' => waHtmlControl::INPUT,
            'cache'        => true,
        ));

        $this->addOption('request_verifier', array(
            'title'        => _wp('Verifier code'),
            'value'        => '',
            'class'        => 'long highlighted',
            'placeholder'  => '',
            'control_type' => waHtmlControl::HIDDEN,
        ));

        $this->addOption('repeat', array(
            'value'        => false,
            'control_type' => waHtmlControl::HIDDEN,
        ));
        parent::initOptions();
    }

    public function validate($result, &$errors)
    {
        $result = false;

        # 1. validate and complete URL
        if (!strlen($url = $this->getOption('url'))) {
            $errors['url'] = _wp('Empty URL');
        } else {
            if (!preg_match('@^https?://@', $url)) {
                $url = 'http://'.$url;
            }

            if (!parse_url($url, PHP_URL_HOST)) {
                $errors['url'] = _wp('Invalid URL');
            } else {
                $url = rtrim($url, '/').'/';
                try {
                    if ($this->get($url, null)) {
                        $this->setOption('url', $url);
                    } else {
                        $errors['url'] = sprintf(_wp('Magento installation was not found by this URL: %s'), _wp('empty server response'));
                    }
                } catch (waException $ex) {
                    $errors['url'] = sprintf(_wp('Magento installation was not found by this URL: %s'), $ex->getMessage());
                }
            }
        }

        # 2. validate admin path (not empty)
        if (!strlen($admin_path = $this->getOption('admin_path'))) {
            $errors['admin_path'] = _wp('Empty value');
        } else {
            $this->setOption('admin_path', trim($admin_path, '/'));
        }

        # 3. validate hashes
        $pattern = '@^[0-9a-f]{32}$@i';
        $fields = array(
            'consumer_key',
            'consumer_secret',
            'request_verifier',
        );
        foreach ($fields as $option) {
            if (strlen($value = $this->getOption($option))) {
                if (!preg_match($pattern, $value)) {
                    $errors[$option] = _wp('Invalid value');
                    if ($option == 'request_verifier') {
                        $this->repeatAuth();
                    }
                }
            }
        }
        if (empty($errors)) {
            try {
                #check auth data
                if ($this->auth($errors)) {
                    #default currency
                    $option = array(
                        'control_type' => waHtmlControl::SELECT,
                        'title'        => _wp('Currency'),
                        'options'      => array(),
                    );

                    $currency_model = new shopCurrencyModel();
                    if ($currencies = $currency_model->getAll()) {
                        foreach ($currencies as $currency) {
                            $option['options'][$currency['code']] = $currency['code'];
                        }
                    }
                    $this->addOption('currency', $option);
                    $this->addOption('type', $this->getProductTypeOption());
                    $option = array(
                        'value'        => 'kg',
                        'control_type' => waHtmlControl::SELECT,
                        'title'        => _wp('Weight unit'),
                        'description'  => 'Выберите единицу измерения, в которой задан вес товаров',
                        'options'      => shopDimension::getUnits('weight'),
                    );
                    $this->addOption('weight', $option);

                    $option = array(
                        'control_type' => 'StatusControl',
                        'title'        => _wp('Status map'),
                        'options'      => array(
                            'pending'    => 'Pending',//: Pending orders are brand new orders that have not been processed. Typically, these orders need to be invoiced and shipped.
                            //Pending PayPal: Pending PayPal orders are brand new orders that have not been cleared by PayPal. [...]
                            'processing' => 'Processing',//: Processing means that orders have either been invoiced or shipped, but not both.
                            'complete'   => 'Complete',//: Orders marked as complete have been invoiced and have shipped.
                            'canceled'   => 'Cancelled',//: Cancelled orders should be used if orders are cancelled or if the orders have not been paid for.
                            'closed'     => 'Closed',//: Closed orders are orders that have had a credit memo assigned to it and the customer has been refunded for their order.
                            'on-hold'    => 'On Hold',//: Orders placed on hold must be taken off hold before continuing any further actions.
                        ),
                    );
                    //@todo try to get real list of order states
                    $this->addOption('status', $option);

                    $result = true;
                }
            } catch (waException $ex) {
                if (empty($errors)) {
                    $errors[''] = $ex->getMessage();
                }
            }
        }

        if (!empty($errors['request_verifier'])) {
            $this->addOption('request_verifier', array(
                'control_type' => waHtmlControl::INPUT,
            ));
        }


        return parent::validate($result, $errors);
    }

    public function count()
    {
        return array(
            self::STAGE_PRODUCT  => $this->countSuggest('products'),
            self::STAGE_CUSTOMER => $this->countSuggest('customers'),
            self::STAGE_ORDER    => $this->countSuggest('orders'),
        );
    }

    public function step(&$current, &$count, &$processed, $stage, &$error)
    {
        $method_name = 'step'.ucfirst($stage);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                if ($result && ($processed[$stage] > 10) && ($current[$stage] == $count[$stage])) {
                    $result = false;
                }
            } else {
                $this->log(sprintf("Unsupported stage [%s]", $stage), self::LOG_ERROR);
                $current[$stage] = $count[$stage];
            }
        } catch (Exception $ex) {
            $this->stepException($current, $stage, $error, $ex);
        }

        return $result;
    }


    /**
     * @help http://www.magentocommerce.com/api/rest/Resources/Products/products.html
     * @param $current_stage
     * @param $count
     * @param $processed
     * @return bool
     */
    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        if (!$products) {
            if (!isset($this->map[self::STAGE_PRODUCT])) {
                $this->map[self::STAGE_PRODUCT] = array();
            }
            $products = $this->getProducts($current_stage);
            if (is_array($products) && !count($products)) {
                // correct total count on ids holes
                $current_stage = $count[self::STAGE_PRODUCT];
            }
        }
        if ($data = reset($products)) {
            try {
                $this->addProduct($data);
                ++$processed;
            } catch (waException $ex) {
                $message = sprintf('Error during import product: %s', $ex->getMessage());
                $this->log($message, self::LOG_ERROR, $data);
            }
            ++$current_stage;
            array_shift($products);
        }
        return true;
    }

    private function stepCustomer(&$current_stage, &$count, &$processed)
    {
        static $raw;

        if (!isset($this->map[self::STAGE_CUSTOMER])) {
            $this->map[self::STAGE_CUSTOMER] = array();
        }

        if (!$raw) {
            $raw = $this->getCustomers($current_stage);

            if (is_array($raw) && !count($raw)) {
                // correct total count on ids holes
                $current_stage = $count[self::STAGE_CUSTOMER];
            }
        }

        if ($data = reset($raw)) {
            try {
                if ($this->addCustomer($data)) {
                    ++$processed;
                }

            } catch (waException $ex) {
                $message = sprintf('Error during import customer: %s', $ex->getMessage());
                $this->log($message, self::LOG_ERROR, $data);
            }
            ++$current_stage;
            array_shift($raw);
        }
        return true;
    }

    private function stepOrder(&$current_stage, &$count, &$processed)
    {
        static $raw;
        if (!$raw) {
            $raw = $this->getOrders($current_stage);

            if (is_array($raw) && !count($raw)) {
                // correct total count on ids holes
                $current_stage = $count[self::STAGE_ORDER];
            }
        }

        if ($data = reset($raw)) {
            try {
                if ($this->addOrder($data)) {
                    ++$processed;
                }

            } catch (waException $ex) {
                $message = sprintf('Error during import customer: %s', $ex->getMessage());
                $this->log($message, self::LOG_ERROR, $data);
            }
            ++$current_stage;
            array_shift($raw);
            if ($current_stage == $count[self::STAGE_ORDER]) {
                $this->orderModel()->recalculateProductsTotalSales();
                $sql = <<<SQL
UPDATE shop_order o
JOIN (SELECT contact_id, MIN(id) id
FROM `shop_order`
WHERE paid_date IS NOT NULL
GROUP BY contact_id) AS f
ON o.id = f.id
SET o.is_first = 1
SQL;
                $this->orderModel()->query($sql);
            }
        } else {
            $sql = <<<SQL
UPDATE shop_order o
JOIN (SELECT contact_id, MIN(id) id
FROM `shop_order`
WHERE paid_date IS NOT NULL
GROUP BY contact_id) AS f
ON o.id = f.id
SET o.is_first = 1
SQL;
            $this->orderModel()->query($sql);
        }

        return true;
    }

    private function auth(&$errors)
    {
        $auth = false;
        $fields = array();
        $option = array(
            'valid'    => true,
            'readonly' => true,
        );

        if (!$this->getOption('access_token')) {
            try {
                $request_token = false;
                if (!$this->getOption('request_token') || $this->getOption('repeat')) {
                    $this->setOption('request_token', '');
                    $this->setOption('request_token_secret', '');
                    $this->setOption('access_token', '');
                    $this->setOption('access_token_secret', '');
                    $request_token = $this->getRequestToken($errors);
                }

                $this->setOption('repeat', 0);

                if ($this->getOption('request_token')) {
                    $fields += array(
                        'consumer_key',
                        'consumer_secret',
                        'url',
                        'admin_path',
                    );
                    if ($this->getOption('request_verifier')) {
                        $auth = $this->getAccessToken($errors);
                        $fields += array(
                            'request_verifier',
                        );
                    } elseif (!$request_token) {
                        $errors['request_verifier'] = _wp('Paste Magento verifier code to continue');
                        $this->repeatAuth();
                    } else {
                        $fields += array(
                            'request_verifier',
                        );
                    }
                }
            } catch (waException $ex) {
                if (empty($errors)) {
                    foreach ($fields as $name) {
                        $this->addOption($name, $option);
                    }
                    throw $ex;
                }
            }

        } else {
            $fields += array(
                'consumer_key',
                'consumer_secret',
                'url',
                'admin_path',
                'request_verifier',
            );
            $auth = true;
        }
        foreach ($fields as $name) {
            $this->addOption($name, $option);
        }
        return $auth;
    }

    /**
     * @param string $subject orders|products|customers
     * @param string $field
     * @return int
     * @throws waException
     */
    private function countSuggest($subject, $field = null)
    {
        if (empty($field)) {
            $field = 'entity_id';
        }
        $params = array(
            'limit' => 1,
            'dir'   => 'dsc',
            'order' => $field,
        );

        $count = null;
        if ($data = $this->query($subject, $params)) {
            $entity = reset($data);
            $count = max(0, intval(ifset($entity[$field])));
        }
        return $count;
    }

    private function countFix()
    {

    }

    /**
     * @param $action
     * @param mixed [string] $params page,limit, order & dir=asc|dsc
     * @return mixed
     * @throws waException
     */
    private function query($action, $params = array())
    {
        $url = "{$this->getOption('url')}api/rest/{$action}";
        $params += array(
            'oauth_token' => $this->getOption('access_token'),
        );
        $headers = array(
            'Authorization' => 'Oauth',
            'Accept'        => 'application/json',
        );

        if ($res = $this->get($url, $params, $headers)) {
            $data = @json_decode($res, true);
            if ($data && !empty($data['messages']['error'])) {
                $message = array();
                foreach ($data['messages']['error'] as $error) {
                    $message[] = sprintf("Magento API error #%d: %s", $error['code'], $error['message']);
                }
                if ($message) {
                    throw new waException(implode("\n", $message));
                }
            }
        } else {
            throw new waException('Empty response');
        }

        return $data;
    }


    protected function get($url, $params, $header = array())
    {
        if (!empty($params)) {
            $url = $this->signRequest($url, $params);
        }
        $http_code = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if ($header) {
                foreach ($header as $id => &$_header) {
                    if (!is_int($id)) {
                        $_header = sprintf('%s: %s', $id, $_header);
                    }
                    unset($_header);
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!$response || ($http_code && (!in_array($http_code, array(200, 401))))) {
                $this->log(($error = curl_error($ch)) ? $error : 'Network error', self::LOG_ERROR, compact('http_code', 'params', 'url', 'response'));
                $response = false;
            }

            curl_close($ch);

        } elseif (ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'ignore_errors' => true,
                    'timeout'       => 15.0,
                ),
            ));
            $response = @file_get_contents($url, null, $context);
            if (!empty($http_response_header)) {
                /**
                 * @link http://php.net/manual/en/reserved.variables.httpresponseheader.php
                 * @var string[] $http_response_header
                 */
                foreach ($http_response_header as $header) {
                    if (preg_match('@^status:\s+(\d+)\s+(.+)$@i', $header, $matches)) {
                        $http_code = $matches[1];
                        break;
                    }
                }
            }
            if (!$response || ($http_code && (!in_array($http_code, array(200, 401))))) {
                $error = error_get_last();
                $message = 'network error';
                if ($error && ($error['file'] == __FILE__)) {
                    $message = $error['message'];
                }
                $this->log($message, self::LOG_ERROR, compact('http_code', 'params', 'url', 'response'));
                $response = false;

            }
        } else {
            throw new waException('PHP extension curl or ini option allow_url_fopen required');
        }

        return $response;
    }

    protected function post($url, $post_data, $header = array())
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

            $content = curl_exec($ch);
            curl_close($ch);
            return $content;
        } elseif (ini_get('allow_url_fopen')) {

            $context = stream_context_create(array(
                parse_url($url, PHP_URL_SCHEME) => array(
                    'method'  => 'POST',
                    'header'  => ifempty($header, 'Content-type: application/x-www-form-urlencoded'),
                    'content' => $post_data
                ),
            ));
            return file_get_contents($url, false, $context);
        } else {
            throw new waException('PHP extension curl or ini option allow_url_fopen required');
        }

    }

    private function signRequest($url, $params)
    {
        if (!extension_loaded('hash')) {
            throw new waException('PHP extension hash required');
        }

        if (!in_array('sha1', hash_algos())) {
            throw new waException('Required hash algorithm sha1 not supported');
        }
        $secret = $this->getOption('access_token_secret');
        $secret = array(
            $this->getOption('consumer_secret'),
            ifempty($secret, $this->getOption('request_token_secret', '')),
        );
        $params += array(
            'oauth_consumer_key'     => $this->getOption('consumer_key'),
            'oauth_timestamp'        => $time = time(),
            'oauth_nonce'            => md5(microtime(true).__FILE__.rand()),
            'oauth_signature_method' => "HMAC-SHA1",
        );
        ksort($params);
        $string = 'GET&'.urlencode($url).'&'.urlencode(http_build_query($params));
        $params['oauth_signature'] = base64_encode(hash_hmac('sha1', $string, $key = implode('&', $secret), 1));
        $url .= '?'.http_build_query($params);
        return $url;

    }


    private function handleError($response, &$errors)
    {
        if (empty($response)) {
            $errors['url'] = sprintf(_wp('Magento installation was not found by this URL: %s'), _wp('empty server response'));
            throw new waException(_wp('Empty server response'));
        }
        $problem = ifset($response['oauth_problem']);
        if (!empty($problem)) {
            $message = ifset($response['message']);
            switch ($problem) {
                case 'version_rejected':
                    throw new waException("Incorrect API version: ".$problem);
                    break;
                case 'timestamp_refused':
                    throw new waException("Check server time: timestamp refused");
                    break;
                case 'nonce_used':
                    throw new waException("Nonce used: try again with other nonce");
                    break;
                case 'signature_method_rejected':
                    throw new waException("Incorrect signature method: ".$problem);
                    break;
                case 'signature_invalid':
                    throw new waException("Incorrect  signature");
                    break;
                case 'consumer_key_rejected':
                    $errors['consumer_key'] = 'Value rejected';
                    throw new waException("Incorrect consumer key");
                    break;
                case 'token_used':
                    throw new waException("Token used: try again with other token");
                    break;
                case 'token_revoked':
                    throw new waException("Token revoked");
                    break;
                case 'token_rejected':
                    throw new waException("Token rejected");
                    break;
                case 'verifier_invalid':
                    $errors['request_verifier'] = _wp('Invalid value');
                    $this->repeatAuth();
                    throw new waException("Incorrect verifier code");
                    break;
                case 'parameter_rejected':
                    throw new waException(sprintf("Invalid request param %s=%s", $message, urldecode(ifset($response[$message], ''))));
                    break;
                case 'parameter_absent':
                    throw new waException(sprintf("Missed request param %s", ifset($response['oauth_parameters_absent'])));
                    break;
                case 'internal_error':
                    throw new waException(sprintf('Internal Magento error: %s>', htmlentities($message, ENT_NOQUOTES, 'utf-8')));
                    break;
                default:

                    throw new waException("Unknown error type: ".$problem);
            }
        }
        if (empty($response['oauth_token']) || empty($response['oauth_token_secret'])) {
            $errors['url'] = sprintf(_wp('Magento installation was not found by this URL: %s'), _wp('unexpected server response'));
            throw new waException("Unknown error type: ".$problem);
        }
    }

    private function getAccessToken(&$errors)
    {
        $params = array(
            'oauth_token'    => $this->getOption('request_token'),
            'oauth_verifier' => $this->getOption('request_verifier'),
            'oauth_version'  => '1.0',
        );

        $access_token = array();

        parse_str($this->get("{$this->getOption('url')}oauth/token", $params), $access_token);
        $this->handleError($access_token, $errors);

        if (empty($errors)) {
            $this->setOption('access_token', $access_token['oauth_token']);
            $this->setOption('access_token_secret', $access_token['oauth_token_secret']);
            return true;
        } else {
            return false;
        }

    }


    private function getRequestToken(&$errors)
    {
        $result = false;
        $params = array(
            'oauth_callback' => urlencode('oob'),
        );

        @parse_str($this->get("{$this->getOption('url')}oauth/initiate", $params), $request_token);
        $this->handleError($request_token, $errors);
        if (empty($errors)) {
            $this->setOption('request_token_secret', $request_token['oauth_token_secret']);
            $this->setOption('request_token', $request_token['oauth_token']);

            $redirect = "{$this->getOption('url')}{$this->getOption('admin_path')}/oauth_authorize?oauth_token={$request_token['oauth_token']}";
            $result = true;

            $redirect = htmlentities($redirect, ENT_QUOTES, 'utf-8');
            $description = sprintf(
                _wp(
                    '1. Click this link: <a href="%1$s" target="_blank" style="color: #03c;"><b>%1$s</b><i class="icon10 new-window"></i></a><br> 2. Copy <b>verifier code</b> from the Magento window and paste in the field above.<br> 3. Click <b>Connect</b>. This will grant API access to your data in Magento admin mode.'
                ),
                $redirect
            );

            $this->addOption('request_verifier', array(
                'description'  => $description,
                'control_type' => waHtmlControl::INPUT,
            ));
        }
        return $result;
    }

    private function getProducts($current_stage)
    {
        /**
         * Product Categories
         *
         * Retrieve the list of categories assigned to a product, assign, and unassign the category to/from the specific product.
         * Resource Structure: http://magentohost/api/rest/products/:productId/categories
         *
         * @todo Product Images
         *
         * Retrieve the list of images assigned to a product, add, update, and remove an image to/from the specific product.
         * Resource Structure: http://magentohost/api/rest/products/:productId/images
         *
         * Product Websites
         *
         * Retrieve the list of websites assigned to a product, assign, and unassign a website to/from the specific product.
         * Resource Structure: http://magentohost/api/rest/products/:productId/websites
         */

        $params = array(
            'limit' => self::API_PRODUCT_PER_PAGE,
            'page'  => (int)(floor($current_stage / self::API_PRODUCT_PER_PAGE) + 1),
            'dir'   => 'asc',
            'order' => 'entity_id',
        );

        $products = $this->query('products', array_filter($params));

        if ($offset = $current_stage % self::API_PRODUCT_PER_PAGE) {
            $products = array_slice($products, $offset);
        }

        if (is_array($products) && count($products)) {
            $map = array();
            foreach ($products as $id => $product) {
                $map[$product['entity_id']] = $id;
            }

            $params = array(
                'order'  => 'item_id',
                'filter' => array(
                    '1' => array(
                        'attribute' => 'product_id',
                        'in'        => array_keys($map),
                    ),
                )
            );

            #add stock data
            $items = $this->query('stockitems', array_filter($params));

            foreach ($items as $item) {
                if (isset($map[$item['product_id']])) {
                    $products[$map[$item['product_id']]]['stock_data'] = $item;
                }

            }

            return $products;
        }
    }

    private function getCustomers($current_stage)
    {
        /**
         * @todo Customer Addresses
         * Resource Structure: http://magentohost/api/rest/customers/:customerId/addresses
         */
        $params = array(
            'limit' => self::API_CUSTOMER_PER_PAGE,
            'page'  => (int)(floor($current_stage / self::API_CUSTOMER_PER_PAGE) + 1),
            'dir'   => 'asc',
            'order' => 'entity_id',
        );

        $customers = $this->query('customers', array_filter($params));

        if ($offset = $current_stage % self::API_PRODUCT_PER_PAGE) {
            $customers = array_slice($customers, $offset);
        }

        return $customers;
    }

    private function getOrders($current_stage)
    {
        $params = array(
            'limit' => self::API_ORDER_PER_PAGE,
            'page'  => (int)(floor($current_stage / self::API_ORDER_PER_PAGE) + 1),
            'dir'   => 'asc',
            'order' => 'entity_id',
        );

        $orders = $this->query('orders', array_filter($params));

        if ($offset = $current_stage % self::API_ORDER_PER_PAGE) {
            $orders = array_slice($orders, $offset);
        }

        return $orders;
    }

    private function addProduct($data)
    {
        $p = new shopProduct();
        if ($this->getOption('persistent')) {
            $p->id = (int)$p['id'];
        }

        $p->type_id = $this->getOption('type');

        $p->name = $data['name'];
        $p->description = $data['description'];
        $p->summary = $data['short_description'];

        #meta
        $p->meta_title = ifset($data['meta_title']);
        $p->meta_description = ifset($data['meta_description']);
        $p->meta_keywords = ifset($data['meta_keyword']);

        $p->currency = $this->getOption('currency');

        if (!empty($data['url_key'])) {
            $p->url = $data['url_key'];
        }

        switch ($data['type_id']) {
            case 'simple':
                $sku = array(
                    'sku'       => $data['sku'],
                    'price'     => $data['price'],
                    'stock'     => array(
                        0 => isset($data['stock_data']['qty']) ? intval($data['stock_data']['qty']) : null,
                    ),
                    'available' => true,
                );

                if (!empty($data['special_price'])) {
                    $sku['compare_price'] = $data['price'];
                    $sku['price'] = $data['special_price'];
                }
                break;
            default:
                $sku = array(
                    'sku'   => $data['sku'],
                    'price' => $data['price'],

                );
        }
        $p->skus = array(
            -1 => $sku,
        );

        #features
        $features = array();
        if (!empty($data['weight'])) {
            $weight = $data['weight'];
            if ($weight_unit = $this->getOption('weight')) {
                $weight .= ' '.$weight_unit;
            }
            $features['weight'] = $weight;
        }
        $p->features = $features;


        $p->save();
        $this->map[self::STAGE_PRODUCT][$data['entity_id']] = array(
            'id'     => $p->getId(),
            'sku_id' => $p->sku_id,
        );
    }


    /**
     * @param $data
     * @return int|null
     * @throws waException
     */
    private function addCustomer($data)
    {
        $result = null;
        $this->log('Import customer', self::LOG_DEBUG, $data);

        $customer = new waContact();
        $customer['firstname'] = $data['firstname'];
        $customer['lastname'] = $data['lastname'];
        $customer['middlename'] = $data['middlename'];
        $customer['email'] = $data['email'];
        $customer['phone'] = ifset($data['telephone']);
        // $customer['suffix'] = $data['suffix'];
        // $customer['prefix'] = $data['prefix'];

        if (!empty($data['id']) && !empty($data['username'])) {
            //    $customer['password'] = md5(microtime(true).rand(0, 10000).$customer['email']);
        }
        $customer['create_datetime'] = $this->formatDatetime($data['created_at']);
        $customer['create_app_id'] = 'shop';
        if ($errors = $customer->save()) {
            $this->log("Error while import customer", self::LOG_ERROR, $errors);
        } else {
            // if (($data['role'] == 'customer') || !empty($data['last_order_id'])) {
            $customer->addToCategory('shop');
            // }
            $result = $customer->getId();
            $this->map[self::STAGE_CUSTOMER][$data['entity_id']] = $result;

        }
        return $result;
    }

    private function addOrder($data)
    {
        $this->log('Import order', self::LOG_DEBUG, $data);
        $order = array(
            'id'              => $data['entity_id'],
            'params'          => array(),
            'state_id'        => $this->statusMap($data['status']),
            'source'          => $this->getOption('url'),
            'create_datetime' => $this->formatDatetime($data['created_at']),
            //'update_datetime' => $this->formatDatetime($data['updated_at']),
            'currency'        => $this->getOption('currency'),
            'rate'            => 1.0,
        );

        if (!empty($data['completed_at'])) {
            $order += $this->formatPaidDate($data['completed_at']);
        }

        $customer_id = intval($data['customer_id']);
        $data['customer'] = reset($data['addresses']);
        if (!empty($this->map[self::STAGE_CUSTOMER][$customer_id])) {
            $order['contact_id'] = $customer_id = $this->map[self::STAGE_CUSTOMER][$customer_id];
        } else {
            $data['customer'] += array(
                'created_at' => $data['created_at'],
            );
            $order['contact_id'] = $customer_id = $this->addCustomer($data['customer']);
        }

        $data['shipping_address'] = reset($data['addresses']);
        $data['billing_address'] = end($data['addresses']);

        /**
         *
         */
        $map = array(
            'base_shipping_amount'        => 'shipping',
            'total_discount'              => 'discount',
            'base_grand_total'            => 'total',
            'tax_amount'                  => 'tax',
            'discount_description'        => 'params:discount_description',
            // 'currency_value'       => 'rate',
            #
            'note'                        => 'comment',
            #customer snapshot(?)
            'remote_ip'                   => 'params:ip',
            'customer:firstname'          => 'params:contact_name',
            'customer:lastname'           => 'params:contact_name',
            'customer:email'              => 'params:contact_email',
            #shipping address
            'shipping_address:firstname'  => 'params:shipping_contact_name',
            'shipping_address:lastname'   => 'params:shipping_contact_name',
            'shipping_address:company'    => 'params:shipping_address.company',
            'shipping_address:country_id' => 'params:shipping_address.country',//ISO code
            'shipping_address:region'     => 'params:shipping_address.region',
            'shipping_address:postcode'   => 'params:shipping_address.zip',
            'shipping_address:city'       => 'params:shipping_address.city',
            'shipping_address:street'     => 'params:shipping_address.street',
            #shipping data
            'shipping_description'        => 'params:shipping_name',
            #billing address
            'billing_address:firstname'   => 'params:billing_contact_name',
            'billing_address:lastname'    => 'params:billing_contact_name',
            'billing_address:company'     => 'params:billing_address.company',
            'billing_address:country_id'  => 'params:billing_address.country',//ISO code
            'billing_address:region'      => 'params:billing_address.region',
            'billing_address:postcode'    => 'params:billing_address.zip',
            'billing_address:city'        => 'params:billing_address.city',
            'billing_address:street'      => 'params:billing_address.street',
            #extra billing address
            'billing_address:email'       => 'params:billing_address.email',
            'billing_address:telephone'   => 'params:billing_address.phone',
            #
            'payment_method'              => 'params:payment_name',
            //             'payment_details:paid'=>'params:payment_name',
        );
        self::dataMap($order, $data, $map);
        $this->deleteOrder($order['id']);

        $this->orderModel()->insert($order);

        foreach ($data['order_items'] as $item) {
            $product = ifset($this->map[self::STAGE_PRODUCT][$item['item_id']], array());
            $insert = array(
                'order_id'   => $order['id'],
                'type'       => 'product',
                'name'       => $item['name'],
                'quantity'   => intval($item['qty_ordered']),
                'price'      => doubleval($item['price']),
                'currency'   => $order['currency'],
                'product_id' => ifset($product['id'], null),
                'sku_id'     => ifset($product['sku_id'], null),
            );
            $this->orderItemsModel()->insert($insert);

        }

        //$data['view_order_url']
        $order['params']['auth_code'] = shopWorkflowCreateAction::generateAuthCode($order['id']);
        $order['params']['auth_pin'] = shopWorkflowCreateAction::generateAuthPin();

        if (!empty($order['params'])) {

            $params = array_map('trim', $order['params']);
            $params_model = new shopOrderParamsModel();
            $params_model->set($order['id'], $params);
        }
        if (!empty($data['order_comments'])) {
            $log_model = new shopOrderLogModel();
            $state = '';
            $payed = false;
            $first = true;
            foreach ($data['order_comments'] as $log) {
                $after_state = $this->statusMap($log['status']);


                $insert = array(
                    'order_id'        => $order['id'],
                    'contact_id'      => $first ? $order['contact_id'] : null,
                    'action_id'       => '',
                    'datetime'        => $this->formatDatetime($log['created_at']),
                    'text'            => ifset($log['comment']),
                    'before_state_id' => $state,
                    'after_state_id'  => $after_state,
                );
                $log_model->insert($insert);
                //TODO add settings
                $payed_states = array(
                    'completed',
                    'paid',
                );
                if (!$payed && in_array($after_state, $payed_states)) {
                    $timestamp = strtotime($log['status_change_time']);
                    $update = array(
                        'paid_year'    => date('Y', $timestamp),
                        'paid_quarter' => date('n', $timestamp),
                        'paid_month'   => floor((date('n', $timestamp) - 1) / 3) + 1,
                        'paid_date'    => date('Y-m-d', $timestamp),
                    );
                    $this->orderModel()->updateById($order['id'], $update);
                    $payed = true;
                }
                $first = false;
                $state = $after_state;
            }
        }

        if ($customer_id) {
            $this->customerModel()->updateFromNewOrder($customer_id, $order['id'], ifset($order['source'], ''));
            shopCustomer::recalculateTotalSpent($customer_id);
        }
        $result = true;
        return $result;
    }

    private static function dataMap(&$result, $data, $map)
    {
        foreach ($map as $field => $target) {
            $_data = $data;
            while (strpos($field, ':')) {
                list($_field, $field) = explode(':', $field, 2);
                $_data = ifset($_data[$_field]);
            }
            if ($target && isset($_data[$field])) {
                if (strpos($target, ':')) {
                    if (!empty($_data[$field])) {
                        list($target, $sub_target) = explode(':', $target, 2);
                        if (empty($result[$target][$sub_target])) {
                            $result[$target][$sub_target] = '';
                        } else {
                            $result[$target][$sub_target] .= ' ';
                        }
                        $result[$target][$sub_target] .= $_data[$field];
                    }
                } else {
                    $result[$target] = $_data[$field];
                }
            }
        }
    }

    private function repeatAuth()
    {
        $this->addOption('repeat', array(
            'title_wrapper' => false,
            'title'         => _wp('Get new verifier code'),
            'description'   => _wp('This will generate new Magento request link so you can copy the new verifier code'),
            'control_type'  => 'RepeatControl',
        ));
    }

    public function settingRepeatControl($name, $params = array())
    {
        $name = htmlentities(waHtmlControl::getName($params, $name), ENT_QUOTES, waHtmlControl::$default_charset);
        $title = htmlentities(ifset($params['title']), ENT_QUOTES, waHtmlControl::$default_charset);
        return sprintf('<input type="hidden" name="%1$s" value="%2$s"><input type="submit" value="%2$s">', $name, $title);
    }


    public function settingStatusControl($name, $params = array())
    {
        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }
        $control = '';
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }

        waHtmlControl::addNamespace($params, $name);
        $control .= '<table class="zebra">';
        $params['description_wrapper'] = '%s';
        $params['title_wrapper'] = '%s';
        $params['control_wrapper'] = '<tr title="%3$s"><td>%1$s</td><td>&rarr;</td><td>%2$s</td></tr>';
        $params['size'] = 6;
        $workflow = new shopWorkflow();
        $states = $workflow->getAvailableStates();
        $source_states = $params['options'];
        $params['options'] = array();
        $params['options'][] = _wp('Select target order state');

        $params['options']['::new'] = _wp('Add as new order state');

        foreach ($states as $id => $state) {
            $params['options'][$id] = $state['name'];
        }

        $predefined = array(
            "pending"    => "new",
            "processing" => "processing",
            "on-hold"    => "new",
            "complete"   => "complete",
            "cancelled"  => "deleted",
            "refunded"   => "refunded",
            //"failed"     => "Failed",
        );
        foreach ($source_states as $id => $state) {
            $control_params = $params;
            $control_params['value'] = (isset($predefined[$id]) && isset($states[$predefined[$id]])) ? $predefined[$id] : null;
            $control_params['title'] = $state;
            $control_params['title_wrapper'] = '%s';
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, $id, $control_params);
        }
        $control .= "</table>";

        return $control;

    }

    private function statusMap($status)
    {

        if (!isset($this->map[self::STAGE_ORDER])) {

            $this->map[self::STAGE_ORDER] = array();

            $workflow_config = shopWorkflow::getConfig();

            $states = $this->getOption('status');
            if ($status_names = $this->get('orders/statuses', 'order_statuses')) {
                foreach ($status_names as $status_id => $name) {
                    if (!empty($states[$status_id])) {
                        ;
                        if ($states[$status_id] === '::new') {
                            $id = &$states[$status_id];
                            $workflow_status = array(
                                'name'              => $name,
                                'options'           => array(
                                    'icon'  => 'icon16 ss flag-white',
                                    'style' => array(),
                                ),
                                'available_actions' => array(),

                            );
                            $status_id = waLocale::transliterate(mb_strtolower($status['name']), 'ru_RU');
                            $status_id = preg_replace('([^a-z_])', '_', $status_id);
                            $status_id = substr(preg_replace('([_]{2,})', '_', $status_id), 0, 16);
                            while (isset($workflow_config['states'][$status_id])) {
                                $status_id = substr(uniqid(substr($status_id, 0, 10)), 0, 16);
                            }


                            $workflow_config['states'][$status_id] = $workflow_status;
                            $id = $status_id;
                            unset($id);
                        }
                    }
                    $this->map[self::STAGE_ORDER] = $states;
                    shopWorkflow::setConfig($workflow_config);
                }

            }
        }
        if (!empty($this->map[self::STAGE_ORDER][$status])) {
            $state_id = $this->map[self::STAGE_ORDER][$status];
        } else {
            $state_id = 'new';
        }
        return $state_id;
    }
}
