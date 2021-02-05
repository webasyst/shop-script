<?php
/**
 * Update existing image, possibly changing its image data (crop, rotate, etc.)
 * New image data comes via waRequest
 */
class shopProdSaveImageDetailsController extends waJsonController
{
    public function execute()
    {
        $image_id = waRequest::request('id', null, waRequest::TYPE_INT);
        $restore_from_backup = waRequest::request('state') === 'restored';

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($image_id);
        if (!$image) {
            throw new waException(_w("Image not found"), 404);
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($image['product_id'])) {
            throw new waException(_w("Access denied"), 403);
        }

        $this->getStorage()->close();

        $file = waRequest::file('image');

        try {
            $data = waRequest::post('data', array());
            $image['description'] = ifset($data['description'], '');
            $this->response = $this->save($file, $image, $restore_from_backup);
        } catch (Exception $e) {
            $this->errors[] = [
                'error' => _w('File uploading error').' '.$e->getMessage(),
            ];
        }
    }

    protected function save(waRequestFile $file, array $image, $restore_from_backup)
    {
        $product_images_model = new shopProductImagesModel();
        $backup_image_path = shopImage::getOriginalPath($image);
        $config = wa('shop')->getConfig();
        if ($file->uploaded()) {
            //
            // Image data came via POST. Update image on disk.
            //

            // Backup original image, if allowed in settings.
            // It may be used to restore from original via web tool.
            if (wa('shop')->getConfig()->getOption('image_save_original')) {
                $protected_image_path = shopImage::getPath($image);
                if (file_exists($protected_image_path) && !file_exists($backup_image_path)) {
                    waFiles::copy($protected_image_path, $backup_image_path);
                }
            }

            $image = $product_images_model->addImage($file, [
                'product_id' => $image['product_id'],
                'image_id' => $image['id'],
            ], $image['filename'].'.'.$image['ext'], $image['description']);

            shopImage::generateThumbs($image, $config->getImageSizes("default"));
        } else if ($restore_from_backup && file_exists($backup_image_path)) {
            //
            // No image data in POST. Restore from backup.
            //
            $image = $product_images_model->addImage($backup_image_path, [
                'product_id' => $image['product_id'],
                'image_id' => $image['id'],
            ], $image['filename'].'.'.$image['ext'], $image['description']);
        } else {
            //
            // No need to update image or restore from backup.
            // Simply update description.
            //
            $product_images_model->updateById($image['id'], [
                'description' => $image['description'],
            ]);
        }

        // same data as shopProdImageUploadController
        return [
            "id" => $image["id"],
            "url" => shopImage::getUrl($image, $config->getImageSize('default')),
            "url_original" => wa()->getAppUrl(null, true) . "?module=prod&action=origImage&id=" . $image["id"],
            "description" => $image["description"],
            "size" => shopProdMediaAction::formatFileSize($image["size"]),
            "name" => $image["original_filename"],
            "width" => $image["width"],
            "height" => $image["height"],
            "uses_count" => 0
        ];
    }
}
