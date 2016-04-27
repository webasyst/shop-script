<?php

class shopVideo
{
    /**
     * Creates thumbnails of specified sizes for a product video image.
     *
     * @param numeric $product_id
     * @param array $sizes Array of image size values; e.g., '200x0', '96x96', etc.
     * @param bool $force Whether missing image thumbnail files must be created
     * @throws waException
     */
    public static function generateThumbs($product_id, $sizes = array(), $force = true)
    {
        $sizes = (array) $sizes;
        $config = wa('shop')->getConfig();
        if (!empty($sizes) && $product_id) {
            $thumbs_path = self::getThumbsPath($product_id);
            if (!file_exists($thumbs_path) && !waFiles::create($thumbs_path)) {
                throw new waException("Insufficient write permissions for the $thumbs_path dir.");
            }
            $image_path = self::getPath($product_id);
            foreach ($sizes as $size) {

                $thumb_path = self::getThumbsPath($product_id, $size);
                if ($force || !file_exists($thumb_path)) {
                    /**
                     * @var waImage
                     */
                    if ($thumb_img = shopImage::generateThumb($image_path, $size)) {
                        $thumb_img->save($thumb_path, $config->getSaveQuality());
                    }
                }
            }
            clearstatcache();
        }
    }

    /**
     * Returns path to product video directory or individual video thumbnail image file.
     *
     * @param numeric $product_id
     * @param string $size Optional size value string (e.g., '200x0', '96x96', etc.).
     *     If specified, path to corresponding thumbnail file is returned instead of path to video thumbnail.
     * @return string
     */
    public static function getThumbsPath($product_id, $size = null)
    {
        $path = shopProduct::getFolder($product_id)."/{$product_id}/";
        if (!$size) {
            return wa()->getDataPath($path, true, 'shop')."video/";
        } else {
            return wa()->getDataPath($path, true, 'shop')."video/{$size}.jpg";
        }
    }

    /**
     * Returns path to product video
     *
     * @param numeric $product_id
     * @return string
     */
    public static function getPath($product_id)
    {
        return shopProduct::getPath($product_id, "video.jpg");
    }

    /**
     * Returns URL of a product video image.
     *
     * @param numeric $product_id
     * @param string $size Size value string (e.g., '200x0', '96x96', etc.)
     * @param bool $absolute Whether absolute URL must be returned
     * @return string
     */
    public static function getThumbUrl($product_id, $size = null, $absolute = false)
    {
        if (!$size) {
            $size = wa('shop')->getConfig()->getImageSize('default');
        }
        $path = shopProduct::getFolder($product_id)."/{$product_id}/video/{$size}.jpg";

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
}