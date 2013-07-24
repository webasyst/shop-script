<?php

class shopCategoryRoutesModel extends waModel
{
    protected $table = 'shop_category_routes';

    public function getRoutes($category_id)
    {
        $sql = "SELECT route FROM ".$this->table." WHERE category_id = i:0";
        return  $this->query($sql, $category_id)->fetchAll(null, true);
    }

    public function setRoutes($category_id, $routes)
    {
        $this->deleteByField('category_id', $category_id);
        if ($routes) {
            $this->multipleInsert(array('category_id' => $category_id, 'route' => $routes));
        }
    }
}
