<?php

class shopMarketingPromoBannerUploadController extends waJsonController
{
    const ALLOWED_EXT = ['gif', 'jpg', 'jpeg', 'png'];

    /**
     * @var waRequestFileIterator
     */
    protected $file;

    protected $image_name;

    public function preExecute()
    {
        $this->file = waRequest::file('image');
    }

    public function execute()
    {
        $this->validate();
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->image_name = shopPromoBannerHelper::generateImageName();

        $this->saveImageCache();
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->response = [
            'name'      => $this->image_name,
            'ext'       => $this->file->extension,
            'file_name' => $this->image_name.'.'.$this->file->extension,
        ];
    }

    protected function validate()
    {
        if (!$this->file->count()) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _w('An image must be uploaded.'),
            ];
            return;
        }

        // Make sure the file has correct extension
        $ext = strtolower($this->file->extension);
        if (!in_array($ext, self::ALLOWED_EXT)) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _w('Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.'),
            ];
            return;
        }

        try {
            $this->file->waImage();
        } catch (Exception $e) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _ws('Not an image or invalid image:').' '.$this->file->name.': '.$e->getMessage(),
            ];
            return;
        }
    }

    protected function saveImageCache()
    {
        try {
            $cache_path = wa()->getAppCachePath('promo/', 'shop');
            waFiles::create($cache_path, true);
            $file_path = $cache_path.sprintf('%s.%s', $this->image_name, $this->file->extension);
            $res = $this->file->moveTo($file_path);
            if (!$res) {
                throw new waException('File could not be moved to cache.');
            }
            return $file_path;
        } catch (Exception $e) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _w('An error occurred during the image uploading:').' '.$e->getMessage(),
            ];
        }
    }
}