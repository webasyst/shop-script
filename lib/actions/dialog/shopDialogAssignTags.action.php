<?php

class shopDialogAssignTagsAction extends waViewAction
{
    /**
     * @var string
     */
    private $hash;

    /**
     * @var shopProductsCollection
     */
    private $collection;

    public function execute()
    {
        $can_assign_tags = $this->canAssignTags();
        $this->view->assign(array(
            'can_assign_tags' => $can_assign_tags
        ));

        if (!$can_assign_tags) {
            return;
        }

        $product_tags_model = new shopProductTagsModel();

        $total_count = $this->getTotalCount();

        $tags = array();
        $offset = 0;
        $count = 100;

        while ($offset < $total_count) {
            $ids  = array_keys($this->getProducts($offset, $count));
            $tags += $product_tags_model->getTags($ids);
            $offset += count($ids);
            if (!$ids) {
                break;
            }
        }

        $tag_model = new shopTagModel();
        $this->view->assign(array(
            'tags' => $tags,
            'popular_tags' => $tag_model->popularTags(),
        ));
    }

    protected function getHash()
    {
        if ($this->hash !== null) {
            return $this->hash;
        }
        $hash = $this->getRequest()->request('hash');
        if ($hash !== null) {
            $this->hash = trim($hash);
            return $this->hash;
        }
        $product_ids = $this->getRequest()->request('product_id');
        if ($product_ids !== null) {
            $product_ids = waUtils::toIntArray($product_ids);
            if ($product_ids) {
                $this->hash = 'id/' . join(',', $product_ids);
                return $this->hash;
            }
        }
        return null;
    }

    /**
     * @return int
     * @throws waException
     */
    protected function getTotalCount()
    {
        return (int)$this->getCollection()->count();
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws waException
     */
    protected function getProducts($limit, $offset)
    {
        return $this->getCollection()->getProducts('*', $limit, $offset);
    }

    /**
     * @return shopProductsCollection
     */
    protected function getCollection()
    {
        if ($this->collection) {
            return $this->collection;
        }

        $this->collection = new shopProductsCollection($this->getHash());
        return $this->collection;
    }

    protected function canAssignTags()
    {
        $hash = $this->getHash();
        if (!$hash) {
            return false;
        }

        if (wa()->getUser()->isAdmin('shop')) {
            return true;
        }

        $total_count = $this->getTotalCount();

        $product_collection = new shopProductsCollection($hash, array(
            'filter_by_rights' => true
        ));
        $count = (int)$product_collection->count();

        return $total_count === $count;
    }
}
