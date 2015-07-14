<?php
class shopCategoryOgModel extends waModel
{
    protected $table = 'shop_category_og';

    public function get($id)
    {
        return $this->query("SELECT property, content FROM {$this->table} WHERE category_id=?", (int)$id)->fetchAll('property', true);
    }

    public function set($id, $params = array())
    {
        $id = (int) $id;
        if (!$id) {
            return false;
        }

        $this->clear($id);

        $values = array();
        foreach($params as $property => $content) {
            @$content = (string) $content;
            if (strlen($content)) {
                $values[] = array(
                    'category_id' => $id,
                    'property' => $property,
                    'content' => $content,
                );
            }
        }

        $values && $this->multipleInsert($values);
        return true;
    }

    public function clear($id)
    {
        $id = (int) $id;
        $id && $this->deleteByField(array(
            'category_id' => $id,
        ));
        return !!$id;
    }
}

