<?php

class shopSettingsOrderEditorSaveController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post('data', null, waRequest::TYPE_ARRAY_TRIM);

        $config = new shopOrderEditorConfig();
        $config->setData($data);
        $this->response = $config->commit();
    }
}