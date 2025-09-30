<?php

abstract class shopFrontApiJsonController extends waJsonController
{
    protected function preExecute()
    {
        $storefront_mode = waRequest::param('storefront_mode');
        if (empty($storefront_mode)) {
            $this->display404();
            exit;
        }

        if ($origin = waRequest::server('HTTP_ORIGIN')) {
            wa()->getResponse()
                ->addHeader('Access-Control-Allow-Origin', $origin)
                ->addHeader('Access-Control-Allow-Credentials', 'true')
                ->addHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                ->addHeader('Access-Control-Allow-Methods', '*')
                ->addHeader('Vary', 'Origin');
        }

        if (waRequest::server('REQUEST_METHOD') == 'OPTIONS') {
            wa()->getResponse()
                ->setStatus(200)
                ->sendHeaders();
            exit;
        }

        $this->supportJsonRequestBody();
        $this->getResponse()->addHeader('Content-Type', 'application/json; charset=utf-8');
        $this->getResponse()->addHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function execute()
    {
        $request_method = strtolower(waRequest::method());
        try {
            if (!method_exists($this, $request_method)) {
                throw new waAPIException('method_not_supported', 'Method '.$request_method.' is not supported.');
            }
            $this->$request_method();
        } catch (Throwable $e) {
            if (!$e instanceof waAPIException) {
                $data = [];
                if (defined('WA_API_EXCEPTION_STACK_TRACE') && WA_API_EXCEPTION_STACK_TRACE) {
                    $data['original_code'] = $e->getCode();
                    $data['original_message'] = $e->getMessage();
                    $data['original_trace'] = $e instanceof waException ? $e->getFullTraceAsString() : $e->getTraceAsString();
                }
                $e = new waAPIException('server_error', 'Unable to serve API request.', 500, $data);
            }
            wa()->getResponse()->setStatus(500);
            print((string) $e);
            exit;
        }
    }

    public function display()
    {
        $this->getResponse()->sendHeaders();
        if (!$this->errors) {
            echo waUtils::jsonEncode($this->response);
        } else {
            echo waUtils::jsonEncode(array('error' => 'api_error', 'error_description' => is_array($this->errors) ? join(', ', $this->errors) : $this->errors));
        }
    }

    protected function display404()
    {
        wa()->getResponse()->setStatus(404);
        wa()->getResponse()->sendHeaders();

        $view = wa('shop')->getView();
        $view->setThemeTemplate(new waTheme(waRequest::getTheme()), 'error.html');
        $view->assign([
            'error_message' => _w('Not found'),
            'error_code' => 404,
        ]);
        $content = $view->fetch('file:error.html');

        $layout = new shopFrontendLayout();
        $layout->setBlock('content', $content);
        $layout->display();
        exit;
    }

    protected function supportJsonRequestBody()
    {
        $request_method = strtolower(waRequest::method());
        if (in_array($request_method, ['post', 'put', 'delete'], true)) {
            $request_content_type = trim((string) waRequest::server('CONTENT_TYPE'));
            if ('application/json' === substr($request_content_type, 0, 16)) {
                $contents = file_get_contents('php://input');
                if (is_string($contents) && strlen($contents)) {
                    $contents = trim($contents);
                    if ($contents && $contents[0] == '{') {
                        $data = json_decode($contents, true);
                        if ($data && is_array($data)) {
                            $_POST += $data;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return string
     */
    protected static function generateToken()
    {
        return shopApiCart::generateToken();
    }

    /** Link to storefront checkout to create order in browser without Headless API. Returns NULL if disabled in store settings. */
    protected function getStorefrontCheckoutUrl(string $customer_token): ?string
    {
        if (waRequest::param('storefront_mode') === 'api') {
            return null;
        }
        return wa()->getRouting()->getUrl('shop/frontendOrderCart/customertoken', true).'?customer_token='.$customer_token;
    }

    protected function checkAntispamHash(Callable $getData=null)
    {
        if (!$getData) {
            $getData = function($antispam_api_key, $antispam_cart_key, $customer_token) {
                return $antispam_api_key.$antispam_cart_key;
            };
        }
        $customer_token = waRequest::request('customer_token', '', waRequest::TYPE_STRING_TRIM);
        if (!$customer_token) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'customer_token'), 400);
        }
        $antispam_hash = waRequest::request('antispam_hash', '', waRequest::TYPE_STRING_TRIM);
        if (!$antispam_hash) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'antispam_hash'), 400);
        }

        $antispam_api_key = wa()->getSetting('headless_api_antispam_key', '', 'shop');
        foreach ([time(), time() - 3600] as $ts) {
            $antispam_cart_key = shopApiCart::getAntispamCartKey($customer_token, $ts);
            $calculated_hash = hash('sha256', $getData($antispam_api_key, $antispam_cart_key, $customer_token));
            if ($calculated_hash == $antispam_hash) {
                return true;
            }
        }

        throw new waAPIException('antispam_check_failed', sprintf_wp('Incorrect value of the “%s” parameter.', 'antispam_hash'), 400);
    }

    public function convertDateToISO8601($date, $tz = 'UTC')
    {
        if (empty($date)) {
            return null;
        }
        try {
            $dt = new DateTime((string) $date);
            if ($tz) {
                $dt->setTimezone(new DateTimeZone($tz));
            }
        } catch (Exception $ex) {
            return $date;
        }

        return $dt->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * @param array $data
     * @param array $fields
     * @param array $field_types
     * @return array
     */
    public function singleFilterFields($data, array $fields, array $field_types = [])
    {
        $res = [];
        foreach (array_keys($data) as $key) {
            if (in_array($key, $fields)) {
                if (!isset($field_types[$key]) || $data[$key] === null) {
                    $res[$key] = $data[$key];
                    continue;
                }
                if ($field_types[$key] === 'int' || $field_types[$key] === 'integer') {
                    $res[$key] = intval($data[$key]);
                } elseif ($field_types[$key] === 'bool') {
                    $res[$key] = boolval($data[$key]);
                } elseif ($field_types[$key] === 'float') {
                    $res[$key] = floatval($data[$key]);
                } elseif ($field_types[$key] === 'double') {
                    $res[$key] = doubleval($data[$key]);
                } elseif ($field_types[$key] === 'datetime') {
                    $res[$key] = $this->convertDateToISO8601($data[$key]);
                } elseif ($field_types[$key] === 'dateiso') {
                    $res[$key] = $this->convertDateToISO8601($data[$key], null);
                } else {
                    $res[$key] = $data[$key];
                }
            }
        }

        return $res;
    }

    protected function makeShopOrder($customer_token, $coupon_code)
    {
        $cart = new shopApiCart($customer_token);
        $cart_items = $cart->getItems();
        if (!$cart_items) {
            throw new waAPIException('empty_cart', 'Unable to make order from an empty cart', 400);
        }
        $routing_url = wa()->getRouting()->getRootUrl();

        $order = new shopOrder([
            'contact_id' => null,
            'currency'   => wa('shop')->getConfig()->getCurrency(false),
            'items'      => array_map(function($it) {
                return $it + ['cart_item_id' => $it['id']];
            }, $cart_items),
            'params'     => [
                'coupon_code' => ifempty($coupon_code, null),
                'storefront' => wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : ''),
            ],
            'discount'   => 'calculate',
            'tax'        => 'calculate',
        ], [
            'items_format'       => 'raw',
            'items_extend_round' => true,
        ]);

        return $order;
    }

    protected function getCheckoutConfig()
    {
        return new shopCheckoutConfig(waRequest::param('checkout_storefront_id', null));
    }
}
