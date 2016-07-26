<?php

class shopSettingsPrintformSetupAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }

        if ($plugin_id = waRequest::request('id')) {
            $plugins = $this->getConfig()->getPlugins();
            if (isset($plugins[$plugin_id]) && !empty($plugins[$plugin_id]['printform'])) {

                $plugin = waSystem::getInstance()->getPlugin($plugin_id, true);
                /**
                 * @var shopPlugin|shopPrintformInterface $plugin
                 */

                $namespace = 'printform_'.$plugin_id;

                if ($settings = waRequest::post($namespace)) {
                    if ($files = waRequest::file($namespace)) {
                        foreach ($files as $name => $file) {
                            $settings[$name] = $file;
                        }
                    }
                    try {
                        $plugin->saveSettings($settings);
                        $this->view->assign('saved', true);
                    } catch (waException $ex) {
                        $this->view->assign('error', $ex->getMessage());
                    }
                }

                $params = array(
                    'id'                  => $plugin_id,
                    'namespace'           => $namespace,
                    'title_wrapper'       => '%s',
                    'description_wrapper' => '<br><span class="hint">%s</span>',
                    'control_wrapper'     => '<div class="name">%s</div><div class="value">%s %s</div>',
                    'subject'             => 'printform',
                );

                $this->getResponse()->setTitle(_w(sprintf('Plugin %s settings', $plugin->getName())));

                $this->view->assign('plugin_info', $plugins[$plugin_id]);
                $this->view->assign('plugin_id', $plugin_id);
                $this->view->assign('settings_controls', $plugin->getControls($params));

                if (method_exists($plugin, 'getTemplate')) {
                    $this->view->assign('template', $plugin->getTemplate());
                    $this->view->assign('is_template_changed', $plugin->isTemplateChanged());
                } else {
                    $this->view->assign('template', false);
                    $this->view->assign('is_template_changed', false);
                }
            }

            $this->view->assign('plugins_count', count($plugins));
            waSystem::popActivePlugin();
        }
    }
}
