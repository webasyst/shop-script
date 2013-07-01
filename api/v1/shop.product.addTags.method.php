<?php

class shopProductAddTagsMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $this->getProduct($id);

        $tags = $this->post('tags', true);

        $product_tags_model = new shopProductTagsModel();
        $product_tags_model->addTags($id, $tags);
        $this->response = true;
    }
}