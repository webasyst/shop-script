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

    protected function updateFilename($image, $filename = '')
    {
        $model = new shopProductImagesModel();
        $model->updateById($image['id'], array('filename' => $filename));
        $this->updateFilenameInProduct($image, $filename);
    }

    protected function updateFilenameInProduct($image, $filename = '')
    {
        if (!$image['sort']) {
            (new shopProductModel())->updateById($image['product_id'], array(
                'image_filename' => $filename,
            ));
        }
    }

    public function getImages()
    {
        $offset = $this->data['offset'];
        $images = (new shopProductImagesModel())->getAvailableImages($offset, $this->data['chunk']);

        return $images;
    }

    public function getImageCount()
    {
        $count = (new shopProductImagesModel())->countAvailableImages();
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

}

