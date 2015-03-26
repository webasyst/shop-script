<?php

class shopWorkflow extends waWorkflow
{
    protected static $config;
    protected static $original_config;

    public static function getConfig()
    {
        if (self::$config === null) {
            $file = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
            if (!file_exists($file)) {
                $file = wa()->getConfig()->getAppsPath('shop', 'lib/config/data/workflow.php');
            }
            if (file_exists($file)) {
                self::$config = include($file);
                foreach (self::$config['states'] as &$data) {
                    if (!isset($data['classname'])) {
                        $data['classname'] = 'shopWorkflowState';
                    }
                }
                unset($data);
            } else {
                self::$config = array();
            }
        }
        return self::$config;
    }

    protected function getOriginalConfig()
    {
        if (self::$original_config === null) {
            $file = wa()->getConfig()->getAppsPath('shop', 'lib/config/data/workflow.php');
            if (file_exists($file)) {
                self::$original_config = include($file);
            } else {
                self::$original_config = array();
            }
        }
        return self::$original_config;
    }

    public static function setConfig(array $config)
    {
        $file = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
        return waUtils::varExportToFile($config, $file);
    }

    public function getAvailableStates()
    {
        $config = self::getConfig();
        $original_config = self::getOriginalConfig();
        if ($original_config) {
            $original_states = ifset($original_config['states'], array());
            foreach ($config['states'] as $state_id => &$state) {
                $state['original'] = isset($original_states[$state_id]);
            }
            unset($state);
        }
        reset($config['states']);
        return $config['states'];
    }

    public function getAvailableActions()
    {
        $config = self::getConfig();
        $original_config = self::getOriginalConfig();
        if ($original_config) {
            $original_actions = ifset($original_config['actions'], array());
            foreach ($config['actions'] as $action_id => &$action) {
                $action['original'] = isset($original_actions[$action_id]);
            }
            unset($action);
        }

        return $config['actions'];
    }

    /**
     * Helper for getActionById(), getStateById(), getAllStates() and getAllActions().
     * Creates action or state object by key=>value from getAvailableStates() or getAvailableActions()
     *
     * @param mixed $id key from getAvailableStates()/getAvailableActions()
     * @param $data
     * @throws waException
     * @return waWorkflowEntity
     */
    protected function createEntity($id, $data)
    {
        $class_name = $data['classname'];
        if (!class_exists($class_name)) {
            throw new waException('Workflow entity class not found: '.$class_name);
        }
        if (!isset($data['options'])) {
            $data['options'] = array();
        }
        $entity = new $class_name($id, $this, $data);
        $entity->original = ifset($data['original'], false);
        return $entity;
    }

    /**
     * Generate unique ID of state by its name. Max length of ID have to be max 16
     * @param string $name
     * @return string
     */
    public static function generateStateId($name)
    {
        return self::generateEntityId($name, 16, 'states');
    }

    /**
     * Generate unique ID of action by its name. Max length of ID have to be max 32
     * @param string $name
     * @return string
     */
    public static function generateActionId($name) {
        return self::generateEntityId($name, 32, 'actions');
    }

    protected static function generateEntityId($name, $length = 16, $type = 'states')
    {
        $id_prefix = substr(strtolower(shopHelper::transliterate($name)), 0, $length);
        $id = $id_prefix;
        $config = self::getConfig();
        $count = 1;
        while (isset($config[$type][$id])) {
            $count = '' . $count;
            $len = strlen($count);
            if (strlen($id_prefix) + $len > $length) {
                $id = substr($id_prefix, 0, $length - $len) . $count;
            } else {
                $id = $id_prefix . $count;
            }
            $count += 1;
        }
        return $id;
    }

}