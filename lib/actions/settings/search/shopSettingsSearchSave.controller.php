<?php

class shopSettingsSearchSaveController extends waJsonController
{
    public function execute()
    {
        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        $settings = $config->getOption(null);

        if (waRequest::post('smart') !== null) {
            $settings['search_smart'] = waRequest::post('smart') ? 1 : 0;
        } else {
            $settings['search_weights'] = waRequest::post('weights');
            $settings['search_ignore'] = waRequest::post('ignore');
            $settings['search_by_part'] = waRequest::post('by_part', 0, 'int');
        }

        $config_file = $config->getConfigPath('config.php');
        waUtils::varExportToFile($settings, $config_file);
    }
}