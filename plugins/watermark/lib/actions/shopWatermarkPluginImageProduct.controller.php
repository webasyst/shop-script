<?php
/** Select a new random product image to use as a preview on plugin settings page. */
class shopWatermarkPluginImageProductController extends waJsonController
{
    public function execute()
    {
        $plugin = wa('shop')->getPlugin('watermark');

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->del(array('shop', 'watermark'), 'preview_image_id');

        // Get random product image
        $sql = "SELECT * FROM shop_product_images ORDER BY RAND() LIMIT 10";
        foreach(wao(new waModel())->query($sql) as $image) {
            $path = shopImage::getPath($image);
            if (is_readable($path)) {
                $app_settings_model->set(array('shop', 'watermark'), 'preview_image_id', $image['id']);
                break;
            }
        }

        $this->response = $plugin->getProductImage();
    }
}

