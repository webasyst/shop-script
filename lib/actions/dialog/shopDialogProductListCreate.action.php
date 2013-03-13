<?php

class shopDialogProductListCreateAction extends waViewAction
{
    private $set_dynamic_default_count = 8;

    public function execute()
    {
        $type = waRequest::get('type', '', waRequest::TYPE_STRING_TRIM);

        $parent_id = waRequest::get('parent_id', 0, waRequest::TYPE_INT);
        if ($parent_id) {
            $category_model = new shopCategoryModel();
            $parent = $category_model->getById($parent_id);
        }

        $this->template = 'DialogProduct'.ucfirst($type).'Create';

        $this->view->assign(array(
            'type' => $type,
            'parent' => $parent_id ? $parent : array()
        ));

        if ($type == 'category') {
            $tag_model = new shopTagModel();
            $this->view->assign('cloud', $tag_model->getCloud());
        } else if ($type == 'set') {
            $this->view->assign('default_count', $this->set_dynamic_default_count);
        }
    }
}