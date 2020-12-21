<?php
class shopProductImagesModel extends waModel
{
    protected $table = 'shop_product_images';

    /**
     * badge types constants
     * @see self::isSystemBadgeType
     * @see self::isCustomBadgeType
     */
    const BADGE_TYPE_NEW = 0;
    const BADGE_TYPE_BESTSELLER = 1;
    const BADGE_TYPE_BESTPRICE = 2;
    const BADGE_TYPE_CUSTOM = 100;

    public function getImages($product_id, $sizes = array(), $key = 'id', $absolute = false)
    {
        if (empty($product_id)) {
            return array();
        }
        if (!$sizes) {
            $sizes = array('crop' => wa('shop')->getConfig()->getImageSize('crop'));
        } elseif (is_numeric($sizes)) {
            $sizes = array($sizes => $sizes);
        } elseif (is_string($sizes)) {
            $sizes = array((string)$sizes => wa('shop')->getConfig()->getImageSize((string)$sizes));
            foreach ($sizes as $k => $s) {
                if ($s === null) {
                    $sizes[$k] = $k;
                }
            }
        }

        if ($key != 'product_id') {
            $key = 'id';
        }

        $where = $this->getWhereByField('product_id', $product_id);
        $images = array();
        foreach ($this->query("SELECT * FROM {$this->table} WHERE $where ORDER BY product_id, sort") as $image) {
            $image['edit_datetime_ts'] = $image['edit_datetime'] ? strtotime($image['edit_datetime']) : null;
            if (!empty($sizes)) {
                foreach ($sizes as $name => $size) {
                    $image['url_'.$name] = shopImage::getUrl($image, $size, $absolute);
                }
            }
            if ($key == 'id') {
                $images[$image['id']] = $image;
            } else {
                $images[$image['product_id']][$image['id']] = $image;
            }
        }
        return $images;
    }

    public function countImages($product_ids)
    {
        if (empty($product_ids)) {
            return array();
        }
        $product_ids = (array) $product_ids;
        $sql = "SELECT product_id, count(*)
                FROM {$this->table}
                WHERE product_id IN (?)
                GROUP BY product_id";
        return $this->query($sql, array($product_ids))->fetchAll('product_id', true) + array_fill_keys($product_ids, 0);
    }

    public function move($id, $before_id = null)
    {
        $image = $this->getById($id);
        if (empty($image)) {
            return false;
        }
        $product_id = $image['product_id'];
        if (!$before_id) {
            $sort = $this->query("SELECT MAX(sort) AS sort FROM {$this->table} WHERE product_id = $product_id")->fetchField('sort') + 1;
        } else {
            $before = $this->getById($before_id);
            if (empty($before_id)) {
                return false;
            }
            $sort = $before['sort'];
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE product_id = $product_id AND sort >= $sort");
        }
        if (!$this->updateById($id, array('sort' => $sort))) {
            return false;
        }

        $main_image = $this->query("SELECT id, filename, ext FROM {$this->table} WHERE product_id = $product_id ORDER BY sort LIMIT 1")->fetch();
        $product_model = new shopProductModel();
        $product_model->updateById($product_id, array(
            'image_id' => $main_image['id'], 'image_filename' => $main_image['filename'], 'ext' => $main_image['ext']));

        return true;
    }

    public function add($data, $is_default = false)
    {
        $product_id = 0;
        if (isset($data['product_id'])) {
            $product_id = (int)$data['product_id'];
        }
        if (!$product_id) {
            return false;
        }
        $info = $this->select('MAX(`sort`)+1 AS `max`, COUNT(1) AS `cnt`')->where($this->getWhereByField('product_id', $product_id))->fetch();

        $data['sort'] = $info['cnt'] ? $info['max'] : 0;
        if (!$info['cnt']) {
            $is_default = true;
        }
        $image_id = $this->insert($data);

        if ($is_default) {
            $product_model = new shopProductModel();
            $product_model->updateById($product_id, array(
                'image_id'       => $image_id,
                'image_filename' => $data['filename'],
                'ext'            => $data['ext'],
            ));
        }
        return $image_id;
    }

    /**
     * Delete one image
     * @param int $id ID of image
     * @return bool
     * @throws waException
     */
    public function delete($id)
    {
        $id = (int)$id;
        if (!$id) {
            return false;
        }
        $image = $this->getById($id);
        if (!$image) {
            return false;
        }

        $product_id = (int)$image['product_id'];

        /**
         * Delete image event
         * @param array $image
         *
         * @event product_images_delete
         */

        wa('shop')->event('product_images_delete', $image);

        // first of all try delete files from disk
        waFiles::delete(shopImage::getThumbsPath($image), true);
        waFiles::delete(shopImage::getPath($image), true);
        waFiles::delete(shopImage::getOriginalPath($image), true);

        if (!$this->deleteById($id)) {
            return false;
        }

        // first image for this product is main image for this product
        $sql = <<<SQL
SELECT
  id       AS image_id,
  filename as image_filename,
  ext
FROM {$this->table} WHERE product_id = {$product_id} ORDER BY sort LIMIT 1
SQL;

        $main_image = $this->query($sql)->fetchAssoc();
        if (!$main_image) {
            $main_image = array('image_id' => null, 'image_filename' => '', 'ext' => null);
        }
        $product_model = new shopProductModel();
        $product_model->updateById($product_id, $main_image);

        // make NULL image_id for that skus of this product which have image_id equals this image ID
        $sql = <<<SQL
UPDATE `shop_product_skus`
SET image_id = NULL
WHERE product_id = $product_id AND image_id = $id
SQL;

        $this->exec($sql);

        return true;
    }

