<?php

class shopSettingsConfigSaveController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post();

        $config_path = $this->getConfig()->getConfigPath('config.php');
        if (file_exists($config_path)) {
            $config = include($config_path);
        } else {
            $config = array();
        }
        $save = false;
        foreach ($data as $key => $value) {
            if ($this->getConfig()->getOption($key) !== null) {
                $save = true;
                $config[$key] = $value;
            }
        }
        if ($save) {
            waUtils::varExportToFile($config, $config_path);
        }
    }
}