<?php

class shopCategoryRoutesModel extends waModel
{
    protected $table = 'shop_category_routes';

    /**
     * Returns category routes
     * @param int|array $category_id
     * @param bool $show_private
     * @return array
     */
    public function getRoutes($category_id, $show_private = true)
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
        } elseif (is_array($category_id)) {
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

    public function setRoutes($category_id, $routes, $include_descendants = false)
    {
        $category_ids = array();
        if (!$include_descendants) {
            $category_ids[] = $category_id;
        } else {
            $category_ids = $this->query("
                SELECT c.id FROM shop_category c
                JOIN shop_category p ON c.left_key > p.left_key AND c.right_key < p.right_key
                WHERE p.id  = ?", $category_id)->fetchAll(null, true);
            $category_ids[] = $category_id;
        }
        $this->deleteByField('category_id', $category_ids);
        if ($routes) {
            $data = array();
            foreach ($category_ids as $category_id) {
                foreach ($routes as $route) {
                    $data[] = array('category_id' => $category_id, 'route' => $route);
                }
            }
            $this->multipleInsert($data);
        }
    }
}
