<?php

class shopMarketingPromoAiGenerateBannerController extends waJsonController
{
    const BANNER_FACILITY = 'store_product_image_banner';

    protected $promo_id;

    public function execute()
    {
        $this->validateRights();

        $this->promo_id = waRequest::post('promo_id', null, waRequest::TYPE_INT);
        $image_style = waRequest::post('image_style', '', waRequest::TYPE_STRING_TRIM);
        $image_prompt = waRequest::post('image_prompt', '', waRequest::TYPE_STRING_TRIM);
        $source_image_filename = basename(waRequest::post('source_image_filename', '', waRequest::TYPE_STRING_TRIM));
        $delete_source_cache = waRequest::post('delete_source_cache', false, waRequest::TYPE_INT);
        $allowed_styles = shopHelper::getAllowedProdAiImageStyles();

        if (!$image_style || $image_style === 'auto' || !isset($allowed_styles[$image_style])) {
            $this->errors[] = [
                'id' => 'invalid_request',
                'text' => _w('Invalid request data.'),
            ];
            return;
        }

        if (!$image_prompt && !$source_image_filename) {
            $this->errors[] = [
                'id' => 'invalid_request',
                'text' => _w('Please describe in few words what should be there on a banner.'),
            ];
            return;
        }

        $this->getStorage()->close();

        $source = [];
        try {
            $source = $this->getSourceImage($source_image_filename, !empty($delete_source_cache));
            $response = $this->generateBannerImage($image_style, $image_prompt, $source);
            $result_data_url = ifset($response, 'image_data_url', '');
            if (!$result_data_url) {
                throw new waException(_w('No image in the Webasyst ID API response.'), 500);
            }

            $decoded = $this->decodeDataUrl($result_data_url);
            $file_name = $this->saveImageToCache($decoded);

            $this->response = [
                'file_name' => $file_name,
                'image_url' => $result_data_url,
            ];
        } catch (Exception $e) {
            $this->errors[] = [
                'id' => 'ai_generate_banner_error',
                'text' => $e->getMessage(),
            ];
        } finally {
            if (!empty($source['delete_after_use']) && !empty($source['cache_path']) && file_exists($source['cache_path'])) {
                @unlink($source['cache_path']);
            }
        }
    }

    protected function validateRights()
    {
        if (!wa()->getUser()->getRights('shop', 'marketing')) {
            throw new waRightsException(_w('Access denied'));
        }
    }

    protected function getSourceImage($filename, $delete_after_use = false)
    {
        if (!$filename) {
            return [];
        }

        $cache_path = wa()->getAppCachePath('promo/' . $filename, 'shop');
        if (is_readable($cache_path)) {
            return [
                'path' => $cache_path,
                'cache_path' => $cache_path,
                'delete_after_use' => $delete_after_use,
                'mime_type' => waFiles::getMimeType('.' . pathinfo($filename, PATHINFO_EXTENSION)),
            ];
        }

        if (!$this->promo_id) {
            throw new waException(_w('Banner image not found.'), 404);
        }

        $promo_path = shopPromoBannerHelper::getPromoBannerPath($this->promo_id, $filename);
        if (!is_readable($promo_path)) {
            throw new waException(_w('Banner image not found.'), 404);
        }

        return [
            'path' => $promo_path,
            'mime_type' => waFiles::getMimeType('.' . pathinfo($filename, PATHINFO_EXTENSION)),
        ];
    }

    protected function generateBannerImage($image_style, $image_prompt, array $source)
    {
        $request = (new shopAiApiRequest())
            ->loadFieldsFromApi(self::BANNER_FACILITY)
            ->loadFieldValuesFromSettings()
            ->setFieldValues([
                'image_style' => $image_style,
                'image_prompt' => ifset($image_prompt, ''),
            ]);

        if (!empty($source['path'])) {
            $request->setFieldValue('image', $this->encodeImageAsDataUrl($source['path'], $source['mime_type']));
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

        return $response;
    }

    protected function encodeImageAsDataUrl($path, $mime_type)
    {
        return 'data:' . $mime_type . ';base64,' . base64_encode(file_get_contents($path));
    }

    protected function saveImageToCache(array $decoded)
    {
        $cache_path = wa()->getAppCachePath('promo/', 'shop');
        waFiles::create($cache_path, true);

        $file_name = shopPromoBannerHelper::generateImageName() . $this->resolveExtension($decoded['mime_type']);
        $file_path = $cache_path . $file_name;

        if (file_put_contents($file_path, $decoded['data']) === false) {
            throw new waException(_w('Temporary file creation error.'), 500);
        }

        return $file_name;
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
}
