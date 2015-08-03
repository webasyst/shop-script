<?php
/**
 * Promos module is responsible for promos editor in Products section in backend.
 *
 * Promos are a convenient way to manage slider or similar list of images
 * on storefront main page.
 *
 * See:
 * - shopViewHelper->promos(), i.e. $wa->shop->promos()
 * - plugin hooks: frontend_promo, backend_promo_dialog, promo_save, promo_delete
 */
class shopPromosActions extends waViewActions
{
    // List of promos for a single storefront
    protected function defaultAction()
    {
        $storefronts = $this->getStorefronts();
        $promo_routes_model = new shopPromoRoutesModel();
        foreach($promo_routes_model->getMaxSorts() as $d => $sort) {
            if (empty($storefronts[$d]) && $d != '%all%') {
                $storefronts[$d] = array(
                    'storefront' => $d,
                    'name' => $d,
                    'active' => false,
                    'sort' => 0,
                );
            }
        }

        $storefront_route = waRequest::request('storefront');
        if (empty($storefront_route) || empty($storefronts[$storefront_route])) {
            $storefront = reset($storefronts);
        } else {
            $storefront = $storefronts[$storefront_route];
        }

        $promo_model = new shopPromoModel();
        $promos = $promo_model->getByStorefront($storefront['storefront']);
        foreach($promos as &$p) {
            $p['image_url'] = shopHelper::getPromoImageUrl($p['id'], $p['ext']);
        }
        unset($p);

        $counts = $promo_routes_model->getStorefrontCounts();
        foreach($storefronts as &$s) {
            $s['count'] = ifset($counts[$s['storefront']], 0);
        }
        unset($s);

        $this->view->assign(array(
            'promos_count' => $promo_model->countAll(),
            'storefronts' => $storefronts,
            'storefront' => $storefront,
            'promos' => $promos,
        ));
    }

    // Saves relative positions after drag-and-drop in promos list
    protected function sortAction()
    {
        $storefront = waRequest::post('storefront', '', 'string');
        $ids = waRequest::post('ids', array(), 'array');
        if (!$ids || !$storefront) {
            exit;
        }

        $sort = 0;
        foreach($ids as $promo_id) {
            $sort++;
            $promo_routes_model = new shopPromoRoutesModel();
            $promo_routes_model->updateByField(array(
                'promo_id' => $promo_id,
                'storefront' => $storefront
            ), array(
                'sort' => $sort,
            ));
        }
        exit;
    }

    // Dialog to create new or modify existing promo
    protected function editorAction()
    {
        $promo_model = new shopPromoModel();

        $id = waRequest::request('id', 0, 'int');
        if ($id) {
            $promo = $promo_model->getById($id);
            if (!$promo) {
                throw new waException('Not found', 404);
            }
        } else {
            $promo = $promo_model->getEmptyRow();
        }

        $storefronts = $this->getStorefronts($id);

        $errors = array();
        $data = waRequest::post('promo', array(), 'array');
        if ($data) {
            $data = array_intersect_key($data, $promo) + $promo;
            unset($data['id']);

            if (empty($data['link'])) {
                $errors['promo[link]'] = _w('This field is required.');
            }

            $file_info = wa()->getStorage()->get('shop/promo/uploaded');
            if ($file_info && @is_writable($file_info['filepath'])) {
                $data['ext'] = $file_info['extension'];
            } else if (!$id) {
                $errors['image'] = _w('This field is required.');
            }

            // max_sort
            $storefronts_data = waRequest::post('storefronts', array(), 'array');
            if (!empty($storefronts_data['%all%'])) {
                foreach($storefronts as $s) {
                    if ($s['active'] && empty($storefronts_data[$s['storefront']])) {
                        $storefronts_data[$s['storefront']] = $s['sort'];
                    }
                }
            }

            $promo_routes_model = new shopPromoRoutesModel();
            $max_sorts = $promo_routes_model->getMaxSorts();
            foreach($storefronts_data as $route => $sort) {
                if ($sort < 0) {
                    if (empty($max_sorts[$route])) {
                        $max_sorts[$route] = 0;
                    }
                    $max_sorts[$route]++;
                    $storefronts_data[$route] = $max_sorts[$route];
                }
            }
            if(empty($storefronts_data)) {
                $errors['storefronts'] = _w('Select at least one storefront.');
            }

            if (!$errors) {
                // Save DB row
                if (empty($id)) {
                    $id = $promo_model->insert($data);
                } else {
                    $promo_model->updateById($id, $data);
                }

                // save image
                $filepath = wa('shop')->getDataPath('promos/'.$id.'.'.$file_info['extension'], true);
                @rename($file_info['filepath'], $filepath);
                $this->clearUpload();

                // Save storefronts
                $values = array();
                foreach($storefronts_data as $route => $sort) {
                    $values[] = array(
                        'sort' => $sort,
                        'promo_id' => $id,
                        'storefront' => $route,
                    );
                }
                $promo_routes_model->deleteByField('promo_id', $id);
                $promo_routes_model->multipleInsert($values);

                $promo = $data;
                $promo['id'] = $id;
                $storefronts = $this->getStorefronts($id);
                return $this->closeAndReload($promo, $storefronts);
            } else {
                $promo = $data;
                $promo['id'] = $id;
                foreach($storefronts as $d => $s) {
                    if (empty($storefronts_data[$d])) {
                        $storefronts[$d]['sort'] = -1;
                    } else {
                        $storefronts[$d]['sort'] = $storefronts_data[$d];
                    }
                }
            }
        } else {
            $this->clearUpload();
        }

        $storefronts = array(
            '%all%' => array(
                'storefront' => '%all%',
                'name' => _w('All storefronts'),
                'active' => true,
                'sort' => ifset($storefronts['%all%']['sort'], !$id && !$data ? 1 : -1),
            ),
        ) + $storefronts;

        $this->view->assign(array(
            'file_uploaded' => !!wa()->getStorage()->get('shop/promo/uploaded'),
            'storefronts' => $storefronts,
            'errors' => $errors,
            'p' => $promo,
        ));
    }

