<?php

class shopSettingsOrderStateMoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        if (!$id) {
            $this->errors[] = _w("Unknown state");
            return;
        }

        $this->moveState($id);
    }

    /**
     * @param $id POST['id']
     */
    public function moveState($id)
    {
        $before_id = waRequest::post('before_id');

        $config = shopWorkflow::getConfig();

        if (!isset($config['states'][$id])) {
            $this->errors[] = _w("Unknown state");
            return;
        }
        $item = $config['states'][$id];

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
            $this->errors[] = _w("Error on configuration saving.");
        }
    }
}
