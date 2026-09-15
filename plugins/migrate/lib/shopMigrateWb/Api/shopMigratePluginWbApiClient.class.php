<?php

class shopMigratePluginWbApiClient
{
    const CONTENT_BASE_URL = 'https://content-api.wildberries.ru/';
    const PRICES_BASE_URL = 'https://discounts-prices-api.wildberries.ru/';
    const MARKETPLACE_BASE_URL = 'https://marketplace-api.wildberries.ru/';
    const ANALYTICS_BASE_URL = 'https://seller-analytics-api.wildberries.ru/';
    const FBW_REQUEST_INTERVAL = 21;
    const MAX_FBW_PAGE_SIZE = 1000;

    const DEFAULT_TIMEOUT = 18;
    const DEFAULT_MAX_RETRY_ATTEMPTS = 1;
    const DEFAULT_RETRY_DELAY_MS = 1000;
    const DEFAULT_RETRY_MAX_DELAY_MS = 60000;

    const MAX_CARDS_PAGE = 100;
    const MAX_SUBJECTS_PAGE = 1000;
    const MAX_PRICE_NM_LIST = 1000;
    const MAX_STOCK_CHRT_LIST = 1000;

    private $token;
    private $content_base_url;
    private $prices_base_url;
    private $marketplace_base_url;
    private $analytics_base_url;
    private $timeout;
    private $max_retry_attempts;
    private $retry_delay_ms;
    private $retry_max_delay_ms;
    private $logger;

    public function __construct($token, array $options = array())
    {
        $this->token = $this->normalizeToken($token);
        $this->content_base_url = $this->getBaseUrlOption(
            $options,
            'content_base_url',
            self::CONTENT_BASE_URL
        );
        $this->prices_base_url = $this->getBaseUrlOption(
            $options,
            'prices_base_url',
            self::PRICES_BASE_URL
        );
        $this->marketplace_base_url = $this->getBaseUrlOption(
            $options,
            'marketplace_base_url',
            self::MARKETPLACE_BASE_URL
        );
        $this->analytics_base_url = $this->getBaseUrlOption($options, 'analytics_base_url', self::ANALYTICS_BASE_URL);
        $this->timeout = isset($options['timeout'])
            ? max(5, (int) $options['timeout'])
            : self::DEFAULT_TIMEOUT;
        $this->max_retry_attempts = isset($options['max_retry_attempts'])
            ? max(1, (int) $options['max_retry_attempts'])
            : self::DEFAULT_MAX_RETRY_ATTEMPTS;
        $this->retry_delay_ms = isset($options['retry_delay_ms'])
            ? max(100, (int) $options['retry_delay_ms'])
            : self::DEFAULT_RETRY_DELAY_MS;
        $this->retry_max_delay_ms = isset($options['retry_max_delay_ms'])
            ? max($this->retry_delay_ms, (int) $options['retry_max_delay_ms'])
            : self::DEFAULT_RETRY_MAX_DELAY_MS;
        $this->logger = isset($options['logger']) && is_object($options['logger'])
            ? $options['logger']
            : null;
    }

    /**
     * Checks both token validity and access to the Content API required by the importer.
     *
     * @return array
     * @throws waException
     */
    public function testContentAccess()
    {
        return $this->getCardsList(1);
    }

    /**
     * Category-specific, read-only probes used by the credentials check.
     * Unlike /ping, these methods use the regular API group rate limits.
     */
    public function testContentPermissionAccess()
    {
        return $this->getJson($this->content_base_url, 'content/v2/cards/limits');
    }

    public function testPricesPermissionAccess()
    {
        return $this->getJson($this->prices_base_url, 'api/v2/list/goods/filter', array(
            'limit'  => 1,
            'offset' => 0,
        ));
    }

    public function testMarketplacePermissionAccess()
    {
        return $this->getSellerWarehouses();
    }

    public function testAnalyticsPermissionAccess()
    {
        return $this->getWbStocksPage(array(), 1);
    }

