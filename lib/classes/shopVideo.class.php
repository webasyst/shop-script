<?php

class shopVideo
{
    /**
     * Creates thumbnails of specified sizes for a product video image.
     *
     * @param int $product_id
     * @param array $sizes Array of image size values; e.g., '200x0', '96x96', etc.
     * @param bool $force Whether missing image thumbnail files must be created
     * @throws waException
     */
    public static function generateThumbs($product_id, $sizes = array(), $force = true)
    {
        $sizes = (array)$sizes;

        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();
        if (empty($sizes)) {
            $sizes['crop'] = $config->getImageSize('crop');
        }


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
     * @param int $product_id
     * @param string $size Optional size value string (e.g., '200x0', '96x96', etc.).
     *     If specified, path to corresponding thumbnail file is returned instead of path to video thumbnail.
     * @return string
     */
    public static function getThumbsPath($product_id, $size = null)
    {
        $path = shopProduct::getFolder($product_id)."/{$product_id}/";
        if (!$size) {
            return wa()->getDataPath($path, true, 'shop', false)."video/";
        } else {
            return wa()->getDataPath($path, true, 'shop', false)."video/{$size}.jpg";
        }
    }

    /**
     * Returns path to product video
     *
     * @param int $product_id
     * @return string
     */
    public static function getPath($product_id)
    {
        return shopProduct::getPath($product_id, "video.jpg");
    }

    /**
     * Returns URL of a product video image.
     *
     * @param int $product_id
     * @param string $size Size value string (e.g., '200x0', '96x96', etc.)
     * @param bool $absolute Whether absolute URL must be returned
     * @return string
     */
    public static function getThumbUrl($product_id, $size = null, $absolute = false)
    {
        if (!$size) {
            /**
             * @var shopConfig $config
             */
            $config = wa('shop')->getConfig();
            $size = $config->getImageSize('default');
        }
        $path = shopProduct::getFolder($product_id)."/{$product_id}/video/{$size}.jpg";

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

    public static function checkVideo($url, &$site = null, &$id = null)
    {
        if (preg_match('~^(?:https?://)?(?:www.)?(vk\.com/).*(?:video|clips?)([a-z0-9\-]+_[a-z0-9]+)~i', $url, $m)) {
            $site = 'vk.com/video';
            $id = $m[2];
            $result = 'http://'.$site.$id;
        } else if (preg_match('!^(?:https?://)?(?:www.)?(youtube\.com|youtu\.be|vimeo\.com|rutube\.ru\/(?:video|shorts))/(?:watch\?v=|shorts/)?([a-z0-9\-_]+)!i', $url, $m)) {
            $site = strtolower($m[1]);
            $id = $m[2];
            if ($site == 'youtube.com') {
                $site = 'youtu.be';
            }
            $id = $m[2];
            $result = 'http://'.$site.'/'.$id;
        } else {
            return null;
        }

        if (($site == 'youtu.be' || $site == 'vk.com/video') && preg_match('/(\?|&)t=([0-9hms]+)/i', $url, $match)) {
            $result .= '?t='.$match[2];
        }
        return $result;
    }

    /**
     * @param int $product_id
     * @param string $video_url
     * @return bool
     */
    public static function checkVideoThumb($product_id, $video_url)
    {
        $file_path = self::getPath($product_id);
        if (file_exists($file_path)) {
            return $file_path;
        } elseif ( ( $video_url = self::checkVideo($video_url, $site, $id))) {
            $file_url = null;
            try {
                if (!waFiles::create($file_path)) {
                    return false;
                }
                if ($site == 'youtube.com' || $site == 'youtu.be') {
                    $file_url = 'http://img.youtube.com/vi/'.$id.'/0.jpg';
                } elseif ($site == 'vimeo.com') {
                    $n = new waNet(array('format' => waNet::FORMAT_JSON));
                    $desc = $n->query('http://vimeo.com/api/v2/video/'.$id.'.json');
                    if ($desc && !empty($desc[0]['thumbnail_large'])) {
                        $file_url = $desc[0]['thumbnail_large'];
                    }
                } else if ($site == 'vk.com/video') {
                    if (preg_match('~video([^&\?_]+)_([^&\?_]+)~', $video_url, $match)) {
                        $html = (new waNet())->query("https://vk.com/video_ext.php?oid={$match[1]}&id={$match[2]}&hd=1");
                        if (preg_match('~background-image:\s*url\s*\(([^)]+)\)~', $html, $match)) {
                            $file_url = trim($match[1]);
                        }
                    }
                } else {
                    $n = new waNet(array('format' => waNet::FORMAT_JSON));
                    $desc = $n->query('http://rutube.ru/api/video/'.$id);
                    if ($desc && !empty($desc['thumbnail_url'])) {
                        $file_url = $desc['thumbnail_url'];
                    }
                }

                if ($file_url) {
                    waFiles::upload($file_url, $file_path);
                    return $file_path;
                }
            } catch (waException $ex) {

            }
        }
        return false;
    }
}
