<?php

class shopImagesRegenerateReview implements shopImagesRegenerateInterface
{
    use shopImagesRegenerateTrait;

    public function upCount($image)
    {
        if ($this->data['parent_id'] != $image['review_id']) {
            $this->data['parent_id'] = $image['review_id'];
            $this->data['parent_count'] += 1;
        }
        $this->data['offset'] += 1;
        $this->data['count'] += 1;
    }

    public function updateFilename($image, $filename = '')
    {
        $this->model()->updateById($image['id'], ['filename' => $filename]);
    }

    public function getImages()
    {
        $offset = $this->data['offset'];
        $images = $this->model()->getAvailableImages($offset, $this->data['chunk']);

        return $images;
    }

    public function getImageCount()
    {
        return $this->model()->countAvailableImages();
    }

    public function getReport()
    {
        $success = _w('%d review image updated.', '%d review images updated.', $this->data['success']);
        $reviews = _w('%d review affected.', '%d reviews affected.', $this->data['parent_count']);
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
    }

    protected function model()
    {
        static $m = null;
        if (!$m) {
            $m = new shopProductReviewsImagesModel();
        }
        return $m;
    }
}

