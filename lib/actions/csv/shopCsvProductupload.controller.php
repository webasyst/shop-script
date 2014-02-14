<?php

class shopCsvProductuploadController extends shopUploadController
{
    private function options()
    {
        $translates = array();
        $translates['feature'] = _w('Features');
        $translates['product'] = _w('Basic fields');
        $translates['sku'] = _w('SKU fields');

        $options = array();
        $fields = array(
            'product' => array(
                'name'             => _w('Product name'),
                'summary'          => _w('Summary'),
                'meta_title'       => _w('Title'),
                'meta_keywords'    => _w('META Keyword'),
                'meta_description' => _w('META Description'),
                'description'      => _w('Description'),
                'badge'            => _w('Badge'),
                'status'           => _w('Status'),
                //'sort'             => _w('Product sort order'),
                'tags'             => _w('Tags'),
                'tax_name'         => _w('Taxable'),

                'type_name'        => _w('Product type'),
                'url'              => _w('Storefront link'),
                'currency'         => _w('Currency'),
                'images'           => _w('Product images'),
            ),
            'sku'     => array(
                'skus:-1:sku'            => _w('SKU code'),
                'skus:-1:name'           => _w('SKU name'),
                'skus:-1:price'          => _w('Price'),
                'skus:-1:available'      => _w('Available for purchase'),
                'skus:-1:compare_price'  => _w('Compare at price'),
                'skus:-1:purchase_price' => _w('Purchase price'),
                'skus:-1:stock:0'        => _w('In stock'),
            ),
        );

        $stock_model = new shopStockModel();
        if ($stocks = $stock_model->getAll('id')) {

            foreach ($stocks as $stock_id => $stock) {
                $fields['sku']['skus:-1:stock:'.$stock_id] = _w('In stock').' @'.$stock['name'];
            }
        }

        foreach ($fields as $group => $group_fields) {
            foreach ($group_fields as $id => $name) {
                $options[] = array(
                    'group' => array(
                        'title' => ifset($translates[$group]),
                        'class' => $group,
                    ),
                    'value' => $id,
                    'title' => ifempty($name, $id),
                );
            }
        }

        $group = 'feature';
        $feature_model = new shopFeatureModel();

        //TODO use ajax features mapping
        $limit = null;

        if (!$limit || ($feature_model->countByField(array('parent_id' => null)) < $limit)) {
            $features = $feature_model->select('`code`, `name`,`type`')->fetchAll('code', true);

            foreach ($features as $code => $feature) {
                if (!preg_match('/\.\d$/', $code) && ($feature['type'] != shopFeatureModel::TYPE_DIVIDER)) {
                    $options[] = array(
                        'group'       => array(
                            'title' => ifset($translates[$group]),
                            'class' => $group,
                        ),
                        'value'       => sprintf('features:%s', $code),
                        'title'       => $feature['name'],
                        'description' => $code,
                    );
                }
            }
        } else {
            //use $map for preload some features
            $features = array();
            $feature_options = array();
            array_push($feature_options, array(
                'title' => '—',
                'value' => '',
            ));

            if ($features = array_unique($features)) {
                foreach ($features as $feature) {
                    $feature_options[] = array(
                        'title' => $feature['name'],
                        'value' => sprintf('features:%s', $feature['code']),
                    );
                }
            }

            $feature_options[] = array(
                'value' => 'features:%s',
                'title' => _w('Select feature'),
            );

            unset($option);

            $options[] = array(
                'group'       => array(
                    'title' => ifset($translates[$group]),
                    'class' => $group,
                ),
                'value'       => 'features:%s',
                'title'       => _w('feature'),
                'description' => '',
                'control'     => array(
                    'control' => waHtmlControl::SELECT,
                    'params'  => array(
                        'options' => $feature_options,
                    ),
                ),
            );
        }

        return $options;
    }

    protected function save(waRequestFile $file)
    {
        $path = wa()->getTempPath('csv/upload/');
        waFiles::create($path);
        $original_name = $file->name;
        if ($name = tempnam($path, 'csv')) {
            unlink($name);
            if (($ext = pathinfo($original_name, PATHINFO_EXTENSION)) && preg_match('/^\w+$/', $ext)) {
                $name .= '.'.$ext;
            }
            $file->moveTo($name);
        } else {
            throw new waException(_w('Error file upload'));
        }

        $f = new shopCsvReader($name, waRequest::post('delimiter'), waRequest::post('encoding'));


        $profile_helper = new shopImportexportHelper('csv:product:import');
        $profile = $profile_helper->getConfig();
        $profile['config'] += array(
            'encoding'  => 'UTF-8',
            'delimiter' => ';',
            'map'       => array(),
        );
        $params = array();
        $params['title'] = _w('CSV column map');
        $params['id'] = 'csvproducts';
        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_wrapper'] = '<div class="field"><div class="name">%s</div><div class="value">%s %s</div></div>';
        $params['options'] = $this->options();
        if (count($f->header()) < 2) {
            throw new waException($f->header() ? _w('No data columns were located in the uploaded file. Make sure right separator and encoding were chosen for this upload.') : _w('Unsupported CSV file structure'));
        }
        return array(
            'name'          => htmlentities(basename($f->file()), ENT_QUOTES, 'utf-8'),
            'original_name' => htmlentities(basename($original_name), ENT_QUOTES, 'utf-8'),
            'size'          => waFiles::formatSize($f->size()),
            'original_size' => waFiles::formatSize($file->size),
            'controls'      => waHtmlControl::getControl('Csvmap', 'csv_map', $params),
            'header'        => $f->header(),
        );
    }
}
