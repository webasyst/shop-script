<?php

class shopBackendTransliterateController extends waJsonController
{
    public function execute()
    {
        $this->response = shopHelper::transliterate(
            waRequest::get('str', '', waRequest::TYPE_STRING_TRIM),
            waRequest::get('strict', 1, waRequest::TYPE_INT)
        );
    }
}