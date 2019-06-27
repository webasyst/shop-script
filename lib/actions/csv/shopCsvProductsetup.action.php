<?php

/**
 * @method shopConfig getConfig()
 */
class shopCsvProductsetupAction extends waViewAction
{
    public function execute()
    {
        $direction = (waRequest::request('direction', 'import') == 'export') ? 'export' : 'import';
        $profile_helper = new shopImportexportHelper('csv:product:'.$direction);
        $this->view->assign('profiles', $profile_helper->getList());
        $profile = $profile_helper->getConfig();

        if ($direction == 'export') { //export section
            $profile['config'] += array(
                'encoding'    => 'UTF-8',
                'images'      => false,
                'features'    => false,
                'description' => true,
                'domain'      => null,
                'delimiter'   => ';',
                'hash'        => '',
            );


            $info = $this->getAvailableFiles($profile, 5);
            $this->view->assign('info', $info);


            $set_model = new shopSetModel();
            $this->view->assign('sets', $set_model->getAll());


            $current_domain = '';
            $settlements = $this->getApplicationSettlements($profile, $current_domain);

            $this->view->assign('current_domain', $current_domain);
            $this->view->assign('settlements', $settlements);


            $image_sizes = $this->getImageSizes();
            $this->view->assign('image_sizes', $image_sizes);

        } else {
            $profile['config'] += array(
                'encoding'  => 'UTF-8',
                'delimiter' => ';',
                'map'       => array(),
            );

            $base_path = wa()->getConfig()->getPath('root');
            $app_path = array(
                'shop' => wa()->getDataPath(null, false, 'shop'),
                'site' => wa()->getDataPath(null, true, 'site'),
            );
            foreach ($app_path as &$path) {
                $path = preg_replace('@^([\\\\/])@', '', str_replace($base_path, '', $path.'/'));
            }
            unset($path);

            $this->view->assign('upload_path', waSystem::getSetting('csv.upload_path', 'path/to/folder/with/source/images/'));
            $this->view->assign('upload_app', waSystem::getSetting('csv.upload_app', 'shop'));
            $this->view->assign('app_path', $app_path);

            $product_model = new shopProductModel();
            $sku_model = new shopProductSkusModel();

            $meta_fields = array(
                'product' => $product_model->getMetadata(),
                'sku'     => $sku_model->getMetadata(),
            );

            $this->view->assign('meta_fields', $meta_fields);
        }

        $this->view->assign('profile', $profile);

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getTypes());

        $encoding = $this->getAppropriateEncoding();

        $this->view->assign('encoding', $encoding);
        $this->view->assign('direction', $direction);

        $plugins = wa()->getConfig()->getPlugins();
        $this->view->assign('plugin', ifempty($plugins['csvproducts'], array()));
    }

    private function getAppropriateEncoding()
    {
        $encoding = array_diff(mb_list_encodings(), array(
            'pass',
            'wchar',
            'byte2be',
            'byte2le',
            'byte4be',
            'byte4le',
            'BASE64',
            'UUENCODE',
            'HTML-ENTITIES',
            'Quoted-Printable',
            '7bit',
            '8bit',
            'auto',
        ));

        $popular = array_intersect(array('UTF-8', 'Windows-1251', 'ISO-8859-1',), $encoding);

        asort($encoding);
        $encoding = array_unique(array_merge($popular, $encoding));
        return $encoding;
    }

    private function getImageSizes()
    {
        $sizes = $this->getConfig()->getImageSizes();
        $image_sizes = array();
        foreach ($sizes as $size) {
            $size_info = shopImage::parseSize((string)$size);
            $type = $size_info['type'];
            $width = $size_info['width'];
            $height = $size_info['height'];
            switch ($type) {
                case 'crop':
                    $image_sizes[$size] = sprintf('%s: %d x %d px', _w('Square crop'), $width, $height);
                    break;
                case 'max':
                    $image_sizes[$size] = sprintf('%s (%s, %s) = %d px', _w('Max'), _w('Width'), _w('Height'), $width);
                    break;
                case 'width':
                    $image_sizes[$size] = sprintf('%s = %d px, %s = %s', _w('Width'), $width, _w('Height'), _w('auto'));
                    break;
                case 'height':
                    $image_sizes[$size] = sprintf('%s = %s, %s = %d px', _w('Width'), _w('auto'), _w('Height'), $height);
                    break;
                case 'rectangle':
                    $image_sizes[$size] = sprintf('%s = %s px, %s = %d px', _w('Width'), $width, _w('Height'), $height);
                    break;
            }
        }
        return $image_sizes;
    }

    private function getAvailableFiles($profile, $length)
    {
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

            usort($info, wa_lambda('$a, $b', 'return (max(-1, min(1, $a["mtime"] - $b["mtime"])));'));
        }

        return array_slice($info, -$length, $length, true);
    }

    private function getApplicationSettlements($profile, &$current_domain)
    {
        $settlements = array(
            '' => _w('All storefronts'),
        );

        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');

        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $settlement = $domain.'/'.$route['url'];
                if ($settlement == $profile['config']['domain']) {
                    $current_domain = $settlement;
                }
                $settlements[$settlement] = $settlement;
            }
        }
        return $settlements;
    }
}
