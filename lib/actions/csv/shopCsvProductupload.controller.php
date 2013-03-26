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
                'tax_id'           => _w('Taxable'),
                'url'              => _w('Storefront link'),
                'currency'         => _w('Currency'),
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
                    'group' => ifset($translates[$group]),
                    'value' => $id,
                    'title' => ifempty($name, $id),
                );
            }
        }

        $features_model = new shopFeatureModel();
        $features = $features_model->getAll();

        foreach ($features as $feature) {
            $options[] = array(
                'group'       => & $translates['feature'],
                'value'       => sprintf('features:%s', $feature['code']),
                'title'       => $feature['name'],
                'description' => $feature['code'],
            );
        }

        return $options;
    }

    protected function save(waRequestFile $file)
    {
        $path = wa()->getDataPath('temp/csv/upload/');
        waFiles::delete($path, true);
        waFiles::create($path);
        $file->moveTo($path, $name = $file->name);
        $f = new shopCsvReader($path.$name, waRequest::post('delimeter'), waRequest::post('encoding'));

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
            'original_name' => htmlentities(basename($file->name), ENT_QUOTES, 'utf-8'),
            'size'          => waFiles::formatSize($f->size()),
            'original_size' => waFiles::formatSize($file->size),
            'controls'      => waHtmlControl::getControl('Csvmap', 'csv_map', $params),
            'header'        => $f->header(),
        );
    }
}
