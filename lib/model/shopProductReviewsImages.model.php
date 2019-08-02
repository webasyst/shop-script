<?php

class shopProductReviewsImagesModel extends waModel
{
    protected $table = 'shop_product_reviews_images';

    /**
     * @param string $file_path
     * @param array $options
     *
     * @return bool|array
     * @throws waException
     */
    public function addImage($file_path, $options = [])
    {
        if (!is_string($file_path) || empty($options['review_id']) || empty($options['product_id'])) {
            return false;
        }

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $image = waImage::factory($file_path);
        $review_id = $options['review_id'];
        $product_id = $options['product_id'];
        $description = ifset($options, 'description', null);
        $filename = $original_filename = ifset($options, 'filename', '');

        $image_changed = $this->eventReview($image, $options);
        $filename = $this->getFilename($filename);
        $data = array(
            'review_id'         => $review_id,
            'product_id'        => $product_id,
            'upload_datetime'   => date('Y-m-d H:i:s'),
            'width'             => $image->width,
            'height'            => $image->height,
            'size'              => filesize($image->file),
            'filename'          => basename($filename, '.'.waFiles::extension($filename)),
            'description'       => $description,
            'original_filename' => pathinfo($original_filename, PATHINFO_BASENAME),
            'ext'               => pathinfo($original_filename, PATHINFO_EXTENSION),
        );

        $data['id'] = $this->add($data);

        if (empty($data['id'])) {
            throw new waException("Database error");
        }

        $path = $this->getImagePath($data);

        if (!$this->createFile($path)) {
            $this->dropReview($path, $data['id']);
        }

        $target_path = null;

        if ($image_changed) {
            $image->save($path);
            // save original
            $original_file = $this->getOriginalPath($data);
            if ($config->getOption('image_save_original') && $original_file) {
                $target_path = $original_file;
            }
        } else {
            $target_path = $path;
        }

        if ($target_path) {
            $image->save($path);
        }

        return $data;
    }

    /**
     * @param array $data
     * @return bool|int|resource
     * @throws waException
     */
    public function add($data)
    {
        if (!isset($data['review_id'])) {
            return false;
        }
        $data['sort'] = $this->getSort($data['review_id']);
        $image_id = $this->insert($data);

        return $image_id;
    }

    /**
     * @param $path
     * @return bool|string
     */
    protected function createFile($path)
    {
        $result = false;
        if (!file_exists($path)) {
            $result = waFiles::create($path);
        } elseif (file_exists($path) && is_writable($path)) {
            $result = true;
        }
        return $result;
    }


    /**
     * @param string $path
     * @param $id
     * @throws waException
     */
    protected function dropReview($path, $id)
    {
        $config = wa('shop')->getConfig();

        $this->deleteById((int)$id);
        throw new waException(
            sprintf(
                "The insufficient file write permissions for the %s folder.",
                substr($path, strlen($config->getRootPath()))
            )
        );
    }

    /**
     * @param $image
     * @param $options
     * @return bool|int
     */
    protected function eventReview(&$image, $options)
    {
        $result = false;

        /**
         * Extend upload process
         * Make extra workup
         * @event image_upload
         * @params waImage $image
         * @return bool $result Is image changed
         */
        $event_params = [
            'image'   => &$image,
            'options' => $options,
        ];

        $event = wa('shop')->event('review_image_upload', $event_params);

        if ($event) {
            $result = count(array_filter($event));
        }

        return $result;
    }

    /**
     * @param $filename
     * @return array|false|string|string[]|null
     * @throws waException
     */
    public function getFilename($filename)
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        if ($config->getOption('image_filename')) {
            // If the name is not in unicode, then convert to unicode
            if (!preg_match('//u', $filename)) {
                $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $filename);
                if ($tmp_name) {
                    $filename = $tmp_name;
                }
            }
            // Replace spaces with underscores
            $filename = preg_replace('/\s+/u', '_', $filename);

