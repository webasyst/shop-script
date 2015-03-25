<?php

class shopCategoryRoutesModel extends waModel
{
    protected $table = 'shop_category_routes';

    /**
     * Returns category routes
     * @param int|array $category_id
     * @return array
     */
    public function getRoutes($category_id, $show_private=true)
    {
        static $is_route_private = null;
        if ($is_route_private === null) {
            $is_route_private = array();
            foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
                foreach ($routes as $r) {
                    $is_route_private[$domain.'/'.$r['url']] = !empty($r['private']);
                }
            }
        }

        if (!$category_id) {
            return array();
        } else if (is_array($category_id)) {
            $return_as_array = true;
        } else {
            $return_as_array = false;
            $category_id = array($category_id);
        }

        $category_id = array_map('intval', $category_id);
        $sql = "SELECT category_id, route FROM ".$this->table." WHERE category_id IN (".implode(',', $category_id).")";
        $result = array();
        foreach ($this->query($sql) as $row) {
            if (!$show_private && !empty($is_route_private[$row['route']])) {
                continue;
            }
            $result[$row['category_id']][] = $row['route'];
        }

        if (!$return_as_array && $result) {
            $result = reset($result);
        }

        return $result;
    }

    public function setRoutes($category_id, $routes)
    {
        $this->deleteByField('category_id', $category_id);
        if ($routes) {
            $this->multipleInsert(array('category_id' => $category_id, 'route' => $routes));
        }
    }

}

