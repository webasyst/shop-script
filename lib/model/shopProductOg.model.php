<?php
class shopProductOgModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_og';

    public function getData(shopProduct $product)
    {
        return $this->query(
            "SELECT property, content
             FROM {$this->table}
             WHERE product_id=?",
            array($product->id)
        )->fetchAll('property', true);
    }

    public function setData(shopProduct $product, $data)
    {
        if (!$data) {
            $data = array();
        } else if (!is_array($data)) {
            return $this->getData($product);
        }
        $values = array();
        foreach($data as $property => $content) {
            @$content = $data[$property] = (string) $content;
            if (strlen($content)) {
                $values[] = array(
                    'product_id' => $product->id,
                    'property' => $property,
                    'content' => $content,
                );
            } else {
                unset($data[$property]);
            }
        }
        $this->deleteByProducts(array($product->id));
        $this->multipleInsert($values);
        return $data;
    }

    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }
}
