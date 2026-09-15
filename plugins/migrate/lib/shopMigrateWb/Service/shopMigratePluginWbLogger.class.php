<?php

class shopMigratePluginWbLogger
{
    const LOG_FILE = 'shop/plugins/migrate/migrate_wb.log';
    const MODE_ERRORS = 'errors';
    const MODE_FULL = 'full';
    const MAX_VALUE_LENGTH = 4000;

    private $mode;

    public function __construct($mode = self::MODE_ERRORS)
    {
        $this->setMode($mode);
    }

    public function setMode($mode)
    {
        $this->mode = $mode === self::MODE_FULL ? self::MODE_FULL : self::MODE_ERRORS;
    }

    public function logRequest($request_id, $path, array $payload = array(), $body = '', array $headers = array())
    {
        if ($this->mode !== self::MODE_FULL) {
            return;
        }
        $this->write(sprintf(
            '[REQUEST] [%s] %s payload=%s body=%s headers=%s',
            (string) $request_id,
            (string) $path,
            $this->formatValue($payload),
            $this->truncate($this->redact((string) $body)),
            $this->formatValue($this->sanitizeHeaders($headers))
        ));
    }

    public function logResponse($request_id, $path, $status_code, $body, $is_error = false)
    {
        if ($this->mode !== self::MODE_FULL && !$is_error) {
            return;
        }
        $this->write(sprintf(
            '[RESPONSE] [%s] %s status=%d body=%s',
            (string) $request_id,
            (string) $path,
            (int) $status_code,
            $this->truncate($this->redact((string) $body))
        ));
    }

    public function logError($message, array $context = array())
    {
        $this->logLevel('ERROR', $message, $context, true);
    }

    public function logWarning($message, array $context = array())
    {
        $this->logLevel('WARNING', $message, $context, true);
    }

    public function logInfo($message, array $context = array())
    {
        $this->logLevel('INFO', $message, $context, false);
    }

    private function logLevel($level, $message, array $context, $always)
    {
        if (!$always && $this->mode !== self::MODE_FULL) {
            return;
        }
        $suffix = $context ? ' context='.$this->formatValue($context) : '';
        $this->write(sprintf('[%s] %s%s', $level, $this->redact((string) $message), $suffix));
    }

    private function sanitizeHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), array('authorization', 'api-key', 'client-id'), true)) {
                $headers[$name] = '[redacted]';
            }
        }
        return $headers;
    }

    private function formatValue($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->truncate($this->redact($json === false ? '' : $json));
    }

    private function redact($value)
    {
        $redacted = preg_replace(
            '/(Authorization["\'\s:=]+(?:Bearer\s+)?)[A-Za-z0-9._~+\/=\-]+/i',
            '$1[redacted]',
            (string) $value
        );
        if ($redacted === null) {
            return '';
        }
        $redacted = preg_replace(
            '/\b[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\b/',
            '[redacted-token]',
            $redacted
        );
        return $redacted === null ? '' : $redacted;
    }

    private function truncate($value, $limit = self::MAX_VALUE_LENGTH)
    {
        $value = (string) $value;
        if (function_exists('mb_strlen') && mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit).'...';
        }
        return strlen($value) > $limit ? substr($value, 0, $limit).'...' : $value;
    }

    private function write($message)
    {
        waLog::log($this->truncate($message, self::MAX_VALUE_LENGTH * 2), self::LOG_FILE);
    }
}
