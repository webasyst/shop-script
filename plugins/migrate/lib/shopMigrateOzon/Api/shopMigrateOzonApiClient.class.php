<?php

class shopMigrateOzonApiClient
{
    const DEFAULT_BASE_URL = 'https://api-seller.ozon.ru/';
    const DEFAULT_TIMEOUT = 30;
    const MAX_PRODUCT_PAGE = 100;
    const MAX_INFO_BATCH = 100;

    private $client_id;
    private $api_key;
    private $base_url;
    private $timeout;
    /**
     * @var shopMigrateOzonLogger
     */
    private $logger;

    public function __construct($client_id, $api_key, shopMigrateOzonLogger $logger, array $options = array())
    {
        $this->client_id = (string) $client_id;
        $this->api_key = (string) $api_key;
        $this->base_url = isset($options['base_url']) && $options['base_url']
            ? rtrim($options['base_url'], '/').'/'
            : self::DEFAULT_BASE_URL;
        $this->timeout = isset($options['timeout']) ? max(5, (int) $options['timeout']) : self::DEFAULT_TIMEOUT;
        $this->logger = $logger;
    }

    public function listWarehouses()
    {
        return $this->request('v1/warehouse/list', array());
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
        $values = array();
        foreach ((array) $identifiers as $value) {
            $value = (int) $value;
            if ($value > 0) {
                $values[] = $value;
            }
        }
        $values = array_values(array_unique($values));
        $payload_sets = array();
        foreach (array_chunk($values, self::MAX_INFO_BATCH) as $chunk) {
            if ($chunk) {
                $payload_sets[] = array('sku' => $chunk);
            }
        }

        $result = array();
        foreach ($payload_sets as $payload) {
            if ($warehouse_id !== null) {
                $payload['warehouse_id'] = (int) $warehouse_id;
            }
            $response = $this->request('v1/product/info/stocks-by-warehouse/fbs', $payload);
            if (!empty($response['items']) && is_array($response['items'])) {
                foreach ($response['items'] as $item) {
                    $result[] = $item;
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
        $this->logger->logRequest($request_id, $path, $payload, $body, $headers);

        $net = new waNet(array(
            'timeout'            => $this->timeout,
            'format'             => waNet::FORMAT_RAW,
            'expected_http_code' => null,
        ), $headers);

        try {
            $response_body = $net->query($url, $body, waNet::METHOD_POST);
        } catch (Exception $e) {
            $this->logger->logResponse($request_id, $path, 0, $e->getMessage(), true);
            throw $e;
        }

        $status_code = (int) $net->getResponseHeader('http_code');
        $this->logger->logResponse($request_id, $path, $status_code, $response_body, $status_code >= 400);

        if ($status_code < 200 || $status_code >= 300) {
            $message = $this->buildErrorMessage($response_body, $status_code);
            throw new waException($message, $status_code);
        }

        $decoded = json_decode($response_body, true);
        if (!is_array($decoded)) {
            throw new waException('Invalid JSON response from Ozon API');
        }

        return $decoded;
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
