<?php

class shopSettingsOrderStateMoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$id) {
            $this->errors[] = _w("Unknown state");
            return;
        }

        $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);

        $config = shopWorkflow::getConfig();
        $item = $config['states'][$id];
        if (!isset($config['states'][$id])) {
            $this->errors[] = _w("Unknown state");
            return;
        }
        unset($config['states'][$id]);

        if (!$before_id) {
            $config['states'][$id] = $item;
        } else {
            if (!isset($config['states'][$before_id])) {
                $this->errors[] = _w("Unknown state");
                return;
            }
            $states = array();
            foreach ($config['states'] as $state_id => $state) {
                if ($state_id == $before_id) {
                    $states[$id] = $item;
                }
                $states[$state_id] = $state;
            }
            $config['states'] = $states;
        }

        if (!shopWorkflow::setConfig($config)) {
            $this->errors[] = _w("Error when save config");
        }
    }
}