    /**
     * Read-only FBW inventory. Reserve a request slot across AJAX workers and
     * connection checks; callers wait in the browser instead of sleeping in PHP.
     */
    public function getWbStocksPage(array $nm_ids, $limit = self::MAX_FBW_PAGE_SIZE, $offset = 0)
    {
        $lock = new shopMigratePluginWbOperationLock();
        if (!$lock->acquire('fbw_api')) {
            throw new shopMigratePluginWbApiException(_wp('Waiting for the WB warehouse API request interval.'), 429, self::FBW_REQUEST_INTERVAL * 1000);
        }
        try {
            $settings = new waAppSettingsModel();
            $namespace = array(shopMigratePluginWbSettings::NS_APP, shopMigratePluginWbSettings::NS_PLUGIN);
            $next_at = (int) $settings->select('value')->where(
                'app_id = ? AND name = ?',
                implode('.', $namespace),
                'fbw_request_after'
            )->fetchField();
            if ($next_at > time()) {
                throw new shopMigratePluginWbApiException(_wp('Waiting for the WB warehouse API request interval.'), 429, ($next_at - time()) * 1000);
            }
            $settings->set($namespace, 'fbw_request_after', time() + self::FBW_REQUEST_INTERVAL);
            $payload = array(
                'limit' => min(self::MAX_FBW_PAGE_SIZE, max(1, (int) $limit)),
                'offset' => max(0, (int) $offset),
            );
            $nm_ids = $this->normalizePositiveIds($nm_ids);
            if ($nm_ids) {
                $payload['nmIds'] = array_slice($nm_ids, 0, 1000);
            }
            // Retries belong to the resumable snapshot, not this HTTP request.
            $previous_attempts = $this->max_retry_attempts;
            $this->max_retry_attempts = 1;
            try {
                // WB documents HTTP 204 as "No data" for this endpoint.
                return $this->postJson(
                    $this->analytics_base_url,
                    'api/analytics/v1/stocks-report/wb-warehouses',
                    $payload,
                    array('data' => array('items' => array()))
                );
            } catch (shopMigratePluginWbApiException $e) {
                if ($e->getCode() === 429 && $e->getRetryAfterMs() !== null) {
                    $delay = max(self::FBW_REQUEST_INTERVAL, (int) ceil($e->getRetryAfterMs() / 1000));
                    $settings->set($namespace, 'fbw_request_after', time() + $delay);
                }
                throw $e;
            } finally {
                $this->max_retry_attempts = $previous_attempts;
            }
        } finally {
            $lock->release('fbw_api');
        }
    }

    /**
     * Returns one cursor page of Wildberries product cards.
     *
     * Pass response cursor.updatedAt and cursor.nmID back in $cursor to get the next page.
     * A page with cursor.total lower than the requested limit is the last one.
     *
     * @param int $limit
     * @param array $cursor
     * @return array
     * @throws waException
     */
    public function getCardsList($limit = 100, array $cursor = array())
    {
        $cursor['limit'] = min(self::MAX_CARDS_PAGE, max(1, (int) $limit));

        return $this->postJson($this->content_base_url, 'content/v2/get/cards/list', array(
            'settings' => array(
                'sort' => array(
                    'ascending' => true,
                ),
                'cursor' => $cursor,
                'filter' => array(
                    'withPhoto' => -1,
                ),
            ),
        ));
    }

    /**
     * Returns all Wildberries parent product categories.
     *
     * @return array
     * @throws waException
     */
    public function getParentCategories()
    {
        return $this->getJson($this->content_base_url, 'content/v2/object/parent/all');
    }

    /**
     * Returns one offset page of Wildberries subjects and their parent categories.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws waException
     */
    public function getSubjects($limit = 1000, $offset = 0)
    {
        return $this->getJson($this->content_base_url, 'content/v2/object/all', array(
            'limit'  => min(self::MAX_SUBJECTS_PAGE, max(1, (int) $limit)),
            'offset' => max(0, (int) $offset),
        ));
    }

