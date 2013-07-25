<?php


class shopSmartfiltersPluginShowAction extends waViewAction {

    public function setFilters($filters)
    {
        $this->view->assign('smartfilters', $filters);
    }
}