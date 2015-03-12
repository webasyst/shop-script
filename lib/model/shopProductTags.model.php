<?php

class shopProductTagsModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_tags';

    /**
     * Method triggered when deleting product through shopProductModel
     * @param int[] $product_ids
     * @return bool
     */
    public function deleteByProducts(array $product_ids)
    {
        $tag_model = new shopTagModel();

        $count = 0;
        $sql = "
            SELECT tag_id, count(product_id) cnt FROM {$this->table}
            WHERE product_id IN (".implode(',', $product_ids).")
            GROUP BY tag_id";
        foreach ($this->query($sql) as $item) {
            $count += 1;
            $tag_model->query(
                "UPDATE ".$tag_model->getTableName()." SET count = count - {$item['cnt']}
                WHERE id = {$item['tag_id']}"
            );
        }
        if ($count > 0) {
            $tag_model->query("DELETE FROM ".$tag_model->getTableName()." WHERE count <= 0");
        }

        if ($cache = wa()->getCache()) {
            $cache->delete('tags');
        }
        return $this->deleteByField('product_id', $product_ids);
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
        $tags = array_unique(array_map('trim', $tags));
        $empty = array_search('', $tags, true);
        if ($empty !== false) {
            unset($tags[$empty]);
        }
        $tag_model = new shopTagModel();
        $tag_ids = $tag_model->getIds($tags);

        $sql = "SELECT tag_id FROM ".$this->table." WHERE product_id = i:id";
        $old_tag_ids = $this->query($sql, array('id' => $product_id))->fetchAll(null, true);

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

        if ($add_tag_ids || $remove_tag_ids) {
            if ($cache = wa()->getCache()) {
                $cache->delete('tags');
            }
        }
        // return new tags
        return $this->getData($product);
    }

    public function addTags($product_id, $tags)
    {
        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }
        $tag_model = new shopTagModel();
        $tag_ids = $tag_model->getIds($tags);
        $sql = "SELECT tag_id FROM ".$this->table." WHERE product_id = i:id";
        $old_tag_ids = $this->query($sql, array('id' => $product_id))->fetchAll(null, true);
        $add_tag_ids = array_diff($tag_ids, $old_tag_ids);
        if ($add_tag_ids) {
            $this->multipleInsert(array('product_id' => $product_id, 'tag_id' => $add_tag_ids));
            $tag_model->incCounters($add_tag_ids);
        }
        if ($cache = wa()->getCache()) {
            $cache->delete('tags');
        }
        return true;
    }

    public function deleteTags($product_id, $tags)
    {
        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }
        $tag_model = new shopTagModel();
        $tag_ids = $tag_model->getIds($tags);
        $sql = "SELECT tag_id FROM ".$this->table." WHERE product_id = i:id";
        $old_tag_ids = $this->query($sql, array('id' => $product_id))->fetchAll(null, true);
        $delete_tag_ids = array_intersect($tag_ids, $old_tag_ids);
        if ($delete_tag_ids) {
            $this->deleteByField(array('product_id' => $product_id, 'tag_id' => $delete_tag_ids));
            $tag_model->incCounters($delete_tag_ids, -1);
        }
        if ($cache = wa()->getCache()) {
            $cache->delete('tags');
        }
        return true;
    }

    /**
     * Tag tag of product(s)
     * @param int|array $product_id
     * @return array()
     */
    public function getTags($product_id)
    {
        if (!$product_id) {
            return array();
        }

        $sql = "
            SELECT t.id, t.name
            FROM ".$this->table." pt
            JOIN shop_tag t ON pt.tag_id = t.id
            WHERE pt.product_id IN (i:id)
        ";
        return $this->query($sql, array('id' => $product_id))->fetchAll('id', true);
    }

    /**
     *
     * Assign tags to products. Tags just assign to products (without removing if exist for concrete product)
     * @param array|int $product_id
     * @param array|int $tag_id
     */
    public function assign($product_id, $tag_id)
    {
        // define existing tags
        $sql = "SELECT * FROM {$this->table} ";
        if ($where = $this->getWhereByField('product_id', $product_id)) {
            $sql .= " WHERE $where";
        }
        $existed_tags = array();
        foreach ($this->query($sql) as $item) {
            $existed_tags[$item['product_id']][$item['tag_id']] = true;
        }

        // accumulate candidate for adding
        $add = array();
        foreach ((array)$tag_id as $t_id) {
            foreach ((array)$product_id as $p_id) {
                if (!isset($existed_tags[$p_id][$t_id])) {
                    $add[] = array('product_id' => $p_id, 'tag_id' => $t_id);
                }
            }
        }

        // adding itself
        if ($add) {
            $this->multipleInsert($add);
        }

        // recounting counters for this tags
        $tag_model = new shopTagModel();
        $tag_model->recount($tag_id);
        // clear cache
        if ($cache = wa()->getCache()) {
            $cache->delete('tags');
        }
    }


    /**
     * @param int|array $product_id
     * @param int|array $tag_id
     */
    public function delete($product_id, $tag_id)
    {
        if (!$product_id) {
            return false;
        }
        $product_id = (array)$product_id;

        // delete tags
        $this->deleteByField(array('product_id' => $product_id, 'tag_id' => $tag_id));
        // decrease count for tags
        $tag_model = new shopTagModel();
        $tag_model->recount($tag_id);
        // clear cache
        if ($cache = wa()->getCache()) {
            $cache->delete('tags');
        }
    }
}
