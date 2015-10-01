<?php

class shopPluginsActions extends waPluginsActions
{
    protected $plugins_hash = '#';
    protected $is_ajax = false;

    protected function preExecute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
    }

    public function defaultAction()
    {
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->getView()->assign(array(
            'container_class' => 'content left15px right15px s-nolevel2-box',
            'container_before_html' => '<div class="sidebar right15px">
    <div class="block s-nolevel2-sidebar"></div>
</div>
<div class="sidebar left15px">
    <div class="block s-nolevel2-sidebar"></div>
</div>',
        ));

        parent::defaultAction();
    }

    protected function getTemplatePath($action = null)
    {
        if ($action == 'settings') {
            return $this->getConfig()->getAppPath('templates/actions/plugins/').'PluginsSettings.html';
        }
        return parent::getTemplatePath($action);
    }

}