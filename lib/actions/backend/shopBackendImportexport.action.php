<?php

class shopBackendImportexportAction extends waViewAction
{
    public function execute()
    {

        if (!$this->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', 'type.%')) {
            throw new waRightsException('Access denied');
        }

        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->getResponse()->addJs('js/importexport/importexport.js', true);

        $plugins = $this->getConfig()->getPlugins();
        foreach ($plugins as $id => $plugin) {
            if (empty($plugin['importexport'])) {
                unset($plugins[$id]);
            }
        }

        $this->view->assign('plugins', $plugins);
    }
}
