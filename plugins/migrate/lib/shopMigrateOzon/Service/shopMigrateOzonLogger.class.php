<?php

class shopMigrateOzonLogger
{
    const LOG_FILE = 'shop/plugins/migrate/migrate_ozon.log';

    private $mode;

    public function __construct($mode = shopMigrateOzonSettings::LOG_ERRORS)
    {
        $this->mode = $mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function logRequest($request_id, $path, array $payload, $body, array $headers)
    {
        if ($this->mode !== shopMigrateOzonSettings::LOG_FULL) {
            return;
        }
        $message = sprintf(
            '[%s] Request %s payload=%s body=%s headers=%s',
            $request_id,
            $path,
            $this->truncate(json_encode($payload)),
            $this->truncate($body),
            $this->truncate(json_encode($this->sanitizeHeaders($headers)))
        );
        waLog::log($message, self::LOG_FILE);
    }

    public function logResponse($request_id, $path, $status_code, $body, $is_error = false)
    {
        if ($this->mode === shopMigrateOzonSettings::LOG_ERRORS && !$is_error) {
            return;
        }
        $message = sprintf(
            '[%s] Response %s status=%s body=%s',
            $request_id,
            $path,
            $status_code,
            $this->truncate($body)
        );
        waLog::log($message, self::LOG_FILE);
    }

    public function logError($message, array $context = array())
    {
        $suffix = $context ? ' '.json_encode($context) : '';
        waLog::log('[ERROR] '.$message.$suffix, self::LOG_FILE);
    }

    private function sanitizeHeaders(array $headers)
    {
        unset($headers['Api-Key'], $headers['Client-Id'], $headers['Authorization']);
        return $headers;
    }

    private function truncate($value, $limit = 2000)
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        if (mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit).'...';
        }
        return $value;
    }
}
