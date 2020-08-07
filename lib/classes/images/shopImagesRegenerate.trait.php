<?php

/**
 * Изменяет изображения
 *
 * Trait shopImagesRegenerateTrait
 */
trait shopImagesRegenerateTrait
{
    public $data = [];

    abstract public function runEvent(&$image);

    abstract public function updateFilename($image, $filename = '');

    abstract public function upCount($image);

    public function __construct()
    {
        $this->data = [
            'success'      => 0,
            'count'        => 0,
            'parent_count' => 0,
            'offset'       => 0,
            'parent_id'    => null,
            'total'        => $this->getImageCount(),
            'sizes'        => $this->getSizes(),
        ];
    }

    /**
     * Removes old and creates new previews.
     * Changes the name of images
     *
     * @return array
     * @throws waException
     */
    public function regenerate()
    {
        $use_filename = wa('shop')->getConfig()->getOption('image_filename');

        $images = $this->getImages();

        foreach ($images as $image) {
            if ($use_filename && !strlen($image['filename']) && strlen($image['original_filename'])) {
                $filename = $this->getFilename($image['original_filename']);
                if (strlen($filename)) {
                    $this->setFilename($image, $filename);
                }
            } elseif (!$use_filename && strlen($image['filename'])) {
                $this->setFilename($image);
            }

            try {
                $this->regenerateThumbs($image);
                $this->upSuccess(); //Image count - count of successful processed images
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }

            $this->upCount($image);
        }

        return $images;
    }

    /**
     * Count successful runs
     */
    protected function upSuccess()
    {
        $this->data['success'] += 1;
    }

    /**
     * @param $image
     * @throws waException
     */
    public function regenerateThumbs($image)
    {
        // Delete existing thumbnails
        $this->deleteExistingThumbs($image);

        if (waRequest::post('restore_originals')) {
            $this->restoreOriginals($image);
        }

        // Create thumbnails, if asked to
        if (waRequest::post('create_thumbnails')) {
            $with_2x = waRequest::post('with_2x');
            shopImage::generateThumbs($image, $this->data['sizes'], true, !empty($with_2x));
        }
    }

    /**
     * Regenerate original-sized image from backup, if asked to
     * @param $image_data
     * @throws waException
     */
    public function restoreOriginals($image_data)
    {
        $original_path = $this->getOriginalPath($image_data);

        if (is_readable($original_path)) {
            try {
                $new_path = $this->getPath($image_data);
                $new_original_path = $this->getOriginalPath($image_data);
                $image = waImage::factory($original_path);
                $image_changed = false;
                $event = $this->runEvent($image);
                if ($event) {
                    foreach ($event as $plugin_id => $result) {
                        $image_changed = $image_changed || $result;
                    }
                }

                if ($image_changed) {
                    if ($original_path != $new_original_path) {
                        waFiles::copy($original_path, $new_original_path);
                    }
                    $image->save($new_path);
                } else {
                    if ($original_path != $new_path) {
                        waFiles::copy($original_path, $new_path);
                    }
                    if (is_writable($new_original_path)) {
                        waFiles::delete($new_original_path);
                    }
                }
            } catch (Exception $e) {
                throw new waException('Unable to regenerate original for image '.$image_data['id'].': '.$e->getMessage());
            }
            unset($image);
        }
    }

    /**
     * @param $image
     * @throws waException
     */
    public function deleteExistingThumbs($image)
    {
        $path = $this->getThumbsPath($image);
        if (!waFiles::delete($path)) {
            throw new waException(sprintf(_w('Error when delete thumbnails for image %d'), $image['id']));
        }
    }

    #######
    # SET #
    #######
    /**
     * @param $chunk
     */
    public function setChunk($chunk)
    {
        $this->data['chunk'] = $chunk;
    }

    /**
     * @param $image
     * @param string $filename
     */
    protected function setFilename(&$image, $filename = '')
    {
        // get old image
        $old_path = $this->getPath($image);
        //save old name if move dont work
        $old_filename = $image['filename'];
        //set new name
        $image['filename'] = $filename;
        // get new image
        $new_path = $this->getPath($image);

        if (is_readable($old_path) && @waFiles::move($old_path, $new_path)) {
            $this->updateFilename($image, $filename);
        } else {
            $image['filename'] = $old_filename;
        }
    }

    #######
    # GET #
    #######
    /**
     * @param $original_filename
     * @return array|false|string|string[]|null
     */
    public function getFilename($original_filename)
    {
        $filename = basename($original_filename, '.'.waFiles::extension($original_filename));
        if (!preg_match('//u', $filename)) {
            $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $filename);
            if ($tmp_name) {
                $filename = $tmp_name;
            }
        }
        $filename = preg_replace('/\s+/u', '_', $filename);
        if ($filename) {
            try {
                foreach (waLocale::getAll() as $l) {
                    $filename = waLocale::transliterate($filename, $l);
                }
            } catch (waException $e) {

            }
        }
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
        if (!strlen(str_replace('_', '', $filename))) {
            $filename = '';
        }
        return $filename;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return mixed
     */
    public function getImageTotalCount()
    {
        return $this->data['total'];
    }

    /**
     * @return array|mixed
     * @throws waException
     */
    public function getSizes()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        return $config->getImageSizes();
    }

    /**
     * @param $image
     * @return string
     */
    public function getPath($image)
    {
        return shopImage::getPath($image);
    }

    /**
     * @param $image
     * @return string
     */
    public function getThumbsPath($image)
    {
        return shopImage::getThumbsPath($image);
    }

    /**
     * @param $image
     * @return string
     */
    public function getOriginalPath($image)
    {
        return shopImage::getOriginalPath($image);
    }

    /**
     * @param $message
     */
    public function error($message)
    {
        waLog::log($message, 'shop/images_regenerate.log');
    }
}