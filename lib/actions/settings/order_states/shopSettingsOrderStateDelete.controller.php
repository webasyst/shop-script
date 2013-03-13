<?php

class shopSettingsOrderStateDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$id) {
            $this->errors[] = _w("Unknown state");
            return;
        }

        $config = shopWorkflow::getConfig();
        if (isset($config['states'][$id])) {
            unset($config['states'][$id]);
        }
        shopWorkflow::setConfig($config);
    }
}