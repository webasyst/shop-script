<?php
/** Watermark preview: apply watermark to a product image and show the result. */
class shopWatermarkPluginImagePreviewController extends waController
{
    public function execute()
    {
        $plugin = wa('shop')->getPlugin('watermark');
        $settings = $plugin->getSettings();
        $image_data = $plugin->getProductImage();
        $image = waImage::factory($image_data['path']);
        $image->resize(wa('shop')->getConfig()->getImageSize('big'));

        if (ifset($settings['watermark_type']) == 'image') {
            $plugin->applyImageWatermark($image, $settings);
        } else {
            $plugin->applyTextWatermark($image, $settings);
        }

        $tempnam = tempnam(wa()->getTempPath('shop_watermark'), 'tmp');
        if (!$tempnam) {
            throw new waException('Unable to create temporary file');
        }
        $filename = $tempnam.'.jpg';
        $image->save($filename, wa('shop')->getConfig()->getSaveQuality());
        waFiles::readFile($filename, null, false);
        try {
            waFiles::delete($filename);
        } catch (Exception $e) {}
        try {
            waFiles::delete($tempnam);
        } catch (Exception $e) {}
        exit;
    }
}