    /**
     * Returns the characteristic schema for one Wildberries subject.
     *
     * @param int $subject_id
     * @return array
     * @throws waException
     */
    public function getSubjectCharacteristics($subject_id)
    {
        $subject_id = $this->requirePositiveId(
            $subject_id,
            _wp('Wildberries subject ID is invalid.')
        );

        return $this->getJson(
            $this->content_base_url,
            'content/v2/object/charcs/'.$subject_id
        );
    }

    /**
     * Returns prices for the specified WB article IDs.
     *
     * WB accepts up to 1,000 nmIDs per request. Larger input is split into requests and
     * returned as one response-shaped envelope in data.listGoods.
     *
     * @param array $nm_ids
     * @return array
     * @throws waException
     */
    public function getPricesByNmIds(array $nm_ids)
    {
        $nm_ids = $this->normalizePositiveIds($nm_ids);
        $result = array(
            'data' => array(
                'listGoods' => array(),
            ),
            'error' => false,
            'errorText' => '',
        );

        foreach (array_chunk($nm_ids, self::MAX_PRICE_NM_LIST) as $chunk) {
            $response = $this->postJson(
                $this->prices_base_url,
                'api/v2/list/goods/filter',
                array('nmList' => $chunk)
            );
            if (!empty($response['data']['listGoods']) && is_array($response['data']['listGoods'])) {
                foreach ($response['data']['listGoods'] as $item) {
                    if (is_array($item)) {
                        $result['data']['listGoods'][] = $item;
                    }
                }
            }
            $this->mergeEnvelopeError($result, $response);
            unset($response);
        }

        return $result;
    }

    /**
     * Returns all seller warehouses used for seller-side fulfillment models.
     *
     * @return array
     * @throws waException
     */
    public function getSellerWarehouses()
    {
        return $this->getJson($this->marketplace_base_url, 'api/v3/warehouses');
    }

    /**
     * Returns seller warehouse inventory for the specified WB size IDs.
     *
     * Input is conservatively split into batches of 1,000 chrtIDs. WB currently does not
     * publish a separate maximum for this read method, while related stock methods use 1,000.
     *
     * @param int $warehouse_id
     * @param array $chrt_ids
     * @return array
     * @throws waException
     */
    public function getSellerStocks($warehouse_id, array $chrt_ids)
    {
        $warehouse_id = $this->requirePositiveId(
            $warehouse_id,
            _wp('Wildberries seller warehouse ID is invalid.')
        );
        $chrt_ids = $this->normalizePositiveIds($chrt_ids);
        $result = array('stocks' => array());

        foreach (array_chunk($chrt_ids, self::MAX_STOCK_CHRT_LIST) as $chunk) {
            $response = $this->postJson(
                $this->marketplace_base_url,
                'api/v3/stocks/'.$warehouse_id,
                array('chrtIds' => $chunk)
            );
            if (!empty($response['stocks']) && is_array($response['stocks'])) {
                foreach ($response['stocks'] as $item) {
                    if (is_array($item)) {
                        $result['stocks'][] = $item;
                    }
                }
            }
            unset($response);
        }

        return $result;
    }

    private function getJson($base_url, $path, array $query = array())
    {
        return $this->requestJson($base_url, $path, waNet::METHOD_GET, $query, null);
    }

    private function postJson($base_url, $path, array $payload, $no_content_response = null)
    {
        return $this->requestJson($base_url, $path, waNet::METHOD_POST, array(), $payload, $no_content_response);
    }

