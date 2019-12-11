<?php

class shopDialogProductsDeleteAction extends waViewAction
{
    /**
     * @var int
     */
    private $count;

    /**
     * @var string
     */
    private $hash;

    public function execute()
    {
        $this->view->assign(array(
            'count' => $this->getTotalCount(),
            'can_delete' => $this->canDelete()
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

    protected function getTotalCount()
    {
        if ($this->count !== null) {
            return $this->count;
        }

        $count = $this->getRequest()->request('count');
        if ($count === null) {
            $product_collection = new shopProductsCollection($this->getHash());
            $count = $product_collection->count();
        }
        $this->count = (int)$count;

        return $this->count;
    }

    protected function canDelete()
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
            'filter_by_rights' => 'delete'
        ));
        $count = (int)$product_collection->count();

        return $total_count === $count;
    }
}
