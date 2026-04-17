<?php

class shopProdAiGenerateImageController extends waJsonController
{
    public function execute()
    {
        $image_id = waRequest::post('image_id', null, waRequest::TYPE_INT);
        $image_style = waRequest::post('image_style', 'auto', waRequest::TYPE_STRING_TRIM);
        $image_prompt = waRequest::post('image_prompt', '', waRequest::TYPE_STRING_TRIM);
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);

        $product_images_model = new shopProductImagesModel();
        if ($image_id) {
            $image_info = $product_images_model->getById($image_id);
            if (!$image_info) {
                throw new waException(_w('Product image not found in the database.'), 404);
            }

            $product_id = (int) $image_info['product_id'];
        }

        if (!isset(shopHelper::getAllowedProdAiImageStyles()[$image_style])) {
            throw new waException(_w('Invalid request data.'), 400);
        }

        if ($product_id) {
            $product_model = new shopProductModel();
            $product = $product_model->getById($product_id);
        }
        if (empty($product)) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }

        if (!$product_model->checkRights($product)) {
            throw new waException(_w('Access denied'), 403);
        }

        $ai_facility = 'store_product_image_create';
        if (!empty($image_info)) {
            $ai_facility = 'store_product_image_improve';
            $source_image_path = shopImage::getPath($image_info);
            if (!is_readable($source_image_path)) {
                throw new waException(_w('Product image file not found.'), 404);
            }
            $image_data_url = 'data:' . waFiles::getMimeType('.' . $image_info['ext']) . ';base64,' . base64_encode(file_get_contents($source_image_path));
        }

        $this->getStorage()->close();

        $tmp_file_path = null;

        try {
            $request = (new shopAiApiRequest())
                ->loadFieldsFromApi($ai_facility)
                ->loadFieldValuesFromSettings()
                ->loadFieldValuesFromProduct(new shopProduct($product_id))
                ->setFieldValues([
                    'image_style' => ifempty($image_style, 'auto'),
                    'image_prompt' => ifset($image_prompt, ''),
                ]);

            if (!empty($image_data_url)) {
                $request->setFieldValue('image', $image_data_url);
            }

            $response = $request->generate([
                'timeout' => 280,
            ]);

            if (!empty($response['error_description'])) {
                if (ifset($response, 'error', null) === 'payment_required') {
                    $balance = (new waServicesApi)->getBalanceCreditUrl('AI');
                    if (ifset($balance, 'response', 'url', false)) {
                        $response['error_description'] = str_replace('%s', 'href="'.$balance['response']['url'].'"', $response['error_description']);
                    }
                }

                throw new waException($response['error_description']);
            } elseif (!empty($response['error'])) {
                throw new waException($response['error']);
            }

            $result_data_url = ifset($response, 'image_data_url', '');
            if (!$result_data_url) {
                throw new waException(_w('No image in the Webasyst ID API response.'), 500);
            }

            $decoded = $this->decodeDataUrl($result_data_url);

            $tmp_file_path = tempnam(sys_get_temp_dir(), 'improd');
            if (!$tmp_file_path) {
                throw new waException(_w('Temporary file creation error.'), 500);
            }
            file_put_contents($tmp_file_path, $decoded['data']);

            $file_ext = $this->resolveExtension($decoded['mime_type']);
            $new_image = $product_images_model->addImage($tmp_file_path, $product_id, 'product_image' . $file_ext);
            $config = wa('shop')->getConfig();
            shopImage::generateThumbs($new_image, $config->getImageSizes('default'));

            $this->response['image'] = $this->formatImageForResponse($new_image, $config);
            $this->logAction('product_edit', $product_id);
        } catch (Exception $e) {
            $this->errors[] = [
                'id' => 'ai_generate_image_error',
                'text' => $e->getMessage(),
            ];
        } finally {
            if ($tmp_file_path && file_exists($tmp_file_path)) {
                @unlink($tmp_file_path);
            }
        }
    }

    protected function decodeDataUrl($data_url)
    {
        if (strpos($data_url, 'data:') !== 0) {
            throw new waException(_w('Unknown image format.'), 500);
        }

        $raw = substr($data_url, 5);
        $parts = explode(',', $raw, 2);
        if (count($parts) < 2) {
            throw new waException(_w('Unknown image format.'), 500);
        }

        list($meta, $data) = $parts;
        list($mime_type, $encoding) = explode(';', $meta, 2) + ['', ''];

        if ($encoding && $encoding !== 'base64') {
            throw new waException(_w('Unknown image format.'), 500);
        }

        $decoded = base64_decode(str_replace(' ', '+', $data), true);
        if ($decoded === false) {
            throw new waException(_w('Unknown image format.'), 500);
        }

        return [
            'mime_type' => strtolower($mime_type),
            'data' => $decoded,
        ];
    }

    protected function resolveExtension($mime_type)
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/jpg' => '.jpg',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'image/png' => '.png',
        ];

        return ifset($map, strtolower($mime_type), '.png');
    }

    protected function formatImageForResponse(array $image, shopConfig $config)
    {
        $wa_app_url = wa()->getAppUrl(null, true);

        return [
            'id' => (string) $image['id'],
            'url' => shopImage::getUrl($image, $config->getImageSize('default')),
            'url_backup' => $wa_app_url . '?module=prod&action=origImage&backup=1&id=' . $image['id'],
            'url_original' => $wa_app_url . '?module=prod&action=origImage&id=' . $image['id'],
            'description' => ifset($image, 'description', ''),
            'size' => shopProdMediaAction::formatFileSize($image['size']),
            'name' => ifset($image, 'original_filename', ''),
            'width' => $image['width'],
            'height' => $image['height'],
            'uses_count' => 0,
        ];
    }
}
