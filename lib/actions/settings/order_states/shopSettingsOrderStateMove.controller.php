<?php

class shopSettingsOrderStateMoveController extends waJsonController
{
    public function execute()
    {
        $id = $this->getId();
        if (!$id) {
            $this->errors[] = _w("Unknown state");
            return;
        }

        $before_id = $this->getBeforeId();

        if ($this->getType() === 'state') {
            $this->moveState($id, $before_id);
        } else {
            $this->moveAction($id, $before_id, $this->getStateId());
        }

    }

    public function getId()
    {
        return (string) $this->getRequest()->post('id');
    }

    public function getBeforeId()
    {
        return (string) $this->getRequest()->post('before_id');
    }

    public function getType()
    {
        return $this->getRequest()->post('type') === 'action' ? 'action' : 'state';
    }

    public function getStateId()
    {
        return (string) $this->getRequest()->post('state_id');
    }

    public function moveState($id, $before_id)
    {
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
            $this->errors[] = _w("Error when save config");
        }
    }

    public function moveAction($id, $before_id, $state_id)
    {
        $config = shopWorkflow::getConfig();
        if (!isset($config['states'][$state_id])) {
            $this->errors[] = _w("Unknown state");
            return;
        }

        $state = $config['states'][$state_id];
        if (!isset($state['available_actions'])) {
            $this->errors[] = _w("State hasn't any available action");
            return;
        }

        $available_actions = $state['available_actions'];

        $sort_map = array_map(wa_lambda('$sort', 'return $sort * 2;'), array_flip($available_actions));

        if (!isset($sort_map[$id])) {
            $this->errors[] = _w("Action isn't available");
            return;
        }

        if (!$before_id) {
            $sort_map[$id] += 1;
        } else {
            if (!isset($sort_map[$before_id])) {
                $this->errors[] = _w("Action isn't available");
                return;
            }
            $sort_map[$id] = $sort_map[$before_id] - 1;
        }

        sort($sort_map, SORT_NUMERIC);

        $resorted_available_actions = array_values(array_flip($sort_map));

        wa_dump($resorted_available_actions);

        if ($resorted_available_actions != $available_actions) {
            $config['states'][$state_id]['available_actions'] = $resorted_available_actions;
        }

        if (!shopWorkflow::setConfig($config)) {
            $this->errors[] = _w("Error when save config");
        }

    }
}