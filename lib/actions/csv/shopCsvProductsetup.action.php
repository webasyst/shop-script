<?php
class shopCsvProductsetupAction extends waViewAction
{
    public function execute()
    {
        $direction = (waRequest::request('direction', 'import') == 'export') ? 'export' : 'import';

        if ($direction == 'export') { //export section TODO
            $name = 'export.csv';
            $path = wa()->getDataPath('temp/csv/download/'.$name);
            $info = array();
            $info['exists'] = $path && file_exists($path);
            $info['mtime'] = $info['exists'] ? filemtime($path) : null;

            $this->view->assign('info', $info);

            $set_model = new shopSetModel();
            $this->view->assign('sets', $set_model->getAll());
        } else {
            try {
                waFiles::delete(wa()->getDataPath('temp/csv/upload/'), true);
            } catch (waException $ex) {

            }
        }

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getAll($type_model->getTableId()));

        $encoding = array_diff(mb_list_encodings(), array(
            'pass', 'auto', 'wchar', 'byte2be', 'byte2le', 'byte4be', 'byte4le',
            'BASE64', 'UUENCODE', 'HTML-ENTITIES', 'Quoted-Printable', '7bit', '8bit',
        ));

        $popular = array_intersect(array('UTF-8', 'Windows-1251', 'ISO-8859-1'), $encoding);

        asort($encoding);
        $encoding = array_unique(array_merge($popular, $encoding));

        $this->view->assign('encoding', $encoding);
        $plugins = wa()->getConfig()->getPlugins();
        $this->view->assign('direction', $direction);
        $this->view->assign('plugin', ifempty($plugins['csvproducts'], array()));
    }
}
