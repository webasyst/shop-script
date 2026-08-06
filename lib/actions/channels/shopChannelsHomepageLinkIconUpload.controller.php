<?php

class shopChannelsHomepageLinkIconUploadController extends waJsonController
{
    protected const MAX_FILE_SIZE = 1048576;
    protected const MAX_IMAGE_SIDE = 1024;

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        }

        $file = waRequest::file('icon');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!$file->uploaded()) {
            $this->errors[] = [
                'id' => 'upload_failed',
                'text' => _w('File uploading error'),
            ];
            return;
        }

        if ((int) $file->size > self::MAX_FILE_SIZE) {
            $this->errors[] = [
                'id' => 'file_too_large',
                'text' => sprintf_wp('Maximum allowed file size is %s.', waFiles::formatSize(self::MAX_FILE_SIZE)),
            ];
            return;
        }

        if (!in_array(strtolower($file->extension), $allowed, true)) {
            $this->errors[] = [
                'id' => 'extension_not_allowed',
                'text' => _w('Files with extensions *.gif, *.jpg, *.jpeg, *.png, *.webp are allowed only.'),
            ];
            return;
        }

        try {
            $image = $file->waImage();
        } catch (Throwable $e) {
            $this->errors[] = [
                'id' => 'invalid_format',
                'text' => _w('Invalid image format.'),
            ];
            return;
        }

        if ($image->width > self::MAX_IMAGE_SIDE || $image->height > self::MAX_IMAGE_SIDE) {
            $this->errors[] = [
                'id' => 'image_too_large',
                'text' => sprintf_wp('Maximum allowed image size is %dx%d pixels.', self::MAX_IMAGE_SIDE, self::MAX_IMAGE_SIDE),
            ];
            return;
        }

        $ext = strtolower($image->getExt());
        $name = sprintf('%s.%s', md5(uniqid('', true).$file->name), $ext);
        $path = wa()->getDataPath('homepage-link-icons/', true, 'shop');
        waFiles::create($path, true);

        if (!$file->moveTo($path, $name)) {
            $this->errors[] = [
                'id' => 'server_error',
                'text' => _w('Failed to save the image.'),
            ];
            return;
        }

        $relative_path = 'homepage-link-icons/'.$name;
        $this->response = [
            'path' => $relative_path,
            'url' => wa()->getDataUrl($relative_path, true, 'shop', true),
        ];
    }
}