    public function deleteByProducts(array $product_ids, $hard = false)
    {
        if ($hard) {
            foreach ($product_ids as $product_id) {
                waFiles::delete(shopProduct::getPath($product_id, 'images', false));
                waFiles::delete(shopProduct::getPath($product_id, 'images', true));
            }
        }
        $this->deleteByField('product_id', $product_ids);
    }

    public static function isSystemBadgeType($type)
    {
        return $type < self::BADGE_TYPE_CUSTOM;
    }

    public static function isCustomBadgeType($type)
    {
        return !self::isSystemBadgeType($type);
    }

    public static function getBadgeCode($type)
    {
        if ($type == self::BADGE_TYPE_NEW) {
            return '<div class="badge new"><span>'._w('New!').'</span></div>';
        } elseif ($type == self::BADGE_TYPE_BESTSELLER) {
            return '<div class="badge bestseller"><span>'._w('Bestseller!').'</span></div>';
        } elseif ($type == self::BADGE_TYPE_BESTPRICE) {
            return '<div class="badge low-price"><span>'._w('Low price!').'</span></div>';
        } else {
            return '<div class="badge" style="background-color: #a1fcff;"><span>'._w('YOUR TEXT').'</span></div>';
        }
    }

    /**
     * @return int
     */
    public function countAvailableImages()
    {
        $result = 0;
        if (wa()->getUser()->getRights('shop', 'settings')) {
            $result = $this->countAll();
        }
        return $result;
    }

    /**
     * @param int $offset
     * @param null $limit
     * @return array
     */
    public function getAvailableImages($offset = 0, $limit = null)
    {
        if (!$limit) {
            $limit = (int) $offset;
            $offset = 0;
        } else {
            $offset = (int) $offset;
            $limit = (int) $limit;
        }
        $result = [];

        if (wa()->getUser()->getRights('shop', 'settings')) {
            $result = $this->select('*')->order('product_id, id')->limit("{$offset}, {$limit}")->fetchAll('id');
        }
        return $result;
    }

    /**
     * @param string|waImage|waRequestFile $image
     * @param int|array $product
     * @param string $filename
     * @param string $description
     * @return array
     * @throws waException
     */
    public function addImage($image, $product, $filename = null, $description = null)
    {

        if (is_array($product)) {
            $product_id = (int)ifset($product['product_id']);
            $image_id = (int)ifset($product['image_id']);
        } else {
            $product_id = (int)$product;
            $image_id = null;
        }

        if (empty($product_id)) {
            throw new waException('Incorrect product id');
        }

        if (is_string($image)) {
            $file = $image;
            $image = waImage::factory($file);
            if ($filename === null) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
            }
        } elseif ($image instanceof waImage) {
            /** @var waImage $image */
            $file = $image->file;
            if ($filename === null) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
            }
        } elseif ($image instanceof waRequestFile) {
            /** @var waRequestFile $file */
            $file = $image;
            $image = $file->waImage();
            if (!$image) {
                throw new waException('Incorrect image');
            }
            if ($filename === null) {
                $filename = $file->name;
            }
        } else {
            throw new waException('Incorrect image');
        }

        $image_changed = false;

        /**
         * Extend upload process
         * Make extra workup
         * @event image_upload
         * @params waImage $image
         * @return bool $result Is image changed
         */
        $event = wa('shop')->event('image_upload', $image);

        if ($event) {
            $image_changed = count(array_filter($event));
        }

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $original_filename = $filename;
        if ($config->getOption('image_filename')) {
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
        } else {
            $filename = '';
        }

        $data = array(
            'product_id'        => $product_id,
            'upload_datetime'   => date('Y-m-d H:i:s'),
            'width'             => $image->width,
            'height'            => $image->height,
            'size'              => filesize($image->file),
            'filename'          => basename($filename, '.'.waFiles::extension($filename)),
            'description'       => $description,
            'original_filename' => pathinfo($original_filename, PATHINFO_BASENAME),
            'ext'               => pathinfo($original_filename, PATHINFO_EXTENSION),
        );

        if ($image_id) {
            if ($this->updateById($image_id, $data)) {
                $data['id'] = $image_id;
            }

            // Delete obsolete thumbs, if exist
            $thumbs_path = shopImage::getThumbsPath($data);
            if (file_exists($thumbs_path)) {
                waFiles::delete($thumbs_path, true);
            }
        } else {
            $data['id'] = $this->add($data);
        }

        if (empty($data['id'])) {
            throw new waException("Database error");
        }

        $image_path = shopImage::getPath($data);
        if ((file_exists($image_path) && !is_writable($image_path))
            || (!file_exists($image_path) && !waFiles::create($image_path))
        ) {
            $this->deleteById($data['id']);
            throw new waException(
                sprintf(
                    "The insufficient file write permissions for the %s folder.",
                    substr($image_path, strlen($config->getRootPath()))
                )
            );
        }

        $target_path = null;

        if ($image_changed) {
            $image->save($image_path);
            // save original
            $original_file = shopImage::getOriginalPath($data);
            if ($config->getOption('image_save_original') && $original_file) {
                $target_path = $original_file;
            }
        } else {
            $target_path = $image_path;
        }

        unset($image);

        if ($target_path) {
            if ($file instanceof waRequestFile) {
                $file->moveTo($target_path);
            } else {
                waFiles::copy($file, $target_path);
            }
        }

        return $data;
    }
}
