<?php
/**
 * @since 10.0.0
 */
class shopProductImagesAddManyMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $this->getProduct($product_id);

        $files = waRequest::file('files');
        if (!$files->uploaded()) {
            if (isset($_FILES['files'])) {
                throw new waAPIException('server_error', $files->error);
            } else {
                throw new waAPIException('invalid_param', sprintf_wp('Missing required parameter: “%s”.', 'files'), 400);
            }
        }

        $descriptions = waRequest::post('descriptions', [], 'array_trim');
        $this->response = [];

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $product_images_model = new shopProductImagesModel();

        foreach ($files as $file) {
            if (!$file->uploaded()) {
                continue;
            }

            $image = $file->waImage();

            $description = array_shift($descriptions);
            if (!is_scalar($description)) {
                $description = null;
            }

            $data = array(
                'product_id'        => $product_id,
                'upload_datetime'   => date('Y-m-d H:i:s'),
                'width'             => $image->width,
                'height'            => $image->height,
                'size'              => $file->size,
                'filename'          => $this->getSanitizedFilename($file),
                'original_filename' => basename($file->name),
                'ext'               => $file->extension,
                'description'       => $description,
            );
            unset($image);

            $image_id = $data['id'] = $product_images_model->add($data);
            if ($image_id) {
                $image_path = shopImage::getPath($data);
                if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                    $product_images_model->deleteById($image_id);
                    throw new waAPIException('server_error',
                        sprintf("Insufficient file permissions to write to %s",
                            substr($image_path, strlen($config->getRootPath()))
                        )
                    );
                }

                $file->moveTo($image_path);
                shopImage::generateThumbs($data, $config->getImageSizes());

                $method = new shopProductImagesGetInfoMethod();
                $_GET = ['id' => $image_id];
                $this->response[] = $method->getResponse(true);
            }

        }

    }

    protected function getSanitizedFilename($file)
    {
        if (!wa('shop')->getConfig()->getOption('image_filename')) {
            return '';
        }
        $filename = basename($file->name, '.' . $file->extension);
        if (!preg_match('//u', $filename)) {
            $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $filename);
            if ($tmp_name) {
                $filename = $tmp_name;
            }
        }
        $filename = preg_replace('/\s+/u', '_', $filename);
        if ($filename) {
            foreach (waLocale::getAll() as $l) {
                $filename = waLocale::transliterate($filename, $l);
            }
        }
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
        if (!strlen(str_replace('_', '', $filename))) {
            $filename = '';
        }
    }
}
