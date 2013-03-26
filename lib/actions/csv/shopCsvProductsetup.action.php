<?php
class shopCsvProductsetupAction extends waViewAction
{
    public function execute()
    {
        if (false) { //export section TODO
            $path = false;
            $info = array();
            $info['exists'] = $path && file_exists($path);
            $info['mtime'] = $info['exists'] ? filemtime($path) : null;

            $this->view->assign('info', $info);

            $type_model = new shopTypeModel();
            $this->view->assign('types', $type_model->getAll());

            $set_model = new shopSetModel();
            $this->view->assign('sets', $set_model->getAll());
        } else {
            try {
                waFiles::delete(wa()->getDataPath('temp/csv/upload/'), true);
            } catch (waException $ex) {

            }
            $this->view->assign('encoding', mb_list_encodings());
        }
        $plugins = wa()->getConfig()->getPlugins();

        $this->view->assign('plugin', ifempty($plugins['csvproducts'], array()));
    }
}
