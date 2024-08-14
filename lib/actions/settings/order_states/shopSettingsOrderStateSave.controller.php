<?php

class shopSettingsOrderStateSaveController extends waJsonController
{
    /**
     * @var shopWorkflow
     */
    protected $workflow;

    /** Used by shopSettingsOrderStatesAction to prepare modified config, avoiding file operations */
    public $skip_save = false;
    public $config;

    public function execute()
    {
        $this->config = shopWorkflow::getConfig();

        $add = waRequest::get('id', 'new_state', waRequest::TYPE_STRING_TRIM) == 'new_state';
        $data = $this->getData($add);
        if (!$this->validate($data, $add)) {
            return;
        }
        $this->save($data, $add);
        $this->response = array(
            'id'     => $data['state']['id'],
            'new_id' => !empty($data['state']['new_id']) ? $data['state']['new_id'] : null,
            'add'    => $add,
        );
    }

    public function save($data, $add = false)
    {
        $available_states = $this->getWorkflow()->getAvailableStates();
        $available_actions = $this->getWorkflow()->getAvailableActions();

        $id = $data['state']['id'];
        unset($data['state']['id']);
        $this->config['states'] = ifempty($this->config['states'], array());

        if (!empty($data['state']['new_id']) && isset($available_states[$id]) && !$available_states[$id]['original']) {
            unset($this->config['states'][$id]);
            $id = $data['state']['new_id'];
        }

        if (!empty($data['state']['new_id'])) {
            unset($data['state']['new_id']);
        }

        if (!$add && $data['state']['payment_not_allowed_text'] === null) {  // not change in this case
            $text = null;
            if (isset($this->config['states'][$id]['payment_not_allowed_text'])) {
                $text = $this->config['states'][$id]['payment_not_allowed_text'];
            }
            $data['state']['payment_not_allowed_text'] = $text;
        }

        $this->config['states'][$id] = $data['state'];

        // Custom actions
        $this->config['actions'] = ifempty($this->config, 'actions', []) + $available_actions + $data['new_actions'];
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

        if (empty($this->skip_save)) {
            if (!shopWorkflow::setConfig($this->config)) {
                throw new waException(_w("Error on configuration saving."));
            }
        }
    }

    public function validate($data, $add = true)
    {
        if (empty($data['state']['name'])) {
            $this->errors['state']['name'] = _w('State name cannot be empty');
        }

        if (isset($data['state']['new_id'])) {
            if (!preg_match("/^[a-z0-9\._-]+$/i", $data['state']['new_id'])) {
                $this->errors['state']['new_id'] = _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed');
            }
            if (strlen($data['state']['new_id']) > 16) {
                $this->errors['state']['new_id'] = _w('A state ID cannot contain more than 16 characters.');
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

        if (!empty($data['edited_actions'])) {
            foreach ($data['edited_actions'] as $action_id => $action) {
                if (!$action_id) {
                    $this->errors['edited_actions'][$action_id] = _w('Empty action ID');
                }
                if (!$action['name']) {
                    $this->errors['edited_actions'][$action_id] = _w('Empty action name');
                }
                if (strlen($action_id) > 32) {
                    $this->errors['edited_actions'][$action_id] = _w('An action ID cannot contain more than 32 characters.');
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
            'state'          => $state,
            'new_actions'    => $new_actions,
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
                $action['id'] = $action_id;
                $action['classname'] = $this->getActionClassname($action);
                $actions[$action_id] = $action;
            }
        }
        return $actions;
    }

    protected function getActionClassname(&$action, $current_action = array())
    {
        if (isset($action['extends'])) {
            $extends = shopWorkflow::getExtendsActions();
            if (isset($extends[$action['extends']])) {
                $extend_action = $this->getWorkflow()->getActionById($action['extends']);
                $classname = get_class($extend_action);
            } else {
                $classname = 'shopWorkflowAction';
                $action['extends'] = '';
            }
        } else {
            $classname = ifempty($current_action, 'classname', 'shopWorkflowAction');
        }
        return $classname;
    }

    public function getActionOptions()
    {
        $result = array();
        $available_actions = $this->getWorkflow()->getAvailableActions();
        foreach (waRequest::post('action_options', array(), 'array') as $action_id => $settings) {
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
                $action['classname'] = $this->getActionClassname($action, $available_actions[$action_id]);

                foreach ($action as $key => $value) {
                    if ($key === 'options') {
                        if (!isset($available_actions[$action_id]['options'])) {
                            $available_actions[$action_id]['options'] = array();
                        }
                        foreach ($value as $k => $v) {
                            $available_actions[$action_id]['options'][$k] = $v;
                        }
                    } elseif (!is_array($value) && isset($action[$key]) && $key !== 'checked') {
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
        $names = waRequest::post($prefix.'_name', array());
        $descriptions = waRequest::post($prefix.'_description', array());
        $states = waRequest::post($prefix.'_state', array());
        $checked = waRequest::post($prefix, array());
        $links = waRequest::post($prefix.'_link', array());
        $border_colors = waRequest::post($prefix.'_border_color', array());
        $icons = waRequest::post($prefix.'_icon', array());
        $extends = waRequest::post($prefix.'_extends', array());
        foreach (waRequest::post($prefix.'_id', array()) as $k => $action_id) {
            if ($prefix === 'edit_action') {
                $action_id = strtolower(trim($action_id));
            } else {
                $action_id = $k;
            }
            $name = trim($names[$k]);
            $actions[$action_id] = array(
                'name'    => $name,
                'options' => array(),
            );
            if (isset($states[$k])) {
                $state = trim($states[$k]);
                if ($state) {
                    if (isset($available_states[$state])) {
                        $actions[$action_id]['state'] = $state;
                    }
                } else {
                    $actions[$action_id]['state'] = '';
                }
            }
            if (!empty($checked[$k])) {
                $actions[$action_id]['checked'] = true;
            }
            $actions[$action_id]['extends'] = ifset($extends, $k, null);

            $options = &$actions[$action_id]['options'];
            if (!empty($links[$k])) {
                $options['position'] = 'top';
                $options['icon'] = ifset($icons[$k], '');
            } elseif (!empty($border_colors[$k])) {
                $options['position'] = '';
                $options['button_class'] = '';
                $options['border_color'] = trim(trim($border_colors[$k], '#'));
            }
            if (isset($descriptions[$action_id])) {
                $options['description'] = trim($descriptions[$action_id]);
            }
            unset($options);
        }
        return $actions;
    }

    public function getStateInfo($add = true)
    {
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        $info = array(
            'id'                => waRequest::get('id', '', waRequest::TYPE_STRING_TRIM),
            'name'              => $name,
            'options'           => array(
                'style' => $this->getStyle(),
                'icon'  => $this->getIcon(),
            ),
            'payment_allowed'   => !!waRequest::post('payment_allowed'),
            'payment_not_allowed_text' => waRequest::post('payment_not_allowed_text'),
            'available_actions' => $this->getActions(),
        );
        if ($info['id'] == 'auth') {
            $info['available_actions'] = array_merge($info['available_actions'], ['capture', 'cancel']);
        }

        if (waRequest::post('new_id', '', waRequest::TYPE_STRING_TRIM) && $add) {
            $info['new_id'] = waRequest::post('new_id', '', waRequest::TYPE_STRING_TRIM);
        }
        if ($add) {
            if (empty($info['new_id'])) {
                $info['new_id'] = shopWorkflow::generateStateId($name);
            }
            $info['id'] = $info['new_id'];
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
