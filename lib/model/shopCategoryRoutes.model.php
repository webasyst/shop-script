<?php

class shopCategoryRoutesModel extends waModel
{
    protected $table = 'shop_category_routes';

    /**
     * Returns category routes
     * @param int|array $category_id
     * @return array
     */
    public function getRoutes($category_id)
    {
        if (is_array($category_id)) {
            if (!$category_id) {
                return array();
            }
            $category_id = array_map('intval', $category_id);
            $sql = "SELECT category_id, route FROM ".$this->table." WHERE category_id IN (".implode(',', $category_id).")";
            $rows = $this->query($sql)->fetchAll();
            $result = array();
            foreach ($rows as $row) {
                $result[$row['category_id']][] = $row['route'];
            }
            return $result;
        } else {
            $sql = "SELECT route FROM ".$this->table." WHERE category_id = i:0";
            return  $this->query($sql, $category_id)->fetchAll(null, true);
        }
    }

    public function setRoutes($category_id, $routes)
    {
        $this->deleteByField('category_id', $category_id);
        if ($routes) {
            $this->multipleInsert(array('category_id' => $category_id, 'route' => $routes));
        }
    }



}
