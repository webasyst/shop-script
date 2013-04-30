<?php

class shopSettingsOrderStateSaveController extends waJsonController
{
    /**
     * @var shopWorkflow
     */
    protected $workflow;
    protected $config;

    public function execute()
    {
        $this->config = shopWorkflow::getConfig();
        $add = waRequest::get('id', 'new_state', waRequest::TYPE_STRING_TRIM) == 'new_state';
        $data = $this->getData($add);

        if (!$this->validate($data, $add)) {
            return;
        }
        $this->save($data);
        $id = $data['state']['id'];
        $this->response = array(
            'id' => $id
        );
    }

    public function save($data)
    {
        $id = $data['state']['id'];
        unset($data['state']['id']);
        $this->config['states'] = !empty($this->config['states']) ? $this->config['states'] : array();
        $this->config['states'][$id] = $data['state'];
        $this->config['actions'] = !empty($this->config['actions']) ? $this->config['actions'] + $data['actions'] : $data['actions'];
        if (!shopWorkflow::setConfig($this->config)) {
            throw new waException(_w("Error when save config"));
        }
    }

    public function validate($data, $add = true)
    {
        if (empty($data['state']['name'])) {
            $this->errors['state']['name'] = _w('State name can not be empty');
        }
        if (!preg_match("/^[a-z0-9\._-]+$/i", $data['state']['id'])) {
            $this->errors['state']['id'] = _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed');
        }

        if ($add) {
            $states = !empty($this->config['states']) ? $this->config['states'] : array();
            $ids = array_keys($states);
            if (in_array($data['state']['id'], $ids)) {
                $this->errors['state']['id'] = _w('Same state ID already exists');
            }
        }
        if ($data['state']['id'] == 'new_state') {
            $this->errors['state']['id'] = _w('ID is reserved');
        }

        $ids = !empty($this->config['actions']) ? array_keys($this->config['actions']) : array();
        if (!empty($data['actions'])) {
            foreach (array_unique(array_keys($data['actions'])) as $action_id)
            {
                if (in_array($action_id, $ids)) {
                    $this->errors['actions'][$action_id] = _w('Same action ID alreay exists');
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * @return shopWorkflow
     */
    public function getWorkflow()
    {
        if (!$this->workflow) {
            $this->workflow = new shopWorkflow();
        }
        return $this->workflow;
    }

    public function getData($add = true)
    {
        $state = $this->getStateInfo($add);
        $actions = $this->getNewActions();
        foreach ($actions as $action_id => &$action) {
            if (!empty($action['checked'])) {
                $state['available_actions'][] = $action_id;
                unset($action['checked']);
            }
            unset($action);
        }
        return array('state' => $state, 'actions' => $actions);
    }

    public function getNewActions()
    {
        $available_states = $this->getWorkflow()->getAvailableStates();

        $actions = array();
        $names = waRequest::post('new_action_name', array());
        $states = waRequest::post('new_action_state', array());
        $checked = waRequest::post('new_action', array());
        foreach (waRequest::post('new_action_id', array()) as $k => $action_id) {
            $action_id = strtolower(trim($action_id));
            if (!$action_id) {
                continue;
            }
            $name = trim($names[$k]);
            if (!$name) {
                continue;
            }
            $actions[$action_id] = array(
                'classname' => 'shopWorkflowAction',
                'name' => $name
            );
            $state = trim($states[$k]);
            if ($state && isset($available_states[$state])) {
                $actions[$action_id]['state'] = $state;
            }
            if (!empty($checked[$k])) {
                $actions[$action_id]['checked'] = true;
            }
        }
        return $actions;
    }

    public function getStateInfo($add = true)
    {
        return array(
            'id' =>
                $add ? waRequest::post('id', '', waRequest::TYPE_STRING_TRIM) : waRequest::get('id', '', waRequest::TYPE_STRING_TRIM),
            'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'options' => array(
                'style' => $this->getStyle(),
                'icon'  => $this->getIcon()
            ),
            'available_actions' => $this->getActions()
        );
    }

    public function getStyle()
    {
        $data = waRequest::post('style');
        $data['color'] = !empty($data['color']) ? '#'.rtrim($data['color'], '#') : '#CCC';
        return $data;
    }

    public function getIcon()
    {
        return waRequest::post('icon', 'new', waRequest::TYPE_STRING_TRIM);
    }

    public function getActions()
    {
        $actions = array();
        foreach (waRequest::post('action', array()) as $action) {
            $actions[] = trim($action);
        }
        return $actions;
    }
}
