<?php

class shopSettingsSearchAction extends waViewAction
{
    public function execute()
    {
        $fields = array(
            'name' => _w('Name'),
            'summary' => _w('Summary'),
            'description' => _w('Description'),
            'tag' => _w('Tags'),
            'feature' => _w('Features'),
            'sku' => _w('SKU code'),
            //'other' => _w('Other fields'),
        );
        $this->view->assign('fields', $fields);
        
        $config = $this->getConfig();
        
        $default_options = array();
        $path = $config->getAppConfigPath('config');
        if (file_exists($path)) {
            $default_options = include($path);
        }
        
        $this->view->assign('weights', $config->getOption('search_weights'));
        $this->view->assign('default_weights', $default_options['search_weights']);
        $this->view->assign('ignore',$config->getOption('search_ignore'));
        $this->view->assign('by_part', (int)$config->getOption('search_by_part'));
        $this->view->assign('smart', (bool)$config->getOption('search_smart'));
    }
}