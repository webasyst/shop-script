<?php
/**
 * 
 */
class shopFrontendApiErr404Controller extends shopFrontApiJsonController
{
    public function execute()
    {
            print((string) new waAPIException('unknown_endpoint', 'Endpoint not supported.', 404));
            exit;
    }
}
