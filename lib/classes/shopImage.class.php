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
     * @var waImage
     */
    protected $image;

    public function __construct($file)
    {
        $this->file = $file;
        $this->image = waImage::factory($file);
    }

    public function __destruct()
    {
        $this->image->__destruct();
    }

    public function save($file = null, $quality = null)
    {
        $config = wa('shop')->getConfig();
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
     * @return photosImage
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->image, $method)) {
            call_user_func_array(array($this->image, $method), $arguments);
        }
        return $this;
    }

    /**
     * @param array $image
     * @param array $sizes (optional)
     * generateThumbs
     * @param bool $force
     * @throws waException
     */
    public static function generateThumbs($image, $sizes = array(), $force = true)
    {
        $sizes = (array) $sizes;
        $product_id = $image['product_id'];
        $config = wa('shop')->getConfig();
        if (!empty($sizes) && !empty($image) && $product_id) {
            $thumbs_path = self::getThumbsPath($image);
            if (!file_exists($thumbs_path) && !waFiles::create($thumbs_path)) {
                throw new waException("The insufficient file write permissions for the $thumbs_path dir.");
            }
            $image_path = self::getPath($image);
            foreach ($sizes as $size) {

                $thumb_path = self::getThumbsPath($image, $size);
                if ($force || !file_exists($thumb_path)) {
                    /**
                     * @var waImage
                     */
                    if ($thumb_img = self::generateThumb($image_path, $size)) {
                        $thumb_img->save($thumb_path, $config->getSaveQuality());
                    }
                }
            }
            clearstatcache();
        }
    }

    public static function generateThumb($src_image_path, $size)
    {
        $image = waImage::factory($src_image_path);
        $width = $height = null;
        $size_info = self::parseSize($size);
        $type = $size_info['type'];
        $width = $size_info['width'];
        $height = $size_info['height'];

        switch ($type) {
            case 'max':
                $image->resize($width, $height);
                break;
            case 'crop':
                $image->resize($width, $height, waImage::INVERSE)->crop($width, $height);
                break;
            case 'width':
                $image->resize($width, $height);
                break;
            case 'height':
                $image->resize($width, $height);
                break;
            case 'rectangle':
                if ($width > $height) {
                    $w = $image->width;
                    $h = $image->width * $height / $width;
                } else {
                    $h = $image->height;
                    $w = $image->height * $width / $height;
                }                
                $image->crop($w, $h)->resize($width, $height, waImage::INVERSE);
                break;
            default:
                throw new waException("Unknown type");
                break;
        }
        return $image;
    }

    /**
     * Parsing size-code (e.g. 500x400, 500, 96x96, 200x0) into key-value array with info about this size
     *
     * @param string $size
     * @returns array
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
        } else {
            if ($width == $height) { // crop
                $type = 'crop';
            } else {
                if ($width && $height) { // rectange
                    $type = 'rectangle';
                } else
                    if (is_null($width)) {
                        $type = 'height';
                    } else
                        if (is_null($height)) {
                            $type = 'width';
                        }
            }
        }
        return array(
            'type'   => $type,
            'width'  => $width,
            'height' => $height
        );
    }

    public static function getPath($image)
    {
        return shopProduct::getPath($image['product_id'], "images/{$image['id']}.{$image['ext']}");
    }

    /**
     * TODO change
     */
    public static function getOriginalPath($image)
    {
        return shopProduct::getPath($image['product_id'], "images/{$image['id']}.original.{$image['ext']}");
    }

    public static function getThumbsPath($image, $size = null)
    {
        $path = shopProduct::getFolder($image['product_id'])."/{$image['product_id']}/";
        if (is_numeric($image)) {
            return wa()->getDataPath($path, true, 'shop').'images/'.(int) $image.'/';
        } else {
            if (!$size) {
                return wa()->getDataPath($path, true, 'shop')."images/{$image['id']}/";
            } else {
                return wa()->getDataPath($path, true, 'shop')."images/{$image['id']}/{$image['id']}.{$size}.{$image['ext']}";
            }
        }
    }

    public static function getUrl($image, $size = null, $absolute = false)
    {
        $path = shopProduct::getFolder($image['product_id'])."/{$image['product_id']}/images/{$image['id']}/{$image['id']}.{$size}.{$image['ext']}";

        if (waSystemConfig::systemOption('mod_rewrite')) {
            return wa()->getDataUrl($path, true, 'shop', $absolute);
        } else {
            if (file_exists(wa()->getDataPath($path, true, 'shop'))) {
                return wa()->getDataUrl($path, true, 'shop', $absolute);
            } else {
                $path = str_replace('products/', 'products/thumb.php/', $path);
                return wa()->getDataUrl($path, true, 'shop', $absolute);
            }
        }
    }

    /**
     * Calculate dimensions of thumbnail
     *
     * @param array $image Key-value object with image info
     * @param string $size string size-code or key-value object returned by parseSize
     * @return array Key-value object with width and height values
     */
    public static function getThumbDimensions($image, $size = null)
    {
        if (!$image['width'] && !$image['height']) {
            return null;
        }
        $size = !is_null($size) ? $size : wa('shop')->getConfig()->getImageSize('thumb');
        ;
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