    private function requestJson($base_url, $path, $method, array $query, $payload, $no_content_response = null)
    {
        if ($this->token === '') {
            throw new waException(_wp('Wildberries API token is empty.'));
        }

        $url = rtrim((string) $base_url, '/').'/'.ltrim((string) $path, '/');
        if ($query) {
            $query_string = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($query_string !== '') {
                $url .= '?'.$query_string;
            }
        }

        $content = array();
        $headers = array(
            'Authorization' => 'Bearer '.$this->token,
            'Accept'        => 'application/json',
        );
        if ($method === waNet::METHOD_POST) {
            $content = json_encode((object) $payload);
            if ($content === false) {
                throw new waException(_wp('Unable to prepare a Wildberries API request.'));
            }
            $headers['Content-Type'] = 'application/json';
        }

        $request_id = substr(sha1(uniqid('wb', true)), 0, 12);
        if ($this->logger && method_exists($this->logger, 'logRequest')) {
            $this->logger->logRequest(
                $request_id,
                $method.' '.$url,
                $payload === null ? $query : array('query' => $query, 'payload' => $payload),
                '',
                $headers
            );
        }

        $attempt = 0;
        while (true) {
            $attempt++;
            $net = new waNet(array(
                'timeout'            => $this->timeout,
                'format'             => waNet::FORMAT_RAW,
                'expected_http_code' => null,
                'log'                => false,
                // waNet disables verification by default on Windows. Use PHP's
                // configured CA store and never fall back to its plain socket transport.
                'verify'             => true,
                'priority'           => array('curl', 'fopen'),
            ), $headers);

            try {
                $response_body = $net->query($url, $content, $method);
            } catch (Exception $e) {
                if ($attempt < $this->max_retry_attempts) {
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                // Do not expose the transport exception: some transports include request
                // headers or request bodies in exception text.
                throw new waException(_wp('Unable to connect to Wildberries API.'));
            }

            $status_code = (int) $net->getResponseHeader('http_code');
            if ($status_code >= 200 && $status_code < 300) {
                if ($this->logger && method_exists($this->logger, 'logResponse')) {
                    $this->logger->logResponse($request_id, $method.' '.$url, $status_code, $response_body, false);
                }
                // Only explicitly opted-in endpoints may treat 204 as an empty result.
                // An empty HTTP 200 response must still fail JSON validation.
                if ($status_code === 204 && $no_content_response !== null && (string) $response_body === '') {
                    return $no_content_response;
                }
                $decoded = json_decode((string) $response_body, true);
                if (!is_array($decoded)) {
                    throw new waException(_wp('Wildberries API returned an invalid response.'));
                }
                return $decoded;
            }

            if ($this->isRetryableStatus($status_code) && $attempt < $this->max_retry_attempts) {
                $this->sleepBeforeRetry($attempt, $this->getRetryAfterMs($net));
                continue;
            }

            if ($this->logger && method_exists($this->logger, 'logResponse')) {
                $this->logger->logResponse($request_id, $method.' '.$url, $status_code, $response_body, true);
            }

            $retry_after_ms = $this->isRetryableStatus($status_code)
                ? $this->getRetryAfterMs($net)
                : null;
            // Preserve the server's full cooldown for resumable callers.
            // retry_max_delay_ms only limits synchronous sleeps, not this hint.
            throw new shopMigratePluginWbApiException(
                $this->getErrorMessage($status_code, $response_body),
                $status_code,
                $retry_after_ms
            );
        }
    }

    private function getBaseUrlOption(array $options, $key, $default)
    {
        return !empty($options[$key])
            ? rtrim((string) $options[$key], '/').'/'
            : $default;
    }

    private function normalizeToken($token)
    {
        $token = trim((string) $token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }

    private function normalizePositiveIds(array $ids)
    {
        $result = array();
        $seen = array();
        foreach ($ids as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $id;
        }
        return $result;
    }

    private function requirePositiveId($id, $message)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new waException($message);
        }
        return $id;
    }

    private function mergeEnvelopeError(array &$result, array $response)
    {
        if (!empty($response['error'])) {
            $result['error'] = true;
        }
        if (
            $result['errorText'] === ''
            && !empty($response['errorText'])
            && is_scalar($response['errorText'])
        ) {
            $result['errorText'] = $this->redactSensitiveText((string) $response['errorText']);
        }
    }

    private function isRetryableStatus($status_code)
    {
        return in_array((int) $status_code, array(408, 429, 500, 502, 503, 504), true);
    }

