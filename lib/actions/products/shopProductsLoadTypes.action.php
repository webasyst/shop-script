<?php
class shopProductsLoadTypesAction extends waViewAction
{
    public function execute()
    {
        $hide = waRequest::get('hide');
        if (strlen($hide)) {
            wa()->getUser()->setSettings('shop', 'collapse_types', intval($hide));
            exit;
        } else {
            $type_model = new shopTypeModel();
            wa()->getUser()->setSettings('shop', 'collapse_types', 0);
            $this->view->assign('types', $type_model->getAll('id'));
        }
    }
}