    // Helper for editorAction()
    protected function closeAndReload($promo, $storefronts)
    {
        $hashes = array();
        foreach($storefronts as $s) {
            if ($s['storefront'] != '%all%' && $s['sort'] > 0) {
                $hashes[] = '#/promos/storefront='.$s['storefront'];
            }
        }
        $hashes = json_encode($hashes);

        echo <<<EOF
            <script>(function() { "use strict";
                var hashes = {$hashes};
                if (!hashes.length || $.inArray(window.location.hash, hashes) >= 0) {
                    $.products.dispatch();
                } else {
                    window.location.hash = hashes[0];
                }
                $('#promo-editor-dialog').trigger('close');
            })();</script>
EOF;
        exit;
    }

    // Delete button in promo editor
    protected function deleteAction()
    {
        $id = waRequest::post('id', 0, 'int');
        if ($id) {
            $promo_model = new shopPromoModel();
            $promo_model->deleteById($id);
            $promo_routes_model = new shopPromoRoutesModel();
            $promo_routes_model->deleteByField('promo_id', $id);
        }
    }

    // Upload file to temporary dir, later to save to promo in editorAction()
    protected function uploadAction()
    {
        $files = waRequest::file('image');
        if ($files->count() > 0) {
            $result = $this->uploadActionHelper($files[0]);
        } else {
            $result = array(
                'status' => 'error',
                'error' => _w('Error file upload'),
            );
        }

        if (strpos(waRequest::server('HTTP_ACCEPT', ' application/json '), 'application/json') !== false) {
            $this->getResponse()->addHeader('Content-type', 'application/json');
        } else {
            $this->getResponse()->addHeader('Content-type', 'text/plain; charset=utf-8');
        }
        $this->getResponse()->sendHeaders();
        echo json_encode($result);
        exit;
    }

    // Returns to browser the (temporary) image that has been uploaded into the editor
    // but had not been saved yet.
    protected function uploadedAction()
    {
        // Remove old uploaded file if exists
        $file_info = wa()->getStorage()->get('shop/promo/uploaded');
        if ($file_info && is_writable($file_info['filepath'])) {
            $file_path = $file_info['filepath'];
            $this->getResponse()->addHeader("Content-type", waFiles::getMimeType('a.'.$file_info['extension']));
        } else {
            $file_path = wa('shop')->getAppPath('img/image-dummy.png');
            $this->getResponse()->addHeader("Content-type", waFiles::getMimeType('a.png'));
        }
        $this->getResponse()->sendHeaders();
        readfile($file_path);
    }

    protected function uploadActionHelper(waRequestFile $file)
    {
        // Make sure the file has correct extension
        $ext = strtolower($file->extension);
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
            return array(
                'status' => 'error',
                'error' => _w('Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.'),
            );
        }

        // Make sure it's an image
        try {
            $file->waImage();
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'error' => _ws('Not an image or invalid image:').' '.$file->name,
            );
        }

        // Save to temporary folder
        $dir = wa()->getTempPath('promos', 'shop');
        $filepath = tempnam($dir, 'i');
        waFiles::delete($filepath); // otherwise file would stay 0600 which is inconvenient for some
        $file->moveTo($filepath);

        // Remove old uploaded file if exists
        $this->clearUpload();

        // Remember file info in session
        wa()->getStorage()->set('shop/promo/uploaded', array(
            'filepath' => $filepath,
            'extension' => $ext == 'jpeg' ? 'jpg' : $ext,
        ));

        return array(
            'status' => 'ok',
            'url' => '?module=promos&action=uploaded&_='.time(),
        );
    }

    protected function clearUpload()
    {
        $old_file_info = wa()->getStorage()->get('shop/promo/uploaded');
        if ($old_file_info && is_writable($old_file_info['filepath'])) {
            unlink($old_file_info['filepath']);
        }
        wa()->getStorage()->del('shop/promo/uploaded');
    }

    protected function getStorefronts($promo_id=null, $all=false)
    {
        $storefronts = array();
        foreach(wa()->getRouting()->getDomains() as $d) {
            $storefronts[$d] = array(
                'storefront' => $d,
                'name' => $d,
                'active' => true,
                'sort' => -1,
            );
        }

        if ($promo_id) {
            $promo_routes_model = new shopPromoRoutesModel();
            $rows = $promo_routes_model->getByField('promo_id', $promo_id, 'storefront');
            foreach($rows as $d => $row) {
                if (empty($storefronts[$d])) {
                    $storefronts[$d] = array(
                        'storefront' => $d,
                        'name' => $d,
                        'active' => false,
                    );
                }
                $storefronts[$d]['sort'] = $row['sort'];
            }
        }

        return $storefronts;
    }
}