            // Try to transliterate strings
            if ($filename) {
                foreach (waLocale::getAll() as $l) {
                    $filename = waLocale::transliterate($filename, $l);
                }
            }
            // Remove all that is not English
            $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
            // Check that there is something other than spaces
            if (!strlen(str_replace('_', '', $filename))) {
                $filename = '';
            }
        } else {
            $filename = '';
        }

        return $filename;
    }

    /**
     * @param array|string|int $reviews_id
     * @param array $sizes
     * @param string $key
     * @param bool $absolute
     * @return array
     * @throws waException
     */
    public function getImages($reviews_id = 0, $sizes = [], $key = 'id', $absolute = false)
    {
        $sizes = $this->prepareSizes($sizes);

        if ($key != 'review_id') {
            $key = 'id';
        }

        $raw_images = $this->getRawImagesData($reviews_id);
        $images = [];

        foreach ($raw_images as $image) {
            if (!empty($sizes)) {
                foreach ($sizes as $name => $size) {
                    $image['url_'.$name] = $this->getImageUrl($image, $size, $absolute);
                }
            }
            if ($key == 'id') {
                $images[$image['id']] = $image;
            } else {
                $images[$image['review_id']][$image['id']] = $image;
            }
        }
        return $images;
    }

    /**
     * @param array $image
     * @param string $size
     * @param bool $absolute
     * @return string
     */
    protected function getImageUrl($image, $size, $absolute)
    {
        return shopImage::getUrl($image, $size, $absolute);
    }

    /**
     * @param string|int $reviews_id
     * @return array
     * @throws waException
     */
    public function getRawImagesData($reviews_id)
    {
        $where = $this->getWhereByField('review_id', $reviews_id);
        $images = $this->select('*')->where($where)->order('product_id, sort')->fetchAll();

        return $images;
    }

    /**
     * If the size is not an array, then you need to find a suitable value for it and return the array
     * @param $sizes
     * @return array
     */
    public function prepareSizes($sizes = null)
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        if (!$sizes) {
            // If nothing is transferred use cropped image
            $sizes = array('crop' => $config->getImageSize('crop'));
        } elseif (is_numeric($sizes)) {
            // The number is a custom value that will be square.
            $sizes = array($sizes => $sizes);
        } elseif (is_string($sizes)) {
            // String is the default size name.
            $sizes = array((string)$sizes => $config->getImageSize((string)$sizes));
            foreach ($sizes as $k => $s) {
                if ($s === null) {
                    $sizes[$k] = $k;
                }
            }
        }

        return $sizes;
    }

    /**
     * @param int $review_id
     * @return int
     * @throws waException
     */
    public function getSort($review_id)
    {
        $review_id = (int)$review_id;
        $info = $this->select('MAX(`sort`)+1 AS `max`, COUNT(1) AS `cnt`')->where($this->getWhereByField('review_id', $review_id))->fetch();
        $sort = $info['cnt'] ? $info['max'] : 0;
        return $sort;
    }

    /**
     * @return bool|mixed
     */
    public function countAvailableImages()
    {
        $result = 0;
        if (wa()->getUser()->getRights('shop', 'settings')) {
            $result = $this->countAll();
        }
        return $result;
    }

    /**
     * @param int $offset
     * @param null $limit
     * @return array
     */
    public function getAvailableImages($offset = 0, $limit = null)
    {
        if (!$limit) {
            $limit = (int)$offset;
            $offset = 0;
        } else {
            $offset = (int)$offset;
            $limit = (int)$limit;
        }
        $result = [];

        if (wa()->getUser()->getRights('shop', 'settings')) {
            $result = $this->select('*')->order('product_id, id')->limit("{$offset}, {$limit}")->fetchAll('id');
        }
        return $result;
    }

    /**
     * @param $image_id
     * @return bool
     * @throws Exception
     */
    public function remove($image_id)
    {
        $result = false;
        if (!$image_id) {
            return $result;
        }

        $image = $this->getById($image_id);

        if ($image) {
            /**
             * Delete image event
             * @param array $image
             *
             * @event review_images_delete
             */

            wa('shop')->event('review_images_delete', $image);
            $remove_files = $this->removeFiles($image);

            if ($remove_files && $this->deleteById($image_id)) {
                $this->updateImageCount($image['review_id']);
                $result = true;
            }
        }

        return $result;
    }

    protected function updateImageCount($review_id)
    {
        $review_model = new shopProductReviewsModel();

        $review = $review_model->getById($review_id);
        if ($review) {
            $images_count = ifset($review, 'images_count', 0) - 1;
            $images_count = max(0, $images_count);

            $review_model->updateById($review['id'], ['images_count' => $images_count]);
        }
    }

    /**
     * @param $image
     * @return bool
     * @throws Exception
     */
    protected function removeFiles($image)
    {
        $result = false;

        try {
            // first of all try delete files from disk
            waFiles::delete($this->getThumbsPath($image));
            waFiles::delete($this->getImagePath($image));
            waFiles::delete($this->getOriginalPath($image));
            $result = true;
        } catch (waException $e) {
            waLog::log(waLog::log($e->getMessage()), 'shop/review_images_delete.log');
        }

        return $result;
    }

    /**
     * Isolating the static method shopImage ::getImagePath
     * @param $data
     * @return string
     */
    protected function getImagePath($data)
    {
        return shopImage::getPath($data);
    }

    /**
     * Isolating the static method shopImage::getOriginalPath
     * @param $image
     * @return string
     */
    protected function getOriginalPath($image)
    {
        return shopImage::getOriginalPath($image);
    }

    /**
     * Isolating the static method shopImage::getThumbsPath
     * @param $image
     * @return string
     */
    protected function getThumbsPath($image)
    {
        return shopImage::getThumbsPath($image);
    }


}