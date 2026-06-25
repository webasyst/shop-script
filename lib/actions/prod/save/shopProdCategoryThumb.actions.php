<?php
/** Upload/reupload/delete catgory thumb image */
class shopProdCategoryThumbActions extends waActions
{
    protected $category_model;

    public function __construct()
    {
        $this->category_model = new shopCategoryModel();
    }

    public function uploadAction()
    {
        $category = $this->getCategory();
        $file = waRequest::file('file');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!$file->uploaded()) {
            $error_message = $file->error;
            return $this->displayJson(null, [[
                'id' => 'upload_failed',
                'text' => ifempty($error_message, sprintf(_ws('Failed to upload file %s.'), $f->name)),
            ]]);
        }
        if (!in_array(strtolower($file->extension), $allowed)) {
            return $this->displayJson(null, [[
                'id' => 'extension_not_allowed',
                'text' => _w("Files with extensions *.gif, *.jpg, *.jpeg, *.png, *.webp are allowed only."),
            ]]);
        }

        try {
            $image = $file->waImage();
        } catch (Throwable $e) {
            return $this->displayJson(null, [[
                'id' => 'invalid_format',
                'text' => _w("Invalid image format."),
            ]]);
        }

        if (!empty($category['thumb_ext'])) {
            $this->deleteCategoryThumb($category);
        }
        $category['thumb_ext'] = $image->getExt();
        $destination_dir = shopCategoryHelper::getImagePath((int)$category['id'], '0');
        waFiles::create($destination_dir, true);
        if (!$file->moveTo($destination_dir, '0.'.$category['thumb_ext'])) {
            $this->category_model->updateById($category['id'], [
                'edit_datetime' => date('Y-m-d H:i:s'),
                'thumb_ext' => null,
            ]);
            return $this->displayJson(null, [[
                'id' => 'server_error',
                'text' => 'Unable to save image to '.$destination_dir,
            ]]);
        }

        $this->category_model->updateById($category['id'], [
            'edit_datetime' => date('Y-m-d H:i:s'),
            'thumb_ext' => $category['thumb_ext'],
        ]);

        (new shopInstaller())->ensureThumbPhp('categories');

        return $this->displayJson([
            'thumb' => shopCategoryHelper::getThumbInfo($category),
        ]);
    }

    public function deleteAction()
    {
        $category = $this->getCategory();
        if (!empty($category['thumb_ext'])) {
            $this->deleteCategoryThumb($category);
            $this->category_model->updateById($category['id'], [
                'edit_datetime' => date('Y-m-d H:i:s'),
                'thumb_ext' => null,
            ]);
        }
        return $this->displayJson('ok');
    }

    protected function getCategory()
    {
        $category_id = waRequest::request('category_id', null, 'int');
        if ($category_id) {
            $category = $this->category_model->getById($category_id);
        }
        if (empty($category)) {
            throw new waException('Category is requied', 400);
        }
        return $category;
    }

    protected function deleteCategoryThumb(array $category)
    {
        $protected_dir = shopCategoryHelper::getImagePath((int)$category['id'], '0');
        $public_dir = shopCategoryHelper::getImagePath((int)$category['id'], '0', true);
        waFiles::delete($protected_dir, true);
        waFiles::delete($public_dir, true);
    }
}
