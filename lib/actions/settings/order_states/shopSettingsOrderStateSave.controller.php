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
        $this->response = array(
            'id' => $data['state']['id'],
            'new_id' => !empty($data['state']['new_id']) ? $data['state']['new_id'] : null,
            'add' => $add
        );
    }

    public function save($data)
    {
        $availabe_states = $this->getWorkflow()->getAvailableStates();

        $id = $data['state']['id'];
        unset($data['state']['id']);
        $this->config['states'] = ifempty($this->config['states'], array());

        if (!empty($data['state']['new_id']) && isset($availabe_states[$id]) && !$availabe_states[$id]['original']) {
            unset($this->config['states'][$id]);
            $id = $data['state']['new_id'];
        }

        if (!empty($data['state']['new_id'])) {
            unset($data['state']['new_id']);
        }

        $this->config['states'][$id] = $data['state'];

        // Custom actions
        $this->config['actions'] = !empty($this->config['actions']) ? $this->config['actions'] + $data['new_actions'] : $data['actions'];
        foreach ($data['edited_actions'] as $action_id => $action) {
            if (isset($this->config['actions'][$action_id])) {
                $this->config['actions'][$action_id] = $action;
            }
        }

        // Options for original actions
        foreach ($data['action_options'] as $action_id => $options) {
            if (isset($this->config['actions'][$action_id])) {
                foreach ($options as $name => $value) {
                    $this->config['actions'][$action_id]['options'][$name] = $value;
                }
            }
        }

        if (!shopWorkflow::setConfig($this->config)) {
            throw new waException(_w("Error when save config"));
        }
    }

    public function validate($data, $add = true)
    {
        if (empty($data['state']['name'])) {
            $this->errors['state']['name'] = _w('State name can not be empty');
        }

        if (isset($data['state']['new_id'])) {
            if (!preg_match("/^[a-z0-9\._-]+$/i", $data['state']['new_id'])) {
                $this->errors['state']['new_id'] = _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed');
            }
            if (strlen($data['state']['new_id']) > 16) {
                $this->errors['state']['new_id'] = _w('Length of state ID more then 16 symbols');
            }
            if ($data['state']['new_id'] == 'new_state') {
                $this->errors['state']['new_id'] = _w('ID is reserved');
            }
        }

        if ($add) {
            $states = !empty($this->config['states']) ? $this->config['states'] : array();
            $ids = array_keys($states);
            if (in_array($data['state']['new_id'], $ids)) {
                $this->errors['state']['new_id'] = _w('Same state ID already exists');
            }
        }

        $ids = !empty($this->config['new_actions']) ? array_keys($this->config['new_actions']) : array();

        if (!empty($data['edited_actions'])) {
            foreach ($data['edited_actions'] as $action_id => $action)
            {
                if (!$action_id) {
                    $this->errors['edited_actions'][$action_id] = _w('Empty action ID');
                }
                if (!$action['name']) {
                    $this->errors['edited_actions'][$action_id] = _w('Empty action name');
                }
                if (strlen($action_id) > 32) {
                    $this->errors['edited_actions'][$action_id] = _w('Length of action ID is more then 32 symbols');
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
        $new_actions = $this->getNewActions();
        foreach ($new_actions as $action_id => &$action) {
            if (!empty($action['checked'])) {
                $state['available_actions'][] = $action_id;
                unset($action['checked']);
            }
        }
        unset($action);

        $edited_actions = $this->getEditedActions();
        foreach ($edited_actions as $action_id => $action) {
            if (!empty($action['checked'])) {
                $state['available_actions'][] = $action_id;
                unset($action['checked']);
            }
        }

        return array(
            'state' => $state,
            'new_actions' => $new_actions,
            'edited_actions' => $edited_actions,
            'action_options' => $this->getActionOptions(),
        );
    }

    public function getNewActions()
    {
        $actions = array();
        foreach ($this->getPostedActions('new_action') as $action) {
            if ($action['name']) {
                $action_id = shopWorkflow::generateActionId($action['name']);
                $action['classname'] = 'shopWorkflowAction';
                $action['id'] = $action_id;
                $actions[$action_id] = $action;
            }
        }
        return $actions;
    }

    public function getActionOptions()
    {
        $result = array();
        $available_actions = $this->getWorkflow()->getAvailableActions();
        foreach(waRequest::post('action_options', array(), 'array') as $action_id => $settings) {
            if (is_array($settings) && !empty($available_actions[$action_id])) {
                $result[$action_id] = $settings;
            }
        }
        return $result;
    }

    public function getEditedActions()
    {
        $actions = $this->getPostedActions('edit_action');
        $available_actions = $this->getWorkflow()->getAvailableActions();
        foreach ($available_actions as $action_id => $action) {
            if ($action['original']) {
                unset($available_actions[$action_id]);
            }
            unset($available_actions[$action_id]['original']);
        }
        $edited_actions = array();
        foreach ($actions as $action_id => $action) {
            if (isset($available_actions[$action_id])) {
                foreach ($action as $key => $value) {
                    if ($key === 'options') {
                        if (!isset($available_actions[$action_id]['options'])) {
                            $available_actions[$action_id]['options'] = array();
                        }
                        foreach ($value as $k => $v) {
                            $available_actions[$action_id]['options'][$k] = $v;
                        }
                    } else if (!is_array($value) && isset($action[$key]) && $key !== 'checked') {
                        $available_actions[$action_id][$key] = $value;
                    }
                }
                $edited_actions[$action_id] = $available_actions[$action_id];
            }
        }
        return $edited_actions;
    }

    private function getPostedActions($prefix)
    {
        $available_states = $this->getWorkflow()->getAvailableStates();
        $actions = array();
        $names = waRequest::post($prefix . '_name', array());
        $states = waRequest::post($prefix . '_state', array());
        $checked = waRequest::post($prefix, array());
        $links = waRequest::post($prefix . '_link', array());
        $border_colors = waRequest::post($prefix . '_border_color', array());
        $icons = waRequest::post($prefix . '_icon', array());
        foreach (waRequest::post($prefix . '_id', array()) as $k => $action_id) {
            if ($prefix === 'edit_action') {
                $action_id = strtolower(trim($action_id));
            } else {
                $action_id = $k;
            }
            $name = trim($names[$k]);
            $actions[$action_id] = array(
                'name' => $name,
                'options' => array()
            );
            $state = trim($states[$k]);
            if ($state && isset($available_states[$state])) {
                $actions[$action_id]['state'] = $state;
            }
            if (!empty($checked[$k])) {
                $actions[$action_id]['checked'] = true;
            }
            if (!empty($links[$k])) {
                $actions[$action_id]['options']['position'] = 'top';
                $actions[$action_id]['options']['icon'] = ifset($icons[$k], '');
            } else if (!empty($border_colors[$k])) {
                $actions[$action_id]['options']['position'] = '';
                $actions[$action_id]['options']['button_class'] = '';
                $actions[$action_id]['options']['border_color'] = trim(trim($border_colors[$k], '#'));
            }
        }
        return $actions;
    }

    public function getStateInfo($add = true)
    {
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        $info = array(
            'id' => waRequest::get('id', '', waRequest::TYPE_STRING_TRIM),
            'name' => $name,
            'options' => array(
                'style' => $this->getStyle(),
                'icon'  => $this->getIcon()
            ),
            'available_actions' => $this->getActions()
        );
        if (waRequest::post('new_id', '', waRequest::TYPE_STRING_TRIM)) {
            $info['new_id'] = waRequest::post('new_id', '', waRequest::TYPE_STRING_TRIM);
        }
        if ($add) {
            $info['id'] = $info['new_id'] = shopWorkflow::generateStateId($name);
        }
        return $info;
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
