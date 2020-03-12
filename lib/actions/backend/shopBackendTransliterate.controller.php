<?php

class shopBackendTransliterateController extends waJsonController
{
    public function execute()
    {
        $this->response = shopHelper::transliterate(
            waRequest::request('str', '', waRequest::TYPE_STRING_TRIM),
            waRequest::request('strict', 1, waRequest::TYPE_INT)
        );
    }
}