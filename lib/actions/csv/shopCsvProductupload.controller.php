<?php

class shopCsvProductuploadController extends shopUploadController
{
    /**
     * @var shopProductImagesModel
     */
    private $model;

    private function options()
    {
        $translates = array();
        $translates['feature'] = _w('Features');
        $translates['product'] = _w('Basic fields');
        $translates['sku'] = _w('SKU fields');

        $options = array();
        $fileds = array(
            'product' => array(
                'name'             => _w('Product name'),
                'summary'          => _w('Summary'),
                'meta_title'       => _w('Title'),
                'meta_keywords'    => _w('META Keyword'),
                'meta_description' => _w('META Description'),
                'description'      => _w('Description'),
                'sort'             => _w('Product sort order'),
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
                $fileds['sku']['skus:-1:stock:'.$stock_id] = _w('In stock').' @'.$stock['name'];
            }
        }

        foreach ($fileds as $group => $group_fields) {
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

        $features_model = new shopFeatureModel();
        $features = $features_model->getAll();
        $group = 'feature';
        foreach ($features as $feature) {
            $options[] = array(
                'group'       => array(
                    'title' => ifset($translates[$group]),
                    'class' => $group,
                ),
                'value'       => sprintf('features:%s', $feature['code']),
                'title'       => $feature['name'],
                'description' => $feature['code'],
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
            $res = $file->moveTo($name);
        } else {
            throw new waException(_w('Error file upload'));
        }

        $f = new shopCsvReader($name, waRequest::post('delimeter'), waRequest::post('encoding'));

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
