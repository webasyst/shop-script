<?php
class shopCsvProductsetupAction extends waViewAction
{
    public function execute()
    {
        $direction = (waRequest::request('direction', 'import') == 'export') ? 'export' : 'import';

        if ($direction == 'export') { //export section TODO

            $path = wa()->getTempPath('csv/download/');
            $files = waFiles::listdir($path);

            $info = array();
            foreach ($files as $file) {
                $file_path = $path.'/'.$file;
                $info[] = array(
                    'name'  => $file,
                    'mtime' => filemtime($file_path),
                    'size'  => filesize($file_path),
                );
            }
            asort($info);

            $this->view->assign('info', array_slice($info, -5, 5, true));

            $set_model = new shopSetModel();
            $this->view->assign('sets', $set_model->getAll());

            $routing = wa()->getRouting();
            $settlements = array();
            $current_domain = null;
            $domain_routes = $routing->getByApp('shop');
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $route) {
                    $settlement = $domain.'/'.$route['url'];
                    if ($current_domain === null) {
                        $current_domain = $settlement;
                    }
                    $settlements[] = $settlement;
                }
            }
            $this->view->assign('current_domain', $current_domain);

            $this->view->assign('settlements', $settlements);
        } else {
            $upload_path = waSystem::getSetting('csv.upload_path', 'path/to/folder/with/source/images/');
            $this->view->assign('upload_path', $upload_path);
            $this->view->assign('upload_full_path', wa()->getDataPath($upload_path));
        }

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getTypes());

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
