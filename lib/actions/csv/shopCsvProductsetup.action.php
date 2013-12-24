<?php
class shopCsvProductsetupAction extends waViewAction
{
    public function execute()
    {
        $direction = (waRequest::request('direction', 'import') == 'export') ? 'export' : 'import';
        $profile_helper = new shopImportexportHelper('csv:product:'.$direction);
        $this->view->assign('profiles', $profile_helper->getList());
        $profile = $profile_helper->getConfig();
        if ($direction == 'export') { //export section TODO


            $profile['config'] += array(
                'encoding'  => 'UTF-8',
                'images'    => true,
                'features'  => true,
                'domain'    => null,
                'delimiter' => ';',
                'hash'      => '',
            );

            $info = array();
            if (!empty($profile['id'])) {
                $path = wa()->getTempPath('csv/download/'.$profile['id']);
                $files = waFiles::listdir($path);


                foreach ($files as $file) {
                    $file_path = $path.'/'.$file;
                    $info[] = array(
                        'name'  => $file,
                        'mtime' => filemtime($file_path),
                        'size'  => filesize($file_path),
                    );
                }

                usort($info, create_function('$a, $b', 'return (max(-1, min(1, $a["mtime"] - $b["mtime"])));'));
            }

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
                    } elseif ($settlement == $profile['config']['domain']) {
                        $current_domain = $settlement;
                    }
                    $settlements[] = $settlement;
                }
            }
            $this->view->assign('current_domain', $current_domain);
            $this->view->assign('settlements', $settlements);

        } else {
            $profile['config'] += array(
                'encoding'  => 'UTF-8',
                'delimiter' => ';',
                'map'       => array(),
            );

            $upload_path = waSystem::getSetting('csv.upload_path', 'path/to/folder/with/source/images/');
            $this->view->assign('upload_path', $upload_path);
            $this->view->assign('upload_full_path', wa()->getDataPath($upload_path));
        }

        $this->view->assign('profile', $profile);

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
