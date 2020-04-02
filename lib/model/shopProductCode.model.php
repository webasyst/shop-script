<?php
/**
 * This table stores product codes.
 * Product code is a data field associated with particular product item. Like IMEI, serial numbers, etc.
 * Product codes are not attached to shop_product/shop_product_skus because those are not items,
 * they represent catalogue listings and can not have serial numbers.
 * Product codes are attached to shop_order_items.
 * Can possibly be attached to product stock accounting in future, but currently stocks in Shop
 * deal with numbers and not individual items.
 */
class shopProductCodeModel extends waModel
{
    protected $table = 'shop_product_code';

    /**
     * $type_id === null means codes not attached to any product type
     * $type_id == 0 means codes attached to all product types
     * $type_id > 0 means codes attached to given product type
     */
    public function getByType($type_id)
    {
        if ($type_id === null) {
            $sql = "SELECT DISTINCT pc.*
                    FROM {$this->table} AS pc
                        LEFT JOIN shop_type_codes AS tc
                            ON tc.code_id=pc.id
                    WHERE tc.code_id IS NULL
                    ORDER BY pc.name";
            return $this->query($sql)->fetchAll('id');
        }
        if (!$type_id) {
            $type_id = 0;
        }

        $sql = "SELECT pc.*, GROUP_CONCAT(tc.type_id SEPARATOR ',') AS type_ids
                FROM {$this->table} AS pc
                    JOIN shop_type_codes AS tc
                        ON tc.code_id=pc.id
                        AND tc.type_id IN (0, ?)
                GROUP BY pc.id
                ORDER BY pc.name";

        $result = $this->query($sql, [$type_id])->fetchAll('id');
        foreach($result as &$type) {
            $ids = explode(',', $type['type_ids']);
            $type['type_ids'] = [];
            foreach($ids as $id) {
                $type['type_ids'][$id] = $id;
            }
        }
        unset($type);
        return $result;
    }

    public function getUniqueCode($code, $id = null)
    {
        if ($id) {
            $old_code = ifset(ref($this->getById($id)), 'code', null);
            if ($old_code && $old_code == $code) {
                return $code;
            }
        }

        $code = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim(waLocale::transliterate($code)));
        $code = trim($code, '_');

        $sql = "SELECT count(*)
                FROM `{$this->table}`
                WHERE `id` <> ?
                    AND `code` LIKE ?";
        while($this->query($sql, [(int)$id, $code])->fetchField()) {
            $code .= mt_rand(0, 9);
        }
        return $code;
    }

    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY name";
        return $this->query($sql)->fetchAll($key, $normalize);
    }
}
