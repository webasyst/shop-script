<?php

class shopPromoBannerHelper
{
    public static function getColorById($promo_id)
    {
        $colors = ['#FF9999', '#D4FF7F', '#8CFFD9', '#A6A6FF', '#FF99DD', '#FFDD99', '#99FF99', '#99DDFF', '#DD99FF'];
        $hash = md5(wa()->getUser()->getId().$promo_id);
        $id = (string)preg_replace("~[^0-9]~", '', $hash);
        $id = (int)$id[0];

        return ifset($colors, $id, $colors[0]);
    }

    public static function generateImageName()
    {
        $uniq_name = uniqid('promo_', true);
        return str_replace('.', '', $uniq_name);
    }

    public static function getFilenameRegexp($filename)
    {
        $file_info = pathinfo($filename);
        return "~^({$file_info['filename']}\.)(.*)?({$file_info['extension']})$~ui";
    }

    /**
     * Image for banner can be in directories:
     * 1. /wa-data/public/shop/promos/1.jpg
     * 2. /wa-data/public/shop/promos/01/00/1/promo_5ddd20618dc48316776691.jpg
     *
     * This is necessary for backward compatibility with old promo cards.
     * Depends on the file name. If the file starts with a number, it is in a flat directory (first option).
     * Otherwise, he must be in a special catalog for his promo.
     *
     * @see shopHelper::getFolderById()
     *
     * @param int $promo_id
     * @param string $filename
     * @return string
     */
    public static function getPromoBannerFolder($promo_id, $filename)
    {
        // Once upon a time, a promo had only one image. Her name always coincided with the promo id (e.g. 1.png).
        // At that time, all images were stored in a flat catalog.
        $promo_banner_folder = '';
        if (!preg_match('~^[\d]~ui', $filename)) {
            // But now it’s become possible to upload several banners for one promo. Therefore, the structure of storing images on the server has changed.
            $promo_banner_folder = shopHelper::getFolderById($promo_id);
        }

        return $promo_banner_folder;
    }

    public static function getPromoBannerPath($promo_id, $filename)
    {
        $banner_folder = self::getPromoBannerFolder($promo_id, $filename);
        return wa('shop')->getDataPath('promos/'.$banner_folder.$filename, true);
    }

    public static function getPromoBannerUrl($promo_id, $filename, $size = null)
    {
        $file_info = pathinfo($filename);
        $banner_folder = self::getPromoBannerFolder($promo_id, $filename);
        $v = @filemtime(wa('shop')->getDataPath('promos/'.$banner_folder.$file_info['filename'].'.'.$file_info['extension'], true));

        if ($params = array_filter(compact('v'))) {
            $params = '?'.http_build_query($params);
        } else {
            $params = '';
        }

        if ($size) {
            $name = sprintf('%s.%s.%s', $file_info['filename'], $size, $file_info['extension']);
        } else {
            $name = sprintf('%s.%s', $file_info['filename'], $file_info['extension']);
        }

        return wa('shop')->getDataUrl('promos/'.$banner_folder.$name, true).$params;
    }
}