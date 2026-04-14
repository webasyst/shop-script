<?php

class shopMigratePluginOzonApiClient
{
    const DEFAULT_BASE_URL = 'https://api-seller.ozon.ru/';
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_MIN_REQUEST_INTERVAL_MS = 250;
    const DEFAULT_MAX_RETRY_ATTEMPTS = 6;
    const DEFAULT_RETRY_BASE_DELAY_MS = 1000;
    const DEFAULT_RETRY_MAX_DELAY_MS = 30000;
    const DEFAULT_429_BASE_DELAY_MS = 5000;
    const MAX_PRODUCT_PAGE = 100;
    const MAX_INFO_BATCH = 100;

    private $client_id;
    private $api_key;
    private $base_url;
    private $timeout;
    private $min_request_interval_ms;
    private $max_retry_attempts;
    private $retry_base_delay_ms;
    private $retry_max_delay_ms;
    private $retry_429_base_delay_ms;
    private $last_request_started_at = 0.0;
    /**
     * @var shopMigratePluginOzonLogger
     */
    private $logger;

    public function __construct($client_id, $api_key, shopMigratePluginOzonLogger $logger, array $options = array())
    {
        $this->client_id = (string) $client_id;
        $this->api_key = (string) $api_key;
        $this->base_url = isset($options['base_url']) && $options['base_url']
            ? rtrim($options['base_url'], '/').'/'
            : self::DEFAULT_BASE_URL;
        $this->timeout = isset($options['timeout']) ? max(5, (int) $options['timeout']) : self::DEFAULT_TIMEOUT;
        $this->min_request_interval_ms = isset($options['min_request_interval_ms'])
            ? max(0, (int) $options['min_request_interval_ms'])
            : self::DEFAULT_MIN_REQUEST_INTERVAL_MS;
        $this->max_retry_attempts = isset($options['max_retry_attempts'])
            ? max(1, (int) $options['max_retry_attempts'])
            : self::DEFAULT_MAX_RETRY_ATTEMPTS;
        $this->retry_base_delay_ms = isset($options['retry_base_delay_ms'])
            ? max(100, (int) $options['retry_base_delay_ms'])
            : self::DEFAULT_RETRY_BASE_DELAY_MS;
        $this->retry_max_delay_ms = isset($options['retry_max_delay_ms'])
            ? max($this->retry_base_delay_ms, (int) $options['retry_max_delay_ms'])
            : self::DEFAULT_RETRY_MAX_DELAY_MS;
        $this->retry_429_base_delay_ms = isset($options['retry_429_base_delay_ms'])
            ? max(1000, (int) $options['retry_429_base_delay_ms'])
            : self::DEFAULT_429_BASE_DELAY_MS;
        $this->logger = $logger;
    }

    public function listWarehouses()
    {
        $payload = array(
            'limit' => 200,
        );
        $rows = array();
        $seen_cursors = array();

        while (true) {
            $response = $this->request('v2/warehouse/list', $payload);
            $items = array();

            if (!empty($response['warehouses']) && is_array($response['warehouses'])) {
                $items = $response['warehouses'];
            } elseif (!empty($response['result']['warehouses']) && is_array($response['result']['warehouses'])) {
                $items = $response['result']['warehouses'];
            } elseif (!empty($response['result']) && is_array($response['result'])) {
                $items = $response['result'];
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $rows[] = $item;
                }
            }

            $has_next = !empty($response['has_next']);
            $cursor = (string) ifset($response['cursor'], '');
            if (!$has_next || $cursor === '' || isset($seen_cursors[$cursor])) {
                break;
            }

            $seen_cursors[$cursor] = true;
            $payload['cursor'] = $cursor;
        }

