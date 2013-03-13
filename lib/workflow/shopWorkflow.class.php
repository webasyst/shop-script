<?php

class shopWorkflow extends waWorkflow
{
    protected static $config;

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

    public static function setConfig(array $config)
    {
        $file = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
        return waUtils::varExportToFile($config, $file);
    }

    public function getAvailableStates()
    {
        $config = self::getConfig();
        return $config['states'];
    }

    public function getAvailableActions()
    {
        $config = self::getConfig();
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
        return new $class_name($id, $this, $data);
    }

}