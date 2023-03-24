<?php

class shopSettingsOrderStatesAction extends waViewAction
{
    private $config;

    public function execute()
    {
        $id = $this->getId();
        $workflow = new shopWorkflow();

        // If create new State temporarily save the user's settings to send to the template.
        if (waRequest::post()) {
            if ($id === 'new_state') {
                $_POST['name'] = uniqid('new_status_');
            }
            // Make fake workflow config to prepare data for template
            $modified_config = $this->getModifiedConfig();
            $workflow->setTemporaryConfig($modified_config);
            if ($id === 'new_state') {
                // fake id of newly created state
                $states = array_keys(array_reverse($modified_config['states'], true));
                $id = $states[0];
            }
        }

        //Get data with new state
        $states = $workflow->getAllStates();
        $actions = $workflow->getAvailableActions();
        $info = $states ? $this->getStateInfo($id) : $this->getDummyStateInfo();

        $actions = $this->sortActions($actions, $info['actions']);

        $buttons = $this->getButtons($actions, $info);

        $extend_actions = $this->getExtendsActions($workflow);
        $extend_classes = waUtils::getFieldValues($extend_actions, 'classname', 'classname');
        $extend_classes['shopWorkflowAction'] = 'shopWorkflowAction';

        //Assign temporary data
        $this->view->assign(array(
            'edit_actions_map' => $this->getEditActionsMap(),
            'states'           => $states,
            'actions'          => $actions,
            'extend_actions'   => $extend_actions,
            'extend_classes'   => $extend_classes,
            'info'             => $info,
            'icons'            => (array)$this->getConfig()->getOption('order_state_icons'),
            'action_icons'     => (array)$this->getConfig()->getOption('order_action_icons'),
            'buttons'          => $buttons,
        ));

        // Restore unmodified workflow config (being paranoid)
        $workflow->setTemporaryConfig(null);
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
        $edit_action = explode(',', $this->getRequest()->get('edit_action', ''));
        return array_fill_keys($edit_action, true);
    }

    public function getStateInfo($id = null)
    {
        if ($id == 'new_state') {
            return $this->getDummyStateInfo();
        }
        $info = array();
        $workflow = new shopWorkflow();
        $workflow_config = shopWorkflow::getConfig();
        /** @var shopWorkflowState $state */
        $state = $workflow->getStateById($id);
        $info['id'] = $state->id;
        $info['name'] = $state->getName();
        $info['options'] = $state->getOptions();
        $info['actions'] = ifset($workflow_config, 'states', $state->id, 'available_actions', []);
        $info['original'] = $state->original;
        $info['payment_allowed'] = $state->paymentAllowed();
        $info['payment_not_allowed_text'] = $state->paymentNotAllowedText();
        return $info;
    }

    public function getDummyStateInfo()
    {
        return array(
            'id'              => 'new_state',
            'name'            => _w('New State'),
            'options'         => array(
                'icon'  => 'icon16 ss new',
                'style' => array(
                    'color' => '#CCC',
                ),
            ),
            'original'        => false,
            'payment_allowed' => true,
            'payment_not_allowed_text' => shopWorkflowState::paymentNotAllowedDefaultText(),
            'actions'         => array(),
        );
    }

    public function getModifiedConfig()
    {
        $controller = new shopSettingsOrderStateSaveController();
        $controller->skip_save = true;
        $controller->execute();
        return $controller->config;
    }

    public function getButtons($actions, $info)
    {
        $workflow = new shopWorkflow();
        $all_buttons = array('other' => array(), 'top' => array(), 'bottom' => array());
        foreach ($actions as $id => $a) {
            if (in_array($id, $info['actions'])) {
                /** @var shopWorkflowAction $action */
                $action = $workflow->getActionById($id);
                if ($action) {
                    if ($action->getOption('top') || $action->getOption('position') == 'top') {
                        $all_buttons['top'][] = $action->getButton();
                    } elseif ($action->getOption('position') == 'bottom') {
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

    protected function getExtendsActions($workflow)
    {
        $result = [];
        foreach(shopWorkflow::getExtendsActions() as $key => $action) {
            try {
                $extend_action = $workflow->getActionById($action['id']);
                if ($extend_action) {
                    $action['classname'] = get_class($extend_action);
                    $result[$key] = $action;
                }
            } catch (waException $e) {
            }
        }
        return $result;
    }
}
