<?php

class shopMigratePluginWbApiException extends waException
{
    private $retry_after_ms;

    public function __construct($message, $code = 0, $retry_after_ms = null)
    {
        parent::__construct($message, $code);
        $this->retry_after_ms = $retry_after_ms === null
            ? null
            : max(0, (int) $retry_after_ms);
    }

    public function getRetryAfterMs()
    {
        return $this->retry_after_ms;
    }
}
