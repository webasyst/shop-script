<?php

class shopProdFeedbackController extends waJsonController
{
    public function execute()
    {
        $data = $this->getData();

        list($ok, $errors) = $this->sendFeedback($data);
        if (!$ok) {
            $this->errors = $errors;
            return;
        }
    }

    protected function getPostData()
    {
        $data = $this->getRequest()->post('data', [], waRequest::TYPE_ARRAY);
        return waUtils::extractValuesByKeys($data, ['content']);
    }

    protected function getData()
    {
        return array_merge($this->getPostData(), [
            'hash' => $this->getIdentityHash(),
            'domain' => $this->getDomain(),
            'question' => 'shop_product_editor'
        ]);
    }

    /**
     * Get identity hash (aka installation hash)
     * @return string
     * @throws waException
     */
    protected function getIdentityHash()
    {
        return wa('shop')->getConfig()->getIdentityHash();
    }

    protected function getDomain()
    {
        return wa()->getConfig()->getDomain();
    }

    protected function sendFeedback(array $data = [])
    {
        $net_options = [
            'timeout' => 20,
            'format' => waNet::FORMAT_JSON,
            'request_format' => waNet::FORMAT_RAW
        ];

        $url = wa()->getConfig()->getWebasystApiUrl('feedback');

        $net = new waNet($net_options);

        $exception = null;
        $response = null;
        try {
            $response = $net->query($url, $data, waNet::METHOD_POST);
        } catch (Exception $e) {
            $exception = $e;
        }

        $is_debug_mode = waSystemConfig::isDebug();

        $status = $net->getResponseHeader('http_code');
        if ($status == 200 && $response && is_array($response)) {
            return $this->decodeResponse($response);
        }

        if ($exception) {
            $this->logException($exception);
            $this->logError([
                'method' => __METHOD__,
                'debug' => $is_debug_mode ? $net->getResponseDebugInfo() : '',
            ]);
        } else {
            $this->logError([
                'method' => __METHOD__,
                'response_error' => 'unknown',
                'status' => $status,
                'debug' => $is_debug_mode ? $net->getResponseDebugInfo() : ''
            ]);
        }

        $errors = [
            [
                'id' => 'request_fail',
                'text' => _w('Feedback message sending has failed.')
            ]
        ];

        return [false, $errors];
    }

    protected function decodeResponse(array $response)
    {
        if (isset($response['status']) && $response['status'] === 'ok') {
            return [true, []];
        }

        $errors = [];
        if (isset($response['errors']) && is_array($response['errors'])) {
            foreach ($response['errors'] as $error) {
                if (!is_array($error)) {
                    continue;
                }

                $error_type = ifset($error['type']);
                $field = ifset($error['field']);
                $code = ifset($error['code']);

                // general error
                $parts = $field ? [$error_type, $field, $code] : [$error_type, $code];
                $error = [
                    'id' => join('/', $parts),
                    'text' => $code,
                ];

                // or pretty error for user when about invalid field 'content'
                if ($error_type == 'validation' && $field === 'content') {
                    if ($code === 'required') {
                        $text = _w('This is a required field.');
                    } else {
                        $text = $code;
                    }
                    $error = [
                        'name' => "data[{$field}]",
                        'text' => $text,
                    ];
                }

                $errors[] = $error;
            }
        }

        if (!$errors) {
            $errors[] = [
                'id' => 'unknown',
                'text' => _w('Unknown error')
            ];
        }

        return [false, $errors];
    }

    protected function logException(Exception $e)
    {
        $message = join(PHP_EOL, [$e->getCode(), $e->getMessage(), $e->getTraceAsString()]);
        waLog::log($message, 'webasyst/' . get_class($this) . '.log');
    }

    protected function logError($e)
    {
        if (!is_scalar($e)) {
            $e = var_export($e, true);
        }
        waLog::log($e, 'webasyst/' . get_class($this) . '.log');
    }
}
