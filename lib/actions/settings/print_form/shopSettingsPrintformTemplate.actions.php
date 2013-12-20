<?php

class shopSettingsPrintformTemplateActions extends waJsonActions
{
    public function saveAction()
    {
        $plugin_id = waRequest::post('id');
        $plugin = waSystem::getInstance()->getPlugin($plugin_id);
        if (!$plugin) {
            throw new waException(_w("Unknown plugin"));
        }
        
        $template = waRequest::post('template', '', waRequest::TYPE_STRING);
        if (!$template) {
            $this->errors[] = _w("Empty template");
            return;
        }
        
        $plugin->saveTemplate($template);
        
    }
    
    public function resetAction()
    {
        $plugin_id = waRequest::post('id');
        $plugin = waSystem::getInstance()->getPlugin($plugin_id);
        if (!$plugin) {
            throw new waException(_w("Unknown plugin"));
        }
        $plugin->resetTemplate();
        $this->response['template'] = $plugin->getTemplate();
    }
}