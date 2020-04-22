<?php

/**
 * waImage for shop
 *
 * @see waImage
 *
 * @method shopImage rotate(int $degrees)
 * @method shopImage resize(int $width = null, int $height = null, string $master = null, bool $deny_exceed_original_sizes = true)
 * @method shopImage crop(int $width, int $height, $offset_x = waImage::CENTER, $offset_y = waImage::CENTER, $deny_exceed_original_sizes = true)
 * @method shopImage sharpen(int $amount)
 * @method shopImage filter(string $type)
 * @method shopImage watermark(array $options)
 * @method shopImage getExt
 *
 * @property $width
 * @property $height
 * @property $type
 * @property $ext
 */

class shopImage
{
    public $file;

    /**
     * @var waImageGd|waImageImagick
     */
    protected $image;

    /**
     * Constructor of an image object.
     *
     * @param string $file Full path to image file
     * @throws waException
     */
    public function __construct($file)
    {
        $this->file = $file;
        $this->image = waImage::factory($file);
    }

    public function __destruct()
    {
        $this->image->__destruct();
    }

    /**
     * Saves image to file.
     *
     * @param string|null $file Path to save file. If not specified, image is saved at its original path.
     * @param int|null $quality Image quality: from 1 to 100; defaults to 100.
     * @return bool Whether file was saved successfully
     * @throws waException
     */
    public function save($file = null, $quality = null)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        if ($quality === null) {
            $quality = $config->getSaveQuality();
            if (!$quality) {
                $quality = 100;
            }
        }
        // check save_original option
        if ($config->getOption('image_save_original')) {

            // get original file name
            if (($i = strrpos($this->file, '.')) !== false) {
                $original_file = substr($this->file, 0, $i).'.original'.substr($this->file, $i);
            } else {
                $original_file = $this->file.'.original';
            }
            // save original file if it not exists
            if (!file_exists($original_file)) {
                copy($this->file, $original_file);
            }
        }
        // save image
        return $this->image->save($file, $quality);
    }

    public function __get($name)
    {
        return $this->image->$name;
    }

    /**
     *
     * @param $method
     * @param $arguments
     * @return shopImage
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->image, $method)) {
            call_user_func_array(array($this->image, $method), $arguments);
        }
        return $this;
    }

    /**
     * Creates thumbnails of specified sizes for a product image.
     *
     * @param array $image Key-value image data object
     * @param array $sizes Array of image size values; e.g., '200x0', '96x96', etc.
     * @param bool $force Whether missing image thumbnail files must be created
     * @param bool $with_2x
     * @throws waException
     */
    public static function generateThumbs($image, $sizes = array(), $force = true, $with_2x = false)
    {
        $sizes = (array)$sizes;
        $product_id = $image['product_id'];
        /**
         * @var shopConfig $config
         */
        if (!empty($sizes) && !empty($image) && $product_id) {
            $thumbs_path = self::getThumbsPath($image);
            if (!file_exists($thumbs_path) && !waFiles::create($thumbs_path)) {
                throw new waException("Insufficient write permissions for the $thumbs_path dir.");
            }
            foreach ($sizes as $size) {
                self::generateThumbSize($image, $size, $force, $with_2x);
            }
            clearstatcache();
        }
    }

    private static function generateThumbSize($image, $size, $force = true, $with_2x = false)
    {
        $save_thumb = function ($force, $image_path, $thumb_path, $size, $is_2x) {
            $config = wa('shop')->getConfig();
            if ($force || !file_exists($thumb_path)) {
                /**
                 * @var waImage
                 */
                if ($thumb_img = self::generateThumb($image_path, $size)) {
                    $thumb_img->save($thumb_path, $config->getSaveQuality($is_2x));
                }
            }
        };
        $image_path = self::getPath($image);

        $thumb_path = self::getThumbsPath($image, $size);
        $save_thumb($force, $image_path, $thumb_path, $size, false);

        if ($with_2x) {
            $thumb_path = self::getThumbsPath($image, $size.'@2x');

            $size = explode('x', $size);
            foreach ($size as &$s) {
                $s *= 2;
            }
            unset($s);
            $size = implode('x', $size);

            $save_thumb($force, $image_path, $thumb_path, $size, true);
        }
    }

    /**
     * Returns image object for specified original image.
     *
     * @param string $src_image_path Path to original image
     * @param string $size Size value string of the form '200x0', '96x96', etc.
     * @param int|bool $max_size Optional maximum size limit
     * @return waImageImagick|waImageGd
     * @throws waException
     */
    public static function generateThumb($src_image_path, $size, $max_size = false)
    {
        /** @var waImageImagick|waImageGd $image */
        $image = waImage::factory($src_image_path);

        $params = array(
            'path'     => &$src_image_path,
            'image'    => &$image,
            'size'     => &$size,
            'max_size' => &$max_size,
        );
        /**
         * NOTICE: result depends on plugins order (can rearrange it at plugins screen)
         * @event image_generate_thumb
         */
        $results = wa('shop')->event('image_generate_thumb', $params);

        $skip = false;

        foreach ($results as $result) {
            if ($result === false) {
                $skip = true;
                break;
            }
        }


        if (!$skip) {
            $size_info = self::parseSize($size);
            $type = $size_info['type'];
            $width = $size_info['width'];
            $height = $size_info['height'];

            switch ($type) {
                case 'max':
                    if (is_numeric($max_size) && $width > $max_size) {
                        return null;
                    }
                    $image->resize($width, $height);
                    break;
                case 'width':
                    if (is_numeric($max_size) && ($width > $max_size || $height > $max_size)) {
                        return null;
                    }
                    $image->resize($width, $height);
                    break;
                case 'height':
                    if (is_numeric($max_size) && ($width > $max_size || $height > $max_size)) {
                        return null;
                    }
                    $image->resize($width, $height);
                    break;
                case 'crop':
                case 'rectangle':
                    if (is_numeric($max_size) && ($width > $max_size || $height > $max_size)) {
                        return null;
                    }
                    $image->resize($width, $height, waImage::INVERSE)->crop($width, $height);
                    break;
                default:
                    throw new waException("Unknown type");
                    break;
            }
        }

        $image_thumb_delay = intval(wa('shop')->getConfig()->getOption('image_thumb_delay'));
        if ($image_thumb_delay !== 0) {
            usleep($image_thumb_delay);
        }

        /**
         * Extend thumbs for product images
         * Make extra workup
         * @event image_thumb
         */
        wa('shop')->event('image_thumb', $image);

        return $image;
    }

    /**
     * Parses image size value string and returns size info array.
     *
     * @param string $size Size value string (e.g., '500x400', '500', '96x96', '200x0')
     * @returns array Size info array ('type', 'width', 'height')
     * @return array
     */
    public static function parseSize($size)
    {
        $type = 'unknown';
        $ar_size = explode('x', $size);
        $width = !empty($ar_size[0]) ? $ar_size[0] : null;
        $height = !empty($ar_size[1]) ? $ar_size[1] : null;

        if (count($ar_size) == 1) {
            $type = 'max';
            $height = $width;
        } elseif ($width == $height) { // crop
            $type = 'crop';
        } elseif ($width && $height) { // rectangle
            $type = 'rectangle';
        } elseif (is_null($width)) {
            $type = 'height';
        } elseif (is_null($height)) {
            $type = 'width';
        }
        return array(
            'type'   => $type,
            'width'  => $width,
            'height' => $height
        );
    }

    /**
     * @param array $image
     * @return string
     */
    public static function getSubPath($image)
    {
        $review_id = ifset($image,'review_id', null);

        if ($review_id) {
            $review_id = $image['review_id'];
            $sub_path = "reviews/{$review_id}/images";
        } else {
            $sub_path = 'images';
        }

        return $sub_path;
    }

    /**
     * Returns path to product image
     *
     * @param array $image Key-value image data object
     * @return string
     */
    public static function getPath($image)
    {
        if (strlen($image['filename']) && ($image['filename'] !== (string)$image['id'])) {
            $n = $image['id'].'.'.$image['filename'];
        } else {
            $n = $image['id'];
        }
        $sub_path = self::getSubPath($image);
        $path = shopProduct::getPath($image['product_id'], "{$sub_path}/{$n}.{$image['ext']}");

        return $path;
    }

    /**
     * TODO change
     *
     * Returns path to original product image
     *
     * @param array $image Key-value image data object
     * @return string
     */
    public static function getOriginalPath($image)
    {
        $sub_path = self::getSubPath($image);
        $path = shopProduct::getPath($image['product_id'], "$sub_path/{$image['id']}.original.{$image['ext']}");
        return $path;
    }

    /**
     * Returns path to product image directory or individual product image file.
     *
     * @param int|array $image Key-value image data object
     * @param string $size Optional size value string (e.g., '200x0', '96x96', etc.).
     *     If specified, path to corresponding thumbnail file is returned instead of path to image directory.
     * @return string
     */
    public static function getThumbsPath($image, $size = null)
    {
        $path = shopProduct::getFolder($image['product_id'])."/{$image['product_id']}/";
        $sub_path = self::getSubPath($image);

        if (!$size) {
            return wa()->getDataPath($path, true, 'shop', false)."{$sub_path}/{$image['id']}/";
        } else {
            if (strlen($image['filename'])) {
                $n = $image['filename'];
            } else {
                $n = $image['id'];
            }
            return wa()->getDataPath($path, true, 'shop', false)."{$sub_path}/{$image['id']}/{$n}.{$size}.{$image['ext']}";
        }
    }

    /**
     * Returns URL of a product image.
     *
     * @param array $image Key-value image data object
     * @param string $size Size value string (e.g., '200x0', '96x96', etc.)
     * @param bool $absolute Whether absolute URL must be returned
     * @return string
     */
    public static function getUrl($image, $size = null, $absolute = false)
    {
        if (!$size) {
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $size = $config->getImageSize('default');
        }
        if (isset($image['filename']) && strlen($image['filename'])) {
            $n = $image['filename'];
        } else {
            $n = $image['id'];
        }
        $sub_path = self::getSubPath($image);
        $path = shopProduct::getFolder($image['product_id'])."/{$image['product_id']}/{$sub_path}/{$image['id']}/{$n}.{$size}.{$image['ext']}";

        if (waSystemConfig::systemOption('mod_rewrite')) {
            return wa()->getDataUrl($path, true, 'shop', $absolute);
        } else {
            if (file_exists(wa()->getDataPath($path, true, 'shop', false))) {
                return wa()->getDataUrl($path, true, 'shop', $absolute);
            } else {
                $path = str_replace('products/', 'products/thumb.php/', $path);
                return wa()->getDataUrl($path, true, 'shop', $absolute);
            }
        }
    }

    /**
     * Calculates dimensions of image thumbnail.
     *
     * @param array $image Key-value image data object
     * @param string|array|null $size Size value string (e.g., '200x0', '96x96', etc.) or size data array returned by method parseSize()
     *     If empty, default value of 'thumb' size is used as defined in class shopConfig
     * @return array Array containing width and height values
     * @see shopConfig
     */
    public static function getThumbDimensions($image, $size = null)
    {
        if (!$image['width'] && !$image['height']) {
            return null;
        }
        if (is_null($size)) {
            /** @var shopConfig $config */
            $config = wa('shop')->getConfig();
            $size = $config->getImageSize('thumb');
        }

        $rate = $image['width'] / $image['height'];
        $revert_rate = $image['height'] / $image['width'];

        if (!is_array($size)) {
            $size_info = self::parseSize($size);
        } else {
            $size_info = $size;
        }
        $type = $size_info['type'];
        $width = $size_info['width'];
        $height = $size_info['height'];
        switch ($type) {
            case 'max':
                if ($image['width'] > $image['height']) {
                    $w = $width;
                    $h = $revert_rate * $w;
                } else {
                    $h = $width; // second param in case of 'max' type has size of max side, so width is now height
                    $w = $rate * $h;
                }
                break;
            case 'crop':
                $w = $h = $width; // $width == $height
                break;
            case 'rectangle':
                $w = $width;
                $h = $height;
                break;
            case 'width':
                $w = $width;
                $h = $revert_rate * $w;
                break;
            case 'height':
                $h = $height;
                $w = $rate * $h;
                break;
            default:
                $w = $h = null;
                break;
        }
        $w = round($w);
        $h = round($h);
        if ($image['width'] < $w && $image['height'] < $h) {
            return array(
                'width'  => $image['width'],
                'height' => $image['height']
            );
        }
        return array(
            'width'  => $w,
            'height' => $h
        );
    }
}
