<?php
/**
 * Рисует форму для генерации заказов.
 */
class shopGenorderPluginBackendGeneratorAction extends waViewAction
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        $this->view->assign('params', $this->params);
        $this->view->assign('uniqid', str_replace(uniqid('s', true), '.', '-'));
    }
}
