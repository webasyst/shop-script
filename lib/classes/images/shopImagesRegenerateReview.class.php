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

    protected function updateFilename($image, $filename = '')
    {
        $model = new shopProductReviewsImagesModel();
        $model->updateById($image['id'], array('filename' => $filename));
    }

    public function getImages()
    {
        $offset = $this->data['offset'];
        $images = (new shopProductReviewsImagesModel())->getAvailableImages($offset, $this->data['chunk']);

        return $images;
    }

    public function getImageCount()
    {
        $count = (new shopProductReviewsImagesModel())->countAvailableImages();
        return $count;
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

}

