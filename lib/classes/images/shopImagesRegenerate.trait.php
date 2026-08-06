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

    abstract public function saveThumbExt($image);

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
                $this->error((string)$e);
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

    protected function changeExt(&$image, $new_ext, &$do_restore_originals)
    {
        // Получаем путь до исходника (не оригинала) картинки, как есть.
        $source_image = $this->getPath($image);
        $original_image = $this->getOriginalPath($image);

        // If we can restore from original photo, then siply do that (better quality).
        $do_restore_originals = file_exists($original_image);

        // Change ext in database (will affect thumbnail format)
        $prev_ext = $image['ext'];
        $image['original_ext'] = ifempty($image, 'original_ext', $image['ext']);
        $image['ext'] = $new_ext;

        // If unable to restore from original, convert from product image.
        if (!$do_restore_originals) {

            if (!file_exists($source_image)) {
                waLog::log([
                    'Unable to change image extension: no source image and no original',
                    'source' => $source_image,
                    'original_image' => $original_image,
                    'image' => $image,
                ], 'shop/images_regenerate.log');
                return;
            }

            $new_path = $this->getPath($image);
            try {
                $quality = wa('shop')->getConfig()->getSaveQuality();
                waImage::factory($source_image)->save($new_path, $quality);
            } catch (Throwable $e) {
                waLog::log([
                    'Unable to change image extension',
                    'source' => $source_image,
                    'destination' => $new_path,
                    'quality' => $quality,
                    'image' => $image,
                    (string) $e,
                ], 'shop/images_regenerate.log');
            }
            if (file_exists($new_path) && file_exists($source_image)) {
                if (wa('shop')->getConfig()->getOption('image_save_original')) {
                    $image['original_ext'] = $prev_ext;
                    waFiles::move($source_image, $this->getOriginalPath($image));
                } else {
                    waFiles::delete($source_image);
                }
                $this->saveThumbExt($image);
            } else {
                // in case something went wrong converting image format,
                // we generate thumbs in old format
                $image['ext'] = $prev_ext;

                waLog::log([
                    'Revert image ext change',
                    'source' => $source_image,
                    'image' => $image,
                ], 'shop/images_regenerate.log');
            }
        } else {
            $this->saveThumbExt($image);
            if (file_exists($source_image)) {
                waFiles::delete($source_image);
            }
        }
    }

    /**
     * @param $image
     * @throws waException
     */
    public function regenerateThumbs($image)
    {
        // Delete existing thumbnails
        $this->deleteExistingThumbs($image);

        $do_restore_originals = waRequest::post('restore_originals');

        $thumbnail_format = wa('shop')->getConfig()->getOption('image_thumbnail_format');
        if ($thumbnail_format && $image['ext'] != $thumbnail_format) {
            $this->changeExt($image, $thumbnail_format, $do_restore_originals);
        } else if (!$thumbnail_format && $image['ext'] != $image['original_ext']) {
            $original_image = $this->getOriginalPath($image);
            if (file_exists($original_image)) {
                $this->changeExt($image, $image['original_ext'], $do_restore_originals);
            }
        }

        if ($do_restore_originals) {
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
                $image = waImage::factory($original_path);
                $image_changed = false;
                $event = $this->runEvent($image);
                if ($event) {
                    foreach ($event as $plugin_id => $result) {
                        $image_changed = $image_changed || $result;
                    }
                }

                if ($image_changed) {
                    $image->save($new_path);
                } else {
                    if ($original_path != $new_path) {
                        waFiles::copy($original_path, $new_path);
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