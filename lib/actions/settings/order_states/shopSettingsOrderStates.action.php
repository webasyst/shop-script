<?php

class shopSettingsOrderStatesAction extends waViewAction
{
    private $config;

    public function execute()
    {
        $id = $this->getId();

        //If create new State temporarily save the user's settings to send to the template.
        if (waRequest::post()) {
            if ($id === 'new_state') {
                $_POST['name'] = uniqid('new_status_');
            }
            //This shopSettingsOrderStateSave.controller. Save old config to $this->config.
            $this->savePreview();
            if ($id === 'new_state') {
                $config = shopWorkflow::getConfig();
                $states = array_keys(array_reverse($config['states'], true));
                $id = $states[0];
            }
        }

        //Get data with new state
        $workflow = new shopWorkflow();
        $states = $workflow->getAllStates();
        $actions = $workflow->getAvailableActions();
        $info = $states ? $this->getStateInfo($id) : $this->getDummyStateInfo();

        $actions = $this->sortActions($actions, $info['actions']);

        $buttons = $this->getButtons($actions, $info);

        //Assign temporary data
        $this->view->assign(array(
            'edit_actions_map' => $this->getEditActionsMap(),
            'states'  => $states,
            'actions' => $actions,
            'info'    => $info,
            'icons'   => (array) $this->getConfig()->getOption('order_state_icons'),
            'action_icons' => (array) $this->getConfig()->getOption('order_action_icons'),
            'buttons' => $buttons
        ));

        //Restore workflow config from $this->config.
        //Because if the user wants to save a new state, the request will go to the shopSettingsOrderStateSave.controller directly ¯\_(ツ)_/¯
        if (waRequest::post()) {
            $this->restoreConfig();
        }
    }

    public function getId()
    {
        $id = waRequest::get('id', '', waRequest::TYPE_STRING_TRIM);
        if (!$id) {
            $params = (array)waRequest::get('param', array());
            if (isset($params[0])) {
                $id = $params[0];
            }
        }
        return $id;
    }

    public function getEditActionsMap()
    {
        $edit_action = explode(',', $this->getRequest()->get('edit_action'));
        return array_fill_keys($edit_action, true);
    }

    public function getStateInfo($id = null)
    {
        if ($id == 'new_state') {
            return $this->getDummyStateInfo();
        }
        $info = array();
        $workflow = new shopWorkflow();
        $state = $workflow->getStateById($id);
        $info['id'] = $state->id;
        $info['name'] = $state->getName();
        $info['options'] = $state->getOptions();
        $info['actions'] = array_keys($state->getActions(null, $state->id));
        $info['original'] = $state->original;
        return $info;
    }

    public function getDummyStateInfo()
    {
        return array(
            'id' => 'new_state',
            'name'  => _w('New State'),
            'options' => array(
                'icon' => 'icon16 ss new',
                'style' => array(
                    'color' => '#CCC'
                ),
            ),
            'original' => false,
            'actions' => array()
        );
    }

    public function savePreview()
    {
        $this->config = shopWorkflow::getConfig();
        $controller = new shopSettingsOrderStateSaveController();
        $controller->execute();
    }

    public function restoreConfig()
    {
        if ($this->config) {
            shopWorkflow::setConfig($this->config);
        }
    }

    public function getButtons($actions, $info)
    {
        $workflow = new shopWorkflow();
        $all_buttons = array('other' => array(), 'top' => array(), 'bottom' => array());
        foreach ($actions as $id => $a) {
            if (in_array($id, $info['actions'])) {
                $action = $workflow->getActionById($id);
                if ($action) {
                    if ($action->getOption('top') || $action->getOption('position') == 'top') {
                        $all_buttons['top'][] = $action->getButton();
                    } else if ($action->getOption('position') == 'bottom') {
                        $all_buttons['bottom'][] = $action->getButton();
                    } else {
                        $all_buttons['other'][] = $action->getButton();
                    }
                }
            }
        }
        $flat_buttons = array();
        foreach ($all_buttons as $buttons) {
            $flat_buttons = array_merge($flat_buttons, $buttons);
        }
        return $flat_buttons;
    }

    public function sortActions($actions, $state_actions)
    {
        foreach ($state_actions as $action_id) {
            if (isset($actions[$action_id])) {
                $action = $actions[$action_id];
                unset($actions[$action_id]);
                $actions[$action_id] = $action;     // push back
            }
        }
        return $actions;
    }

}
