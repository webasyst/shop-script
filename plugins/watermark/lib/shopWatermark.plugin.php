<?php

class shopWatermarkPlugin extends shopPlugin
{
    public function imageUpload(waImage $image)
    {
        $settings = $this->getSettings();
        if (ifset($settings['overlay']) != 'original') {
            return;
        }
        if (ifset($settings['watermark_type']) == 'image') {
            return self::applyImageWatermark($image, $settings);
        } else {
            return self::applyTextWatermark($image, $settings);
        }
    }

    public function imageThumb(waImage $image)
    {
        $settings = $this->getSettings();
        if (ifset($settings['overlay']) == 'original') {
            return;
        }
        if (ifset($settings['thumb_min'], 0) >= $image->width) {
            return;
        }

        // For retina watermarks half the size restrictions
        if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '@2x.') !== false) {
            if (ifset($settings['thumb_min'], 0)*2 >= $image->width) {
                return;
            }
        }

        if (ifset($settings['watermark_type']) == 'image') {
            return self::applyImageWatermark($image, $settings);
        } else {
            return self::applyTextWatermark($image, $settings);
        }
    }

    public static function applyImageWatermark(waImage $image, $settings)
    {
        if (empty($settings['opacity']) || empty($settings['image'])) {
            return null;
        }

        // Calculate watermark size and position.
        $watermark = waImage::factory(wa()->getDataPath('data/'.$settings['image'], true));
        list($width, $height, $align) = self::calculateWatermarkImagePosition($watermark, $settings, $image->width, $image->height);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        // Apply watermark
        $image->watermark(array(
            'watermark' => $watermark,
            'opacity'   => $settings['opacity'],
            'height'    => $height,
            'width'     => $width,
            'align'     => $align,
        ));

        return true;
    }

    public static function applyTextWatermark(waImage $image, $settings)
    {
        if (empty($settings['opacity']) || empty($settings['text'])) {
            return null;
        }

        $size = wa('shop')->getConfig()->getImageSize('big');
        $image->watermark(array(
            'watermark'        => $settings['text'],
            'opacity'          => $settings['opacity'],
            'font_file'        => wa()->getAppPath('plugins/watermark/lib/config/data/arial.ttf', 'shop'),
            'font_size'        => $settings['font_size'] * max($image->width, $image->height) / ifempty($size, 970),
            'font_color'       => $settings['text_color'],
            'text_orientation' => self::orientation($settings['text_position'] == 'c' ? 'h' : $settings['orientation']),
            'rotation'         => -ifempty($settings['rotation'], 0),
            'align'            => self::align($settings['text_position']),
        ));

        return true;
    }

    protected static function calculateWatermarkImagePosition(waImage $watermark, $settings, $img_width, $img_height)
    {
        // The plan is to resize the watermark so it occupies the same *percentage area*
        // as the selection in plugin settings, and centered at the same percent-based point.
        $pos_x1 = $img_width * $settings['pos_x1'];
        $pos_y1 = $img_height * $settings['pos_y1'];
        $pos_x2 = $img_width * $settings['pos_x2'];
        $pos_y2 = $img_height * $settings['pos_y2'];

        $wh_ratio = $watermark->width/$watermark->height;
        $target_area = ($pos_x2 - $pos_x1)*($pos_y2 - $pos_y1);

        $width = sqrt($target_area * $wh_ratio);
        $height = $width / $wh_ratio;
        if ($width > $img_width) {
            $height *= $img_width / $width;
            $width = $img_width;
        }
        if ($height > $img_height) {
            $width *= $img_height / $height;
            $height = $img_height;
        }

        $x = ($pos_x2 + $pos_x1) / 2 - $width / 2;
        $y = ($pos_y2 + $pos_y1) / 2 - $height / 2;

        return array($width, $height, array($x, $y));
    }

    private static function align($code)
    {
        switch ($code) {
            case 'c': return waImage::ALIGN_CENTER;
            case 'tl': return waImage::ALIGN_TOP_LEFT;
            case 'tr': return waImage::ALIGN_TOP_RIGHT;
            case 'bl': return waImage::ALIGN_BOTTOM_LEFT;
            case 'br': default: return waImage::ALIGN_BOTTOM_RIGHT;
        }
    }

    private static function orientation($code)
    {
        return $code == 'v' ? waImage::ORIENTATION_VERTICAL : waImage::ORIENTATION_HORIZONTAL;
    }

    //
    // Plugin settings page
    //

    public function getControls($params = array())
    {
        $settings = $this->getSettings();
        $file_name = ifset($settings['image']);

        $view = wa()->getView();
        $view->assign(array(
            'src' => self::fileSrc($file_name),
            'plugin_id' => $this->id,
            'file_name' => $file_name,
            'settings' => $settings,
            'image' => $this->getProductImage(),
        ));

        $html = (wa()->whichUI() == '2.0' ? 'Settings' : 'Settings-legacy');
        return array(
            '' => $view->fetch($this->path.'/templates/'.$html.'.html' ),
        );
    }

    /** Product image to use for preview */
    public function getProductImage()
    {
        $image = null;

        $image_model = new shopProductImagesModel();
        $image_id = $this->getSettings('preview_image_id');
        if ($image_id) {
            $image = $image_model->getById($image_id);
            if ($image) {
                $image['path'] = shopImage::getPath($image);
                if (!is_readable($image['path'])) {
                    $image_id = null;
                    $image = null;
                }
            }
        }

        if (!$image) {
            // Fetch the most square product image available
            $sql = "SELECT * FROM `shop_product_images` ORDER BY ABS(1 - width/height), width DESC LIMIT 1";
            foreach($image_model->query($sql) as $image) {
                $image['path'] = shopImage::getPath($image);
                if (!is_readable($image['path'])) {
                    $image = null;
                }
            }
            if ($image && !$image_id) {
                $this->getSettingsModel()->set($this->getSettingsKey(), 'preview_image_id', $image_id);
            }
        }

        if (empty($image)) {
            $image = array(
                'width' => 740,
                'height' => 522,
                'path' => wa('shop')->getAppPath().'/img/promo-dummy-3.jpg',
                'url_default' => wa('shop')->getAppStaticUrl().'img/promo-dummy-3.jpg',
            );
        }
        $image['url_default'] = '?plugin=watermark&module=image&action=download&t='.time();
        return $image;
    }

    public function validateSettings($new_settings)
    {
        if (empty($new_settings['watermark_type'])) {
            $new_settings['watermark_type'] = 'text';
        } else {
            $new_settings['watermark_type'] = 'image';
        }
        if (!empty($new_settings['image'])) {
            if (!$new_settings['image'] instanceof waRequestFile) {
                unset($new_settings['image']);
            } else if (!in_array($new_settings['image']->error_code, array(UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK))) {
                throw new waException($new_settings['image']->error);
            }
        }
        foreach (array('pos_x1', 'pos_y1', 'pos_x2', 'pos_y2') as $x) {
            if (isset($new_settings[$x])) {
                $new_settings[$x] = str_replace(',', '.', $new_settings[$x]);
            }
        }
        return $new_settings;
    }

    public function saveSettings($settings = array())
    {
        $settings = $this->validateSettings($settings);

        if ($settings['watermark_type'] == 'text') {
            $settings['image'] = '';
            unset($settings['delete_image']);
        } elseif (isset($settings['image']) && ($settings['image'] instanceof waRequestFile)) {
            /**
             * @var waRequestFile $file
             */
            $file = $settings['image'];
            if ($file->uploaded()) {
                // check that file is image
                try {
                    // create waImage
                    $image = $file->waImage();
                } catch (Exception $e) {
                    throw new Exception(_w("File isn't an image"));
                }
                $path = wa()->getDataPath('data/', true);
                $file_name = 'watermark.'.$image->getExt();
                if (!file_exists($path) || !is_writable($path)) {
                    $message = _wp(
                        'File could not be saved due to the insufficient file write permissions for the %s folder.'
                    );
                    throw new waException(sprintf($message, 'wa-data/public/shop/data/'));
                } elseif (!$file->moveTo($path, $file_name)) {
                    throw new waException(_wp('Failed to upload file.'));
                }
                $settings['image'] = $file_name;
            } else {
                $image = $this->getSettings('image');
                $settings['image'] = $image;
            }
        }

        foreach ($settings as $name => $value) {
            $this->settings[$name] = $value;
            $this->getSettingsModel()->set($this->getSettingsKey(), $name, is_array($value) ? json_encode($value) : $value);
        }

        return array(
            'src' => self::fileSrc($this->settings['image']),
        );
    }

    protected function getSettingsConfig()
    {
        return array(
            'watermark_type' => 'text',
            'text_color' => 'ffffff',
            'text' => '',
            'image' => '',
            'opacity' => 0.3,
            'rotation' => 0,
            'orientation' => 'h',
            'text_position' => 'br',
            'font_size' => '12',
            'overlay' => 'thumbnails',
            'thumb_min' => 200,
            'pos_x1' => 0.7,
            'pos_y1' => 0.7,
            'pos_x2' => 0.98,
            'pos_y2' => 0.98,
        );
    }

    private static function fileSrc($file_name)
    {
        $src = '';
        if ($file_name) {
            $file_path = wa()->getDataPath('data/', true, 'shop').$file_name;
            if (file_exists($file_path)) {
                $src = wa()->getDataUrl('data/', true, 'shop', true).$file_name.'?'.filemtime($file_path);
            }
        }
        return $src;
    }
}
