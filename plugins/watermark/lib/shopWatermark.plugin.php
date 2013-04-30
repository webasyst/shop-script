<?php

class shopWatermarkPlugin extends shopPlugin
{
    public function imageUpload(waImage $image)
    {
        $settings = $this->getSettings();

        $opacity = $settings['opacity'];
        $result = null;
        if ($opacity && !empty($settings['text'])) {
            $font_path = realpath(dirname(__FILE__) . '/config/data/arial.ttf');

            $config = wa('shop')->getConfig();
            $size = $config->getImageSize('big');
            if (!$size) {
                return false;
            }

            $image->watermark(array(
                'watermark' => $settings['text'],
                'opacity' => $opacity,
                'font_file' => $font_path,
                'font_size' => $settings['text_size'] * max($image->width, $image->height) / $size,
                'font_color' => $settings['text_color'],
                'text_orientation' => $this->_orientation($settings['text_orientation']),
                'align' => $this->_align($settings['text_align']),
            ));
            $result = true;
        }
        if ($opacity && !empty($settings['image'])) {
            $watermark_path = wa()->getDataPath('data/', true) . $settings['image'];
            $watermark = waImage::factory($watermark_path);
            $image->watermark(array(
                'watermark' => $watermark,
                'opacity' => $opacity,
                'align' => $this->_align($settings['image_align']),
            ));
            $result = true;
        }
        return $result;
    }

    public function getPath()
    {
        return $this->path;
    }

    private function _align($code) {
        $align = waImage::ALIGN_TOP_LEFT;
        switch ($code) {
            case 'tl': $align = waImage::ALIGN_TOP_LEFT; break;
            case 'tr': $align = waImage::ALIGN_TOP_RIGHT; break;
            case 'bl': $align = waImage::ALIGN_BOTTOM_LEFT; break;
            case 'br': $align = waImage::ALIGN_BOTTOM_RIGHT; break;
        }
        return $align;
    }

    private function _orientation($code) {
        return $code == 'v' ? waImage::ORIENTATION_VERTICAL : waImage::ORIENTATION_HORIZONTAL;
    }

    public function validateSettings($new_settings)
    {
        $settings = $this->getSettings();
        if (!empty($new_settings['image']) && $new_settings['image']->error_code != UPLOAD_ERR_NO_FILE && $new_settings['image']->error_code != UPLOAD_ERR_OK) {
            throw new waException($new_settings['image']->error);
        }
        return $new_settings;
    }

    public function saveSettings($settings = array())
    {
        $settings = $this->validateSettings($settings);

        if (isset($settings['delete_image']) && $settings['delete_image']) {
            $settings['image'] = '';
            unset($settings['delete_image']);
        } else if (isset($settings['image']) && $settings['image'] instanceof waRequestFile) {
            /**
             * @var waRequestFile $file
             */
            $file = $settings['image'];
            if ($file->uploaded()) {
                // check that file is image
                try {
                    // create waImage
                    $file->waImage();
                } catch(Exception $e) {
                    throw new Exception(_w("File isn't an image"));
                }
                $path = wa()->getDataPath('data/', true);
                $file_name = 'watermark.'.$file->extension;
                if (!file_exists($path) || !is_writable($path)) {
                    throw new waException(sprintf(_wp('File could not be saved due to the insufficient file write permissions for the %s folder.'),
                                'wa-data/public/shop/data/'));
                } elseif (!$file->moveTo($path, $file_name)) {
                    throw new waException(_wp('Failed to upload file.'));
                }
                $settings['image'] = $file_name;
            } else {
                $image = $this->getSettings('image');
                $settings['image'] = $image;
            }
        }

        parent::saveSettings($settings);

        return array(
            'filesrc' => self::fileSrc($this->getSettings('image'))
        );

    }

    static private function fileSrc($file_name)
    {
        $src = '';
        if ($file_name) {
            $file_path = wa()->getDataPath('data/', true) . $file_name;
            if (file_exists($file_path)) {
                $src = wa()->getDataUrl('data/', true, 'shop', true) . $file_name . '?' . filemtime($file_path);
            }
        }
        return $src;
    }

    static public function getFileControl($name, $params)
    {
        $plugin = wa()->getPlugin('watermark');
        $view = wa()->getView();

        $file_name = $plugin->getSettings('image');

        $view->assign('plugin_id', $plugin->id);
        $view->assign('src', self::fileSrc($file_name));
        $view->assign('file_name', $file_name);

        return $view->fetch($plugin->getPath() . '/templates/SettingsFileControl.html');
    }
}