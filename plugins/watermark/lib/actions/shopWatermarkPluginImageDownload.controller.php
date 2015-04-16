<?php
/**
 * Random product image to use as a preview in plugin settings page.
 * We use a custom controller to fetch image of original size with no modifications.
 */
class shopWatermarkPluginImageDownloadController extends waController
{
    public function execute()
    {
        $plugin = wa('shop')->getPlugin('watermark');
        $image_data = $plugin->getProductImage();
        waFiles::readFile($image_data['path']);
    }
}
