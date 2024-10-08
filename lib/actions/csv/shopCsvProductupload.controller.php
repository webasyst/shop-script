<?php

class shopCsvProductuploadController extends shopUploadController
{
    /** @var shopCsvReader */
    private $reader;

    public static function getMapFields($flat = false, $extra_fields = false)
    {
        //TODO пометить поля, допускающий множественные значения (валидация)
        $fields = array(
            'row_type'              => _w('Row type'),
            'product'               => array(
                'id'                        => _w('Product ID'),
                'name'                      => _w('Product name'), //1
                'currency'                  => _w('Currency'), //4
                'summary'                   => _w('Summary'),
                'description'               => _w('Description'),
                'badge'                     => _w('Badge'),
                'status'                    => _w('Status'),
                'sku_type'                  => _w('Product variety selection'),
                'type_name'                 => _w('Product type'),
                'tags'                      => _w('Tags'),
                'tax_name'                  => _w('Taxable'),
                'meta_title'                => _w('Title'),
                'meta_keywords'             => _w('META Keyword'),
                'meta_description'          => _w('META Description'),
                'url'                       => _w('Storefront link'),
                'images'                    => array(
                    'title' => _w('Product images'),
                    'data'  => array(
                        'multiple' => true,
                    ),
                ),
                'images_descriptions' => array(
                    'title' => _w('Product image descriptions'),
                    'data'  => array(
                        'multiple' => true,
                    ),
                ),
                'video_url'           => _w('Video URL on YouTube, Vimeo, VK.com or Rutube'),
                'params'              => _w('Custom parameters'),
            ),
            'product_custom_fields' => array(),
            'sku'                   => array(
                'skus:-1:id'               => _w('SKU ID'), //2
                'skus:-1:name'             => _w('SKU name'), //2
                'skus:-1:sku'              => _w('SKU code'), //3
                'skus:-1:price'            => _w('Price'),
                'skus:-1:available'        => _w('Available for purchase'),
                'skus:-1:status'           => _w('Visibility in the storefront'),
                'skus:-1:compare_price'    => _w('Compare at price'),
                'skus:-1:purchase_price'   => _w('Purchase price'),
                'skus:-1:stock:0'          => _w('In stock'),
                'skus:-1:_primary'         => _w('Primary SKU'),
            ),
            'sku_custom_fields'     => array(),
        );

        if (shopFrac::isEnabled()) {
            $fields['product'] += [
                'order_multiplicity_factor' => _w('Add-to-cart step'),
                'order_count_min'           => _w('Minimum orderable quantity'),
                'order_count_step'          => _w('Quantity adjustment value via “+/-” buttons'),
            ];
        }
        if (shopUnits::stockUnitsEnabled()) {
            $fields['product']['stock_unit_id'] = _w('Stock quantity unit');
        }
        if (shopUnits::baseUnitsEnabled()) {
            $fields['product'] += [
                 'base_unit_id'     => _w('Base quantity unit'),
                 'stock_base_ratio' => _w('Stock to base quantity units ratio'),
            ];
        }

        if ($extra_fields) {
            $product_model = new shopProductModel();
            $sku_model = new shopProductSkusModel();
            $meta_fields = array(
                'product' => $product_model->getMetadata(),
                'sku'     => $sku_model->getMetadata(),
            );
            $black_list = array(
                'contact_id',
                'create_datetime',
                'edit_datetime',
                'type_id',
                'image_id',
                'image_filename',
                'tax_id',
                'cross_selling',
                'upselling',
                'total_sales',
                'sku_count',
                'sku_id',
                'ext',
                'price',
                'compare_price',
                'min_price',
                'max_price',
                'count',
                'rating_count',
                'category_id',
                'base_price',
                'min_base_price',
                'max_base_price',
                'base_price_selectable',
                'compare_price_selectable',
                'purchase_price_selectable',
                'rating',
                'stock_base_ratio',
                'order_count_min',
                'order_count_step',
                'count_denominator',
            );

            if (!shopFrac::isEnabled()) {
                $black_list = array_merge($black_list, [
                    'order_multiplicity_factor',
                    'stock_unit_id',
                    'base_unit_id',
                ]);
            }

            $white_list = array(
                'id_1c' => 'Идентификатор 1С',
            );

            //XXX add callback for custom fields

            //TODO implode
            foreach ($meta_fields['product'] as $field => $info) {
                if (!in_array($field, $black_list)) {
                    $name = ifset($white_list[$field], $field);
                    if (!empty($meta_fields['sku'][$field])) {
                        if (!isset($fields['sku']['skus:-1:'.$field])) {
                            $fields['sku']['skus:-1:'.$field] = $name;
                        }

                        if (!isset($fields['product'][$field])) {
                            $fields['product'][$field] = sprintf('%s: %s', _w('Product'), $name);
                        }

                    } else {
                        if (!isset($fields['product'][$field])) {
                            $fields['product'][$field] = $name;
                        }
                    }
                }
            }
        }

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');
        if ($stocks) {
            foreach ($stocks as $stock_id => $stock) {
                $fields['sku']['skus:-1:stock:'.$stock_id] = _w('In stock').' @'.$stock['name'];
            }
        }

        /**
         * @event product_custom_fields
         * @return array[string]string $return[%plugin_id%]['product'] array
         * @return array[string]string $return[%plugin_id%]['sku'] array
         */
        $custom_fields = wa('shop')->event('product_custom_fields');
        if ($custom_fields) {
            foreach ($custom_fields as $plugin_id => $custom_plugin_fields) {
                # %plugin_id%-plugin became %plugin_id%_plugin
                $plugin_id = preg_replace('@-plugin$@', '_plugin', $plugin_id);
                if (isset($custom_plugin_fields['product'])) {
                    foreach ($custom_plugin_fields['product'] as $field_id => $field_name) {
                        $fields['product_custom_fields'][$plugin_id.':'.$field_id] = $field_name;
                    }
                }
                if (isset($custom_plugin_fields['sku'])) {
                    foreach ($custom_plugin_fields['sku'] as $field_id => $field_name) {
                        $fields['sku_custom_fields']['skus:-1:'.$plugin_id.':'.$field_id] = $field_name;
                    }
                }
            }
        }


        if ($flat) {
            $fields_ = $fields;
            $fields = array(
                'row_type' => _w('Row type'),
            );
            $flat_order = array(
                'product:name',
                'sku:skus:-1:name',
                'sku:skus:-1:sku',
                'product:currency',
            );

            foreach ($flat_order as $field) {
                list($type, $field) = explode(':', $field, 2);
                $fields[$field] = $fields_[$type][$field];
                unset($fields_[$type][$field]);
            }
            $fields += $fields_['sku'];
            $fields += $fields_['product'];
        }

        return $fields;

    }

