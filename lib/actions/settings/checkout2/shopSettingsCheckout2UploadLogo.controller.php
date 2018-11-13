<?php

class shopSettingsCheckout2UploadLogoController extends waJsonController
{
    public function execute()
    {
        $storefront = waRequest::post('storefront_id',null, waRequest::TYPE_STRING_TRIM);
        if (!$storefront) {
            return $this->insertError(_w('Missing storefront id.'));
        }

        $file = waRequest::file('logo');
        if (!$file->uploaded()) {
            return $this->insertError(_w('Image uploading error'));
        }

        if (preg_match('~\.(jpe?g|png|gif|svg)$~i', $file->name, $matches)) {
            $ext = $matches[1];
        }

        if (!isset($ext)) {
            return $this->insertError(_w('Invalid image format.'));
        }

        $name = $storefront.'-'.time().'.'.$ext;
        $url = shopCheckoutConfig::getLogoUrl($name);
        $path = shopCheckoutConfig::getLogoPath($name);

        try {
            waFiles::delete($path);
            $file->copyTo($path);
        } catch (Exception $e) {
            return $this->insertError($e->getMessage());
        }

        clearstatcache();

        $this->response = [
            'name' => $name,
            'url'  => $url,
        ];
    }

    protected function insertError($message)
    {
        $this->errors = [
            'message' => $message,
        ];
        clearstatcache();
    }
}