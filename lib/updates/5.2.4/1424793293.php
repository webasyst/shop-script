<?php

$path = $this->getConfigPath('workflow.php', true, 'shop');

if (file_exists($path)) {
    $config = include($path);
    if (!isset($config['actions']['message'])) {
        $config['actions']['message'] = array(
            'classname' => 'shopWorkflowMessageAction',
            'name' => _w('Contact customer'),
            'options' => array(
                'position' => 'top',
                'icon' => 'email',
                'log_record' => _w('Message was sent'),
            ),
        );
        foreach ($config['states'] as &$s) {
            $s['available_actions'][] = 'message';
        }
        waUtils::varExportToFile($config, $path);
    }
}