    private function options()
    {
        $multiple = true;

        $translates = array();
        $translates['row_type'] = _w('Row type');
        $translates['product'] = _w('Basic fields');
        $translates['product_custom_fields'] = _w("Custom product fields");
        $translates['sku'] = _w('SKU fields');
        $translates['sku_custom_fields'] = _w("Custom SKU fields");

        $translates['feature'] = _w('Add to existing');
        $translates['feature+'] = _w('Add as new feature');


        $options = array();
        $fields = self::getMapFields(false, true);
        foreach ($fields as $group => $group_fields) {
            if ($group == 'row_type') {
                $option = array(
                    'group' => array(
                        'title' => ifset($translates[$group]),
                        'class' => $group,
                    ),
                    'title' => ifset($translates[$group]),
                    'value' => $group,
                );
                $options[] = $option;
            } else {
                foreach ($group_fields as $id => $name) {
                    $option = array(
                        'group' => array(
                            'title' => ifset($translates[$group]),
                            'class' => $group,
                        ),
                        'value' => $id,
                    );
                    if (is_array($name)) {
                        $option += $name;
                        if (empty($option['title'])) {
                            $option['title'] = $id;
                        }
                    } else {
                        $option['title'] = ifempty($name, $id);
                    }

                    if (preg_match('@^[a-z][a-z0-9_]+$@', $option['title'])) {
                        $option['no_match'] = true;
                        $option['title'] = $option['title'].' *';
                    }
                    $options[] = $option;
                }
            }
        }

        $limit = $this->getConfig()->getOption('features_per_page');
        $group = 'feature';
        $auto_complete = false;
        $feature_model = new shopFeatureModel();
        if ($feature_model->countByField(array('parent_id' => null)) < $limit) {
            $features = $feature_model->getFeatures(true); /*, true*/
        } else {
            $auto_complete = true;
            $header = array_unique(array_map('mb_strtolower', $this->reader->header()));
            $header = array_slice($header, 0, max(4096, $limit));
            $features = $feature_model->getFeatures('name', $header);
        }
        foreach ($features as $id => $feature) {
            if ($feature['type'] == shopFeatureModel::TYPE_DIVIDER) {
                unset($features[$id]);
            }
        }

        foreach ($features as $code => $feature) {
            $code = $feature['code'];
            if (!preg_match('/\.\d$/', $code)
                && ($feature['type'] != shopFeatureModel::TYPE_DIVIDER)
            ) {
                $options[] = array(
                    'group'       => array(
                        'title' => ifset($translates[$group]),
                        'class' => $group,
                    ),
                    'value'       => sprintf('features:%s', $code),
                    'title'       => $feature['name'],
                    'description' => $code,
                    'data'        => array(
                        'multiple' => !empty($feature['multiple']),
                    ),
                );
            }
        }

        if ($auto_complete) {
            $options['autocomplete'] = array(
                'group'    => array(
                    'title' => ifset($translates[$group]),
                    'class' => $group,
                ),
                'value'    => 'features:%s',
                'title'    => _w('Select feature'),
                'callback' => array(),
                'no_match' => true,
            );
        }

        if ($this->getUser()->getRights('shop', 'settings')) {

            $group = 'feature+';
            foreach (shopFeatureModel::getTypes() as $f) {
                if ($f['available']
                    && ($f['type'] != shopFeatureModel::TYPE_DIVIDER)
                ) {
                    if (empty($f['subtype'])) {
                        if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $f['type']))) {
                            $options[] = array(
                                'group'    => & $translates[$group],
                                'value'    => sprintf("f+:%s:%d:%d", $f['type'], $f['multiple'], $f['selectable']),
                                'title'    => empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']),
                                'no_match' => true,
                                'data'     => array(
                                    'multiple' => !empty($f['multiple']),
                                ),
                            );
                        }
                    } else {
                        foreach ($f['subtype'] as $sf) {
                            if ($sf['available']) {
                                $type = str_replace('*', $sf['type'], $f['type']);
                                if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $type))) {
                                    $options[] = array(
                                        'group'    => & $translates[$group],
                                        'value'    => sprintf("f+:%s:%d:%d", $type, $f['multiple'], $f['selectable']),
                                        'title'    => (empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']))." — {$sf['name']}",
                                        'no_match' => true,
                                        'data'     => array(
                                            'multiple' => !empty($f['multiple']),
                                        ),
                                    );
                                }
                            }
                        }
                    }
                }
            }
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
            throw new waException(_w('File uploading error'));
        }
        $encoding = waRequest::post('encoding', 'UTF-8');
        $delimiter = waRequest::post('delimiter');
        try {
            $this->reader = new shopCsvReader($name, $delimiter, $encoding);

            #suggest file delimiter
            $delimiters = array(';', ',', 'tab');
            $used_delimiters = array($delimiter);
            while ((count($this->reader->header()) < 2) && ($delimiter = array_diff($delimiters, $used_delimiters))) {
                $delimiter = reset($delimiter);
                $used_delimiters[] = $delimiter;
                $this->reader->delete();
                $this->reader = new shopCsvReader($name, $delimiter, $encoding);
            }

            #retard passed delimiter
            if (count($this->reader->header()) < 2) {
                $this->reader->delete();
                $delimiter = waRequest::post('delimiter');
                $this->reader = new shopCsvReader($name, $delimiter, $encoding);
            }

            #suggest encoding
            $encodings = array('UTF-8', 'Windows-1251', 'ISO-8859-1');
            $used_encodings = array($encoding);
            while (in_array(false, (array)$this->reader->header(), true) && ($encoding = array_diff($encodings, $used_encodings))) {
                $encoding = reset($encoding);
                $used_encodings[] = $encoding;
                $this->reader->delete();
                $this->reader = new shopCsvReader($name, $delimiter, $encoding);
            }

            if (in_array(false, (array)$this->reader->header(), true) || (count($this->reader->header()) < 2)) {
                throw new waException($this->reader->header() ? _w('No data columns were located in the uploaded file. Make sure right separator and encoding were chosen for this upload.') : _w('Unsupported CSV file structure'));
            }

            $profile_helper = new shopImportexportHelper('csv:product:import');
            $profile = $profile_helper->getConfig();
            $profile['config'] += array(
                'encoding'  => $encoding,
                'delimiter' => $delimiter,
                'map'       => array(),
            );

            $params = array();
            $params['id'] = 'csvproducts';
            $params['title_wrapper'] = '%s';
            $params['description_wrapper'] = '<br><span class="hint">%s</span>';
            $params['control_wrapper'] = '<div class="field"><div class="name">%s</div><div class="value">%s %s</div></div>';
            $params['options'] = $this->options();
            $control = true ? shopCsvReader::TABLE_CONTROL : shopCsvReader::MAP_CONTROL;

            switch ($control) {
                case shopCsvReader::TABLE_CONTROL:
                    $params['preview'] = 50;
                    $params['columns'] = array(
                        array('shopCsvProductviewController', 'tableRowHandler'),
                        '&nbsp;',
                    );

                    $params['control_wrapper'] = '<div class="field"><div class="value" style="overflow-x:auto;margin-left:0;">%s %s</div></div>';
                    $params['title_wrapper'] = false;
                    $params['row_handler'] = 'csv_product/rows/';
                    $params['row_handler_string'] = true;
                    $params['autocomplete_handler'] = 'csv_product/autocomplete/reset/';
                    break;
                case shopCsvReader::MAP_CONTROL:
                default:
                    $control = shopCsvReader::MAP_CONTROL;
                    break;
            }

            return array(
                'name'           => htmlentities(basename($this->reader->file()), ENT_QUOTES, 'utf-8'),
                'original_name'  => htmlentities(basename($original_name), ENT_QUOTES, 'utf-8'),
                'size'           => waFiles::formatSize($this->reader->size()),
                'original_size'  => waFiles::formatSize($file->size),
                'controls'       => waHtmlControl::getControl($control, 'csv_map', $params),
                'control'        => $control,
                'header'         => $this->reader->header(),
                'columns_offset' => count(ifset($params['columns'], array())),
                'delimiter'      => $delimiter,
                'encoding'       => $encoding,
            );

        } catch (waException $ex) {
            if ($this->reader) {
                $this->reader->delete(true);
            }
            throw $ex;
        }
    }
}
