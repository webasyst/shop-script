<?php

class shopSettingsOrderActionDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        if (!$id) {
            $this->errors = _w("Unknown action");
            return;
        }


        $config = shopWorkflow::getConfig();
        if (isset($config['actions'][$id])) {
            unset($config['actions'][$id]);
        }
        shopWorkflow::setConfig($config);
    }
}