        return array('result' => $rows);
    }

    public function listProducts($last_id = '', $limit = 100, array $filter = null)
    {
        if ($filter === null) {
            $filter = array(
                'visibility' => 'ALL',
            );
        }
        $payload = array(
            'limit'   => min(self::MAX_PRODUCT_PAGE, max(1, (int) $limit)),
            'last_id' => (string) $last_id,
            'filter'  => $filter,
        );
        return $this->request('v3/product/list', $payload);
    }

    public function getDescriptionCategoryTree($language = 'RU')
    {
        return $this->request('v1/description-category/tree', array('language' => $language));
    }

    public function getAttributesForCategory($description_category_id, $type_id)
    {
        return $this->request('v1/description-category/attribute', array(
            'description_category_id' => (int) $description_category_id,
            'type_id'                 => (int) $type_id,
        ));
    }

    public function getProductsInfoBatch(array $product_ids)
    {
        return $this->batchRequest('v3/product/info/list', 'product_id', $product_ids);
    }

    public function getProductsAttributesBatch(array $product_ids)
    {
        $chunks = array_chunk(array_values(array_unique(array_map('intval', $product_ids))), self::MAX_INFO_BATCH);
        $result = array();
        foreach ($chunks as $chunk) {
            if (!$chunk) {
                continue;
            }
            $payload = array(
                'filter' => array(
                    'product_id' => $chunk,
                ),
                'limit' => count($chunk),
            );
            $response = $this->request('v4/product/info/attributes', $payload);
            if (!empty($response['result']) && is_array($response['result'])) {
                $result = array_merge($result, $response['result']);
            } elseif (!empty($response['items']) && is_array($response['items'])) {
                $result = array_merge($result, $response['items']);
            }
        }

        return $result;
    }

    public function getStocksByWarehouseFbsBatch($identifiers, $warehouse_id = null)
    {
        $sku_values = array();
        $offer_values = array();

        foreach ((array) $identifiers as $identifier) {
            if (is_array($identifier)) {
                if (isset($identifier['sku'])) {
                    $sku = (int) $identifier['sku'];
                    if ($sku > 0) {
                        $sku_values[] = $sku;
                    }
                }
                if (isset($identifier['offer_id'])) {
                    $offer_id = trim((string) $identifier['offer_id']);
                    if ($offer_id !== '') {
                        $offer_values[] = $offer_id;
                    }
                }
                continue;
            }

            $value = trim((string) $identifier);
            if ($value === '') {
                continue;
            }
            if (ctype_digit($value)) {
                $sku = (int) $value;
                if ($sku > 0) {
                    $sku_values[] = $sku;
                }
            } else {
                $offer_values[] = $value;
            }
        }

        $field = '';
        $values = array();
        if ($sku_values) {
            $field = 'sku';
            $values = array_values(array_unique($sku_values));
        } elseif ($offer_values) {
            $field = 'offer_id';
            $values = array_values(array_unique($offer_values));
        } else {
            return array();
        }

        $payload_sets = array();
        foreach (array_chunk($values, self::MAX_INFO_BATCH) as $chunk) {
            if ($chunk) {
                $payload_sets[] = array(
                    $field  => $chunk,
                    'limit' => 1000,
                );
            }
        }

        $result = array();
        foreach ($payload_sets as $payload) {
            if ($warehouse_id !== null) {
                $payload['warehouse_id'] = (int) $warehouse_id;
            }

            $seen_cursors = array();
            while (true) {
                $response = $this->request('v2/product/info/stocks-by-warehouse/fbs', $payload);

                if (!empty($response['products']) && is_array($response['products'])) {
                    foreach ($response['products'] as $item) {
                        if (is_array($item)) {
                            $result[] = $item;
                        }
                    }
                } elseif (!empty($response['items']) && is_array($response['items'])) {
                    foreach ($response['items'] as $item) {
                        if (is_array($item)) {
                            $result[] = $item;
                        }
                    }
                } elseif (!empty($response['result']) && is_array($response['result'])) {
                    foreach ($response['result'] as $record) {
                        $wid = ifset($record['warehouse_id']);
                        if (!$wid && !empty($record['items'])) {
                            foreach ($record['items'] as $item) {
                                $item['warehouse_id'] = ifset($record['warehouse_id']);
                                if ($item['warehouse_id']) {
                                    $result[] = $item;
                                }
                            }
                            continue;
                        }
                        if ($wid) {
                            $result[] = $record;
                        }
                    }
                }

                $has_next = !empty($response['has_next']);
                $cursor = (string) ifset($response['cursor'], '');
                if (!$has_next || $cursor === '' || isset($seen_cursors[$cursor])) {
                    break;
                }

                $seen_cursors[$cursor] = true;
                $payload['cursor'] = $cursor;
            }
        }
        return $result;
    }

    private function batchRequest($path, $key, array $values)
    {
        $chunks = array_chunk(array_values(array_unique(array_map('intval', $values))), self::MAX_INFO_BATCH);
        $result = array();
        foreach ($chunks as $chunk) {
            if (!$chunk) {
                continue;
            }
            $response = $this->request($path, array($key => $chunk));
            if (!empty($response['items']) && is_array($response['items'])) {
                $result = array_merge($result, $response['items']);
            } elseif (!empty($response['result']) && is_array($response['result'])) {
                $result = array_merge($result, $response['result']);
            }
        }
        return $result;
    }

    private function request($path, array $payload)
    {
        if ($this->client_id === '' || $this->api_key === '') {
            throw new waException('Ozon API credentials are empty');
        }
        $url = rtrim($this->base_url, '/').'/'.ltrim($path, '/');
        $body = json_encode((object) $payload);
        $headers = array(
            'Client-Id'    => $this->client_id,
            'Api-Key'      => $this->api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        );
        $request_id = uniqid('ozon_', true);
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->waitForRateWindow();
            $this->logger->logRequest($request_id.'#'.$attempt, $path, $payload, $body, $headers);

            $net = new waNet(array(
                'timeout'            => $this->timeout,
                'format'             => waNet::FORMAT_RAW,
                'expected_http_code' => null,
            ), $headers);

            try {
                $response_body = $net->query($url, $body, waNet::METHOD_POST);
            } catch (Exception $e) {
                $this->logger->logResponse($request_id.'#'.$attempt, $path, 0, $e->getMessage(), true);
                if ($this->shouldRetryByAttempt($attempt)) {
                    $retry_delay_ms = $this->computeRetryDelayMs($attempt);
                    $this->logger->logError(
                        sprintf(
                            'Retrying Ozon request after transport error (attempt %d/%d, delay %d ms)',
                            $attempt,
                            $this->max_retry_attempts,
                            $retry_delay_ms
                        ),
                        array('path' => $path)
                    );
                    $this->sleepMs($retry_delay_ms);
                    continue;
                }
                throw $e;
            }

            $status_code = (int) $net->getResponseHeader('http_code');
            $this->logger->logResponse($request_id.'#'.$attempt, $path, $status_code, $response_body, $status_code >= 400);

            if ($status_code >= 200 && $status_code < 300) {
                $decoded = json_decode($response_body, true);
                if (!is_array($decoded)) {
                    throw new waException('Invalid JSON response from Ozon API');
                }
                return $decoded;
            }

            if ($this->isRetryableStatus($status_code) && $this->shouldRetryByAttempt($attempt)) {
                $retry_after_ms = $this->extractRetryAfterMs($net);
                $retry_delay_ms = $this->computeRetryDelayMs($attempt, $retry_after_ms, $status_code);
                $this->logger->logError(
                    sprintf(
                        'Retrying Ozon request after HTTP %d (attempt %d/%d, delay %d ms)',
                        $status_code,
                        $attempt,
                        $this->max_retry_attempts,
                        $retry_delay_ms
                    ),
                    array('path' => $path)
                );
                $this->sleepMs($retry_delay_ms);
                continue;
            }

            $message = $this->buildErrorMessage($response_body, $status_code);
            throw new waException($message, $status_code);
        }
    }

    private function waitForRateWindow()
    {
        if ($this->min_request_interval_ms <= 0) {
            $this->last_request_started_at = microtime(true);
            return;
        }
        $now = microtime(true);
        if ($this->last_request_started_at > 0) {
            $elapsed_ms = ($now - $this->last_request_started_at) * 1000;
            $wait_ms = $this->min_request_interval_ms - $elapsed_ms;
            if ($wait_ms > 0) {
                $this->sleepMs($wait_ms);
            }
        }
        $this->last_request_started_at = microtime(true);
    }

    private function sleepMs($ms)
    {
        $ms = (int) ceil(max(0, (float) $ms));
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function shouldRetryByAttempt($attempt)
    {
        return (int) $attempt < (int) $this->max_retry_attempts;
    }

    private function isRetryableStatus($status_code)
    {
        return in_array((int) $status_code, array(429, 500, 502, 503, 504), true);
    }

    private function computeRetryDelayMs($attempt, $retry_after_ms = null, $status_code = 0)
    {
        if ($retry_after_ms !== null && $retry_after_ms > 0) {
            $delay = (int) $retry_after_ms + $this->getJitterMs();
            return min($this->retry_max_delay_ms, $delay);
        }
        if ((int) $status_code === 429) {
            $delay = (int) $this->retry_429_base_delay_ms * max(1, (int) $attempt);
            $delay += $this->getJitterMs();
            return min($this->retry_max_delay_ms, $delay);
        }
        $exponent = max(0, (int) $attempt - 1);
        $base_delay = $this->retry_base_delay_ms * pow(2, $exponent);
        $delay = (int) $base_delay + $this->getJitterMs();
        return min($this->retry_max_delay_ms, $delay);
    }

    private function getJitterMs()
    {
        return mt_rand(100, 700);
    }

    private function extractRetryAfterMs(waNet $net)
    {
        $raw = $net->getResponseHeader('retry_after');
        if (!$raw) {
            $raw = $net->getResponseHeader('Retry-After');
        }
        if (!$raw) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return max(0, (int) $raw * 1000);
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return max(0, ($timestamp - time()) * 1000);
    }

    private function buildErrorMessage($body, $status_code)
    {
        $message = 'HTTP '.$status_code;
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $detail = ifset($decoded['message'], ifset($decoded['error'], ifset($decoded['detail'])));
                if (!$detail && isset($decoded['result']) && is_string($decoded['result'])) {
                    $detail = $decoded['result'];
                }
                if ($detail) {
                    $message .= ': '.$detail;
                }
            } else {
                $message .= ': '.mb_substr($body, 0, 500);
            }
        }
        return $message;
    }

}
