<?php

class shopSettingsOrderStatesAction extends waViewAction
{
    /**
     * @var shopWorkflow
     */
    protected $workflow;

    public function execute()
    {
        $id = $this->getId();
        $workflow = $this->getWorkflow();
        $states = $workflow->getAllStates();
        $this->view->assign(array(
            'states'  => $states,
            'actions' => $workflow->getAvailableActions(),
            'info'    => $states ? $this->getStateInfo($id) : $this->getDummyStateInfo(),
            'icons'   => (array) $this->getConfig()->getOption('order_state_icons')
        ));
    }

    public function getId()
    {
        $id = null;
        $params = (array) waRequest::get('param', array());
        if (isset($params[0])) {
            $id = $params[0];
        }
        return $id;
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

    public function getStateInfo($id = null)
    {
        if ($id == 'new_state') {
            return $this->getDummyStateInfo();
        }
        $info = array();
        $state = $this->getWorkflow()->getStateById($id);
        $info['id'] = $state->id;
        $info['name'] = $state->getName();
        $info['options'] = $state->getOptions();
        $info['actions'] = array_keys($state->getActions(null, $state->id));
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
            'actions' => array()
        );
    }
}
