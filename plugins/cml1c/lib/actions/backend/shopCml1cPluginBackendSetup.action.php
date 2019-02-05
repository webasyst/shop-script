<?php

class shopCml1cPluginBackendSetupAction extends waViewAction
{
    /**
     *
     * @return shopCml1cPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            /** @var shopCml1cPlugin $plugin */
            $plugin = wa()->getPlugin('cml1c');
        }
        return $plugin;
    }

    public function execute()
    {
        $path = $this->plugin()->path();
        $info = array();
        $info['exists'] = file_exists($path);
        $info['mtime'] = $info['exists'] ? filemtime($path) : null;

        $this->view->assign('info', $info);
        $this->view->assign('enabled', $this->plugin()->getSettings('enabled'));
        $this->view->assign('export_timestamp', $this->plugin()->exportTime());
        $this->view->assign('url', $this->plugin()->getCallbackUrl());

        $runner = new shopCml1cPluginBackendRunController();
        $this->view->assign('map', $runner->mapSetting());
        $this->view->assign('expert', $this->plugin()->getSettings('expert'));

        //$stock_model = new shopStockModel();
        //$this->view->assign('stocks', $stock_model->getAll('id'));
    }
}
