<?php
/**
 * Sidebar for product editor
 */
class shopProdSidebarAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $this->view->assign('id', $product_id);
    }
}
