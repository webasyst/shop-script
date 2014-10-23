<?php
class shopProductImagesAction extends waViewAction
{
    /**
     * @var array
     */
    protected $images = array();

    public function execute()
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException("Unknown product");
        }
        $product = new shopProduct($id);
        $sizes = $this->getConfig()->getImageSizes('system');
        $images = $product->getImages($sizes);

        $param = waRequest::get('param', array(), waRequest::TYPE_ARRAY_INT);

        $image_id = !empty($param[0]) ? $param[0] : 0;
        if (isset($images[$image_id])) {
            $image = $images[$image_id];
            if ($image['size']) {
                $image['size'] = waFiles::formatSize($image['size'], '%0.2f', 'B,KB,MB,GB');
            }
            $this->setTemplate('ProductImage');

            $images = array_values($images);
            array_unshift($images, null);
            array_push($images, null);

            $offset = 0;
            foreach ($images as $k => $img) {
                if ($image['id'] == $img['id']) {
                    $offset = $k;
                }
            }
            $next = $images[$offset + 1];

            $image['dimensions'] = shopImage::getThumbDimensions($image, $sizes['big']);

            $this->view->assign(array(
                'image' => $image,
                'next' => $next,
                'offset' => $offset,
                'original_exists' => file_exists(shopImage::getOriginalPath($image)),
                'photostream' => waRequest::get('ps', array())
            ));
        }

        $this->view->assign(array(
            'sizes' => $sizes,
            'images' => $images,
            'count' => count($images) - 2,
            'product_id' => $id,
            'product' => $product
        ));

        /**
         * @event backend_product_edit
         * @return array[string][string]string $return[%plugin_id%]['images'] html output
         */
        $this->view->assign('backend_product_edit', wa()->event('backend_product_edit', $product));
    }
}