    private function sleepBeforeRetry($attempt, $retry_after_ms = null)
    {
        if ($retry_after_ms !== null) {
            $delay_ms = min(
                $this->retry_max_delay_ms,
                max(0, (int) ceil($retry_after_ms))
            );
        } else {
            $exponent = max(0, (int) $attempt - 1);
            $delay_ms = min(
                $this->retry_max_delay_ms,
                (int) ($this->retry_delay_ms * pow(2, $exponent))
            );
        }
        if ($delay_ms > 0) {
            usleep($delay_ms * 1000);
        }
    }

    private function getRetryAfterMs(waNet $net)
    {
        $delays = array();

        $retry_after = $net->getResponseHeader('Retry-After');
        $parsed = $this->parseRetryHeaderMs($retry_after, false);
        if ($parsed !== null) {
            $delays[] = $parsed;
        }

        $retry_after_ms = $net->getResponseHeader('Retry-After-Ms');
        $parsed = $this->parseRetryHeaderMs($retry_after_ms, true);
        if ($parsed !== null) {
            $delays[] = $parsed;
        }

        $rate_limit_retry = $net->getResponseHeader('X-Ratelimit-Retry');
        $parsed = $this->parseRateLimitRetryMs($rate_limit_retry);
        if ($parsed !== null) {
            $delays[] = $parsed;
        }

        return $delays ? max($delays) : null;
    }

    private function parseRetryHeaderMs($value, $milliseconds)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(ms|s)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
            if ($unit === 'ms' || ($unit === '' && $milliseconds)) {
                return max(0, (int) ceil($number));
            }
            return max(0, (int) ceil($number * 1000));
        }

        if (!$milliseconds) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return max(0, ($timestamp - time()) * 1000);
            }
        }

        return null;
    }

    private function parseRateLimitRetryMs($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(ms|s)$/i', $value, $matches)) {
            return $this->parseRetryHeaderMs($value, strtolower($matches[2]) === 'ms');
        }
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        // WB documents X-Ratelimit-Retry in seconds. Some gateways return the same
        // value in milliseconds; four or more digits are unambiguous for the API
        // groups used by this client, whose documented retry windows are <= 20 s.
        if ($number >= 1000) {
            return max(0, (int) ceil($number));
        }
        return max(0, (int) ceil($number * 1000));
    }

    private function getErrorMessage($status_code, $response_body)
    {
        if ((int) $status_code === 0) {
            return _wp('Unable to connect to Wildberries API.');
        }
        if ((int) $status_code === 401) {
            return _wp('Wildberries rejected the API token. Check the token and try again.');
        }
        if ((int) $status_code === 403) {
            return _wp('The API token does not grant access to the requested Wildberries API method.');
        }
        if ((int) $status_code === 429) {
            return _wp('Wildberries API request limit has been exceeded. Try again later.');
        }

        $message = sprintf(_wp('Wildberries API returned HTTP status %d.'), (int) $status_code);
        $detail = $this->extractErrorDetail($response_body);
        if ($detail !== '') {
            $message .= ' '.$detail;
        }
        return $message;
    }

    private function extractErrorDetail($response_body)
    {
        $decoded = json_decode((string) $response_body, true);
        if (!is_array($decoded)) {
            return '';
        }

        foreach (array('detail', 'message', 'errorText', 'title') as $key) {
            if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                return mb_substr(
                    $this->redactSensitiveText(trim((string) $decoded[$key])),
                    0,
                    500
                );
            }
        }
        return '';
    }

    private function redactSensitiveText($text)
    {
        $text = (string) $text;
        if ($this->token !== '') {
            $text = str_replace($this->token, '[redacted]', $text);
        }
        $redacted = preg_replace(
            '/Bearer\s+[A-Za-z0-9._~+\/=\-]+/i',
            'Bearer [redacted]',
            $text
        );
        return $redacted === null ? '' : $redacted;
    }
}
