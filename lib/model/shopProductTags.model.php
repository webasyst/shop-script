<?php

class shopProductTagsModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_tags';

    public function deleteByProducts(array $product_ids)
    {
        $tag_model = new shopTagModel();

        $count = 0;
        foreach ($this->query("SELECT tag_id, count(product_id) cnt FROM {$this->table}
            WHERE product_id IN (".implode(',', $product_ids).")
            GROUP BY tag_id")
            as $item)
        {
            $count += 1;
            $tag_model->query("UPDATE ".$tag_model->getTableName()." SET count = count - {$item['cnt']}");
        }
        if ($count > 0) {
            $tag_model->query("DELETE FROM ".$tag_model->getTableName()." WHERE count <= 0");
        }

        $this->deleteByField('product_id', $product_ids);
    }


    /**
     * Returns tags of the product
     *
     * @param shopProduct $product
     * @return array
     */
    public function getData(shopProduct $product)
    {
        $product_id = $product->getId();
        $sql = "SELECT t.id, t.name FROM ".$this->table." pt
                JOIN shop_tag t ON pt.tag_id = t.id
                WHERE pt.product_id = i:id";
        return $this->query($sql, array('id' => $product_id))->fetchAll('id', true);
    }


    /**
     * @param shopProduct $product
     * @param string|array $tags
     * @return array
     */
    public function setData(shopProduct $product, $tags)
    {
        $product_id = $product->getId();
        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }
        $tag_model = new shopTagModel();
        $tag_ids = $tag_model->getIds($tags);

        $old_tag_ids = $this->query("SELECT tag_id FROM ".$this->table."
            WHERE product_id = i:id", array('id' => $product_id))->fetchAll(null, true);

        // find new tags to add
        $add_tag_ids = array_diff($tag_ids, $old_tag_ids);
        if ($add_tag_ids) {
            $this->multipleInsert(array('product_id' => $product_id, 'tag_id' => $add_tag_ids));
            $tag_model->incCounters($add_tag_ids);
        }

        // find tags to remove
        $remove_tag_ids = array_diff($old_tag_ids, $tag_ids);
        if ($remove_tag_ids) {
            $this->deleteByField(array('product_id' => $product_id, 'tag_id' => $remove_tag_ids));
            $tag_model->incCounters($remove_tag_ids, -1);
        }
        // return new tags
        return $this->getData($product);
    }
}