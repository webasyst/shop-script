<?php

class shopImagesRegenerateProduct implements shopImagesRegenerateInterface
{
    use shopImagesRegenerateTrait;

    public function upCount($image)
    {
        if ($this->data['parent_id'] != $image['product_id']) {
            $this->data['parent_id'] = $image['product_id'];
            $this->data['parent_count'] += 1;
        }
        $this->data['offset'] += 1;
        $this->data['count'] += 1;
    }

    public function updateFilename($image, $filename = '')
    {
        $this->model()->updateById($image['id'], ['filename' => $filename]);
        $this->updateFilenameInProduct($image, $filename);
    }

    protected function updateFilenameInProduct($image, $filename = '')
    {
        if (!$image['sort']) {
            $this->productModel()->updateById($image['product_id'], array(
                'image_filename' => $filename,
            ));
        }
    }

    public function getImages()
    {
        $offset = $this->data['offset'];
        $images = $this->model()->getAvailableImages($offset, $this->data['chunk']);

        return $images;
    }

    public function getImageCount()
    {
        $count = $this->model()->countAvailableImages();
        return $count;
    }

    public function getReport()
    {
        $success = _w('Updated %d product image.', 'Updated %d product images.', $this->data['success']);
        $reviews = _w('%d product affected.', '%d products affected.', $this->data['parent_count']);
        $report = <<<HTML
$success
$reviews
HTML;
        return $report;

    }

    public function runEvent(&$image)
    {
        return wa('shop')->event('image_upload', $image);
    }

    public function saveThumbExt($image)
    {
        $this->model()->updateById($image['id'], [
            'original_ext' => ifempty($image, 'original_ext', $image['ext']),
            'ext' => $image['ext'],
        ]);

        $this->productModel()->updateByField([
            'id' => $image['product_id'],
            'image_id' => $image['id'],
         ], array(
            'ext' => $image['ext'],
        ));
    }

    protected function model()
    {
        static $m = null;
        if (!$m) {
            $m = new shopProductImagesModel();
        }
        return $m;
    }

    protected function productModel()
    {
        static $m = null;
        if (!$m) {
            $m = new shopProductModel();
        }
        return $m;
    }
}
