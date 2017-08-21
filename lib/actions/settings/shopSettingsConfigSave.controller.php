<?php

class shopSettingsConfigSaveController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post();

        // Only certain fields are allowed
        $data = array_intersect_key($data, array(
            'discount_description' => 1,
            'notification_name' => 1,
        ));

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