<?php
/**
 *
 * @author Webasyst
 * @version SVN: $Id: shopCategory.model.php 2026 2012-08-14 14:39:28Z vlad $
 */
class shopCategoryModel extends waNestedSetModel
{
    protected $table = 'shop_category';

    protected $left = 'left_key';
    protected $right = 'right_key';
    protected $parent = 'parent_id';

    const TYPE_STATIC = 0;
    const TYPE_DYNAMIC = 1;


    public function getByRoute($route)
    {
        $sql = "SELECT c.id, c.full_url FROM shop_category c
           LEFT JOIN shop_category_routes cr ON c.id = cr.category_id
           WHERE cr.route IS NULL OR cr.route = '".$this->escape($route)."'";
        return $this->query($sql)->fetchAll();
    }

    public function getAll($key = null, $normalize = false)
    {
        return parent::getAll($key, $normalize);
    }

    public function getByName($name)
    {
        $sql = "SELECT * FROM ".$this->table." WHERE name LIKE '%".$this->escape($name, 'like')."%'";
        return $this->query($sql)->fetchAll();
    }

    /**
     * @param string $fields
     * @param bool $static_only
     * @return array
     */
    public function getFullTree($fields = '', $static_only = false)
    {
        if (!$fields) {
            $fields = 'id, left_key, right_key, parent_id, depth, name, count, type, status';
        }

        $fields = $this->escape($fields);

        $where = $static_only ? 'WHERE type='.self::TYPE_STATIC : '';
        $sql = "SELECT $fields FROM {$this->table} $where ORDER BY {$this->left}";
        return $this->query($sql)->fetchAll('id');
    }

    /**
     * Returns subtree
     *
     * @param int $id
     * @param int $depth related depth default is unlimited
     * @param bool $escape
     * @param array $where
     * @return array
     */
    public function getTree($id, $depth = null, $escape = false, $route = null)
    {
        $where = array();
        if ($id) {
            $parent = $this->getById($id);
            $left  = (int) $parent[$this->left];
            $right = (int) $parent[$this->right];
        } else {
            $left = $right = 0;
        }


        if (!$id && $depth == null && $route && ($cache = wa('shop')->getCache())) {
            $cache_key = waRouting::clearUrl($route);
            $data = $cache->get($cache_key, 'categories');
        }
        if (empty($data)) {
            $sql = "SELECT c.* FROM {$this->table} c";
            if ($id) {
                $where[] = "c.`{$this->left}` >= i:left";
                $where[] = "c.`{$this->right}` <= i:right";
            }
            if ($depth !== null) {
                $depth = max(0, intval($depth));
                if ($id && $parent) {
                    $depth += (int)$parent[$this->depth];
                }
                $where[] = "c.`{$this->depth}` <= i:depth";
            }
            if ($route) {
                $sql .= " LEFT JOIN shop_category_routes cr ON c.id = cr.category_id";
                $where[] = "c.status = 1";
                $where[] = "cr.route IS NULL OR cr.route = '" . $this->escape($route) . "'";
            }
            if ($where) {
                $sql .= " WHERE (" . implode(') AND (', $where) . ')';
            }
            $sql .= " ORDER BY c.`{$this->left}`";

            $data = $this->query($sql, array('left' => $left, 'right' => $right, 'depth' => $depth))->fetchAll($this->id);
            if (!$id && $depth == null && $route && $cache) {
                $cache->set($cache_key, $data, 3600, 'categories');
            }
        }

        if ($escape) {
            foreach ($data as &$item) {
                $item['name'] = htmlspecialchars($item['name']);
            }
            unset($item);
        }
        return $data;
    }

    public function getTotalProductsCount($id = 0, $static_only = true)
    {
        $id = (int) $id;

        $where = array();
        if ($static_only) {
            $where[] = 'type = '.self::TYPE_STATIC;
        }

        $sql = "SELECT SUM(count) AS cnt FROM `{$this->table}` ";
        if ($id) {
            $item = $this->getById($id);
            $where[] = "`{$this->left}` >= {$item[$this->left]}";
            $where[] = "`{$this->right}` <= {$item[$this->right]}";
        }
        if ($where) {
            $sql .= " WHERE ".implode(" AND ", $where);
        }

        return $this->query($sql)->fetchField('cnt');
    }

    /**
     * Get one level descendants
     *
     * @param int|array $category
     * @param bool|string $public_only
     * @return array
     */
    public function getSubcategories($category, $public_only = false)
    {
        if (is_numeric($category)) {
            $category = $this->getById($category);
        }
        $sql = "SELECT c.* FROM `{$this->table}` c";
        $where = "`{$this->left}` > i:left AND `{$this->right}` < i:right AND `{$this->depth}` = i:depth";
        if ($public_only) {
            $where .= " AND status = 1";
            if (is_string($public_only)) {
                $sql .= " LEFT JOIN shop_category_routes cr ON c.id = cr.category_id";
                $where .= " AND (route IS NULL OR route = '".$this->escape($public_only)."')";
            }
        }
        $sql .= ' WHERE '.$where;
        $sql .= " ORDER BY `{$this->left}`";

        return $this->query($sql, array(
                'left' => $category[$this->left],
                'right' => $category[$this->right],
                'depth'=> $category[$this->depth] + 1
        ))->fetchAll($this->id);
    }

    protected function clearCache()
    {
        if ($cache = wa('shop')->getCache()) {
            $cache->deleteGroup('categories');
        }
    }

    /**
     * @param int $id
     * @param int|null $before_id If null than inserted back of the level
     * @param int $parent_id If 0 than root level
     * @return boolean
     */
    public function move($id, $before_id = null, $parent_id = 0)
    {
        $element = $this->getById($id);
        $old_parent_id = $element[$this->parent];
        $parent = $this->getById($parent_id);
        
        if ($parent && $parent['type'] == self::TYPE_DYNAMIC && $element['type'] == self::TYPE_STATIC) {
            return false;
        }
        
        if (!parent::move($id, $parent_id, $before_id)) {
            return false;
        }
        
        // change url taking into account uniqueness of urls in one level
        $element['url'] = $this->suggestUniqueUrl($element['url'], $id, $parent ? $parent[$this->id] : 0);
        $this->updateById($id, array(
            'url' => $element['url']
        ));
        
        if (!$parent && $old_parent_id != 0) {
            
            $this->updateById($id, array('full_url' => $element['url']));
            $this->correctFullUrlOfDescendants($id, $element['url']);
            
        } elseif ($parent && $old_parent_id != $parent['id']) {
            
            $full_url = $this->fullUrl($parent['full_url'], $element['url']);
            $this->updateById($id, array('full_url' => $full_url));
            $this->correctFullUrlOfDescendants($id, $full_url);
            
        }
        $this->clearCache();
        
        return true;
    }

    public function delete($id)
    {
        $id = (int)$id;
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }
        $parent_id = (int)$item['parent_id'];
        
        /**
         * @event category_delete
         */
        wa()->event('category_delete', $item);

        // because all descendants will be thrown one level up
        // it's necessary to ensure uniqueness urls of descendants in new environment (new parent)
        foreach (
            $this->descendants($item, false)->order("`{$this->depth}`, `{$this->left}`")->query()
            as $child)
        {
            $url = $this->suggestUniqueUrl($child['url'], $child['id'], $parent_id);
            if ($url != $child['url']) {
                $this->updateById($child['id'], array(
                    'url' => $url,
                    'full_url' => $this->fullUrl($item['full_url'], $child['url'])
                ));
            }
        }

        // than correct full urls of descendants taking into account full url of new parent
        if (!$parent_id) {
            $this->correctFullUrlOfDescendants($item, '');
        } else {
            $parent = $this->getById($parent_id);
            $this->correctFullUrlOfDescendants($item, $parent['full_url']);
        }
        
        if (!parent::delete($id)) {
            return false;
        }
        
        // delete related info
        $category_params_model = new shopCategoryParamsModel();
        $category_params_model->clear($id);
        $category_products_model = new shopCategoryProductsModel();
        $category_products_model->deleteByField('category_id', $id);
        $category_routes_model = new shopCategoryRoutesModel();
        $category_routes_model->deleteByField('category_id', $id);

        $product_model = new shopProductModel();
        $product_model->correctMainCategory(null, $id);

        shopCategories::clear($id);
        $this->clearCache();

        return true;
        
    }
    
    /**
     * Insert new item to on some level (parent)
     * @param array $data
     * @param int $parent_id If 0 than rool level
     */
    public function add($data, $parent_id = null, $before_id = null)
    {
        if (empty($data)) {
            return false;
        }
        $data['full_url'] = null;

        if (isset($data['url']) && !$data['url']) {
            unset($data['url']);
        }

        if (!isset($data['create_datetime'])) {
            $data['create_datetime'] = date('Y-m-d H:i:s');
        }

        $before_id = null;
        
        if (!$parent_id) {
            $before_id = $this->query(
                "SELECT id FROM `{$this->table}` ORDER BY `{$this->left}` LIMIT 1"
            )->fetchField('id');
            if ($before_id === false) {
                $before_id = null;
            }
            if (!empty($data['url'])) {
                $data['url'] = $this->suggestUniqueUrl($data['url'], null, 0);
                $data['full_url'] = $data['url'];
            }
        } else {
            $parent = $this->getById($parent_id);
            if (empty($parent)) {
                return false;
            }
            $before_id = $this->query("
            SELECT id FROM `{$this->table}` WHERE parent_id = i:parent_id 
            ORDER BY `{$this->left}` LIMIT 1
        ", array('parent_id' => $parent_id)
            )->fetchField('id');
            if ($before_id === false) {
                $before_id = null;
            }
            if (!empty($data['url'])) {
                $data['url'] = $this->suggestUniqueUrl($data['url'], null, $parent_id);
                $data['full_url'] = $this->fullUrl($parent['full_url'], $data['url']);
            }
        }

        $id = parent::add($data, $parent_id, $before_id);
        if (!$id) {
            return false;
        }

        if (empty($data['url'])) {
            $data = array();
            $data['url'] = $this->suggestUniqueUrl("category_$id", $id, $parent_id);
            if (!$parent_id) {
                $data['full_url'] = $data['url'];
            } else {
                $data['full_url'] = $this->fullUrl($parent['full_url'], $data['url']);
            }
            $this->updateById($id, $data);
        }
        $this->clearCache();
        return $id;
    }

    /**
     * Update with cheking uniqueness of url
     * @param int $id
     * @param array $data
     */
    public function update($id, $data)
    {
        if (isset($data['full_url'])) {
            unset($data['full_url']);
        }
        if (isset($data['url'])) {
            $url = $data['url'];
            unset($data['full_url']);
            unset($data['url']);
        }
        $item = $this->getById($id);
        if (isset($url)) {
            if ($this->urlExists($url, $id, $item['parent_id'])) {
                return false;
            }
            if (!$this->updateUrl($id, $url)) {
                return false;
            }
        }
        if (!$this->updateById($id, $data)) {
            return false;
        }
        $this->clearCache();
        return true;
    }

    /**
     * Check if same url exists already for any category in current level (parent_id), excepting this category
     *
     * @param string $url
     * @param int $category_id optional. If set than check urls excepting url of this album
     * @param int $parent_id Check category of one level
     *
     * @return boolean
     */
    public function urlExists($url, $category_id = null, $parent_id = 0)
    {
        $where = "url = s:url AND parent_id = i:parent_id";
        if ($category_id) {
            $where .= " AND id != i:id";
        }
        return !!$this->select('id')->where($where, array(
            'url'       => $url,
            'parent_id' => $parent_id,
            'id'        => $category_id
        ))->fetch();
    }

    /**
     * Suggest unique url by original url.
     * If not exists yet just return without changes, otherwise fit a number suffix and adding it to url.
     * @see urlExists
     *
     * @param string $original_url
     * @param int $category_id Pass to urlExists method
     * @param int $parent_id Pass to urlExists method
     *
     * @return string
     */
    public function suggestUniqueUrl($original_url, $category_id = null, $parent_id = 0)
    {
        $counter = 1;
        $url = $original_url;
        while ($this->urlExists($url, $category_id, $parent_id)) {
            $url = "{$original_url}_{$counter}";
            $counter++;
        }
        return $url;
    }

    public function updateUrl($id, $url)
    {
        $item = $this->getById($id);

        $full_url = $this->fullUrl($item['full_url']);
        $pos = strrpos($full_url, $item['url']);
        if ($pos === false) {
            $full_url = $url;
        } else {
            $full_url = substr($full_url, 0, $pos).$url;
        }

        if ($item['url'] != $url || $item['full_url'] != $full_url) {
            $this->updateById($id, array(
                'url'      => $url,
                'full_url' => $full_url
            ));
            // update full_url of all descendant
            $this->correctFullUrlOfDescendants($item['id'], $full_url);
        }

        return true;
    }

    /**
     *
     * @param int|array $parent parent info or parent ID
     * @param string $full_url new full url
     * @return boolean
     */
    public function correctFullUrlOfDescendants($parent, $full_url)
    {
        if (is_numeric($parent)) {
            $parent = $this->getById($parent);
        }
        if (!$parent) {
            return false;
        }
        $parent_full_url_map = array(
            $parent['id'] => $full_url
        );

        foreach (
            $this->descendants($parent, false)->order("`{$this->depth}`, `{$this->left}`")->query()
            as $item)
        {
            $parent_full_url = $parent_full_url_map[$item[$this->parent]];
            $full_url = $this->fullUrl($parent_full_url, $item['url']);
            $parent_full_url_map[$item['id']] = $full_url;
            $this->updateById($item['id'], array('full_url' => $full_url));
        }
        return true;
    }

    private function fullUrl($prefix, $url = '')
    {
        return trim(rtrim($prefix, '/').'/'.ltrim($url, '/'), '/');
    }

    /**
     *
     * Query for getting descendants
     *
     * @param mixed $parent
     *     int parent ID
     *     array parent info
     *     false|null query for all tree
     * @param boolean $include_parent
     * @return waDbQuery
     */
    public function descendants($parent, $include_parent = false)
    {
        $query = new waDbQuery($this);

        if (is_numeric($parent) && $parent) {
            $parent_id = (int)$parent;
            $parent = $this->getById($parent);
            if (!$parent) {
                return $query->where('id = '.$parent_id);
            }
        }
        $op = !$include_parent ? array('>', '<') : array('>=', '<=');
        if ($parent) {
            $where = "
                `{$this->left}`  {$op[0]} {$parent[$this->left]} AND
                `{$this->right}` {$op[1]} {$parent[$this->right]}
            ";
            $query->where($where);
        }
        return $query;
    }

    public function getFrontendUrls($id)
    {
        $category = $this->getById($id);
        if (!$category) {
            return array();
        }
        $category_routes_model = new shopCategoryRoutesModel();
        $category['routes'] = $category_routes_model->getRoutes($id);

        $frontend_urls = array();

        $routing =  wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $r) {
                if (!empty($r['private'])) {
                    continue;
                }
                if (!$category['routes'] || in_array($domain.'/'.$r['url'], $category['routes'])) {
                    $routing->setRoute($r, $domain);
                    $frontend_urls[] = $routing->getUrl('shop/frontend/category', array(
                        'category_url' => isset($r['url_type']) && ($r['url_type'] == 1) ? $category['url'] : $category['full_url']),
                        true);
                }
            }
        }
        return $frontend_urls;
    }
    
    public function recount($category_id = null)
    {
        $cond = "
            WHERE c.type = ".self::TYPE_STATIC."
            GROUP BY c.id
            HAVING c.count != cnt
        ";
        if ($category_id !== null) {
            $category_ids = array();
            foreach ((array)$category_id as $id) {
                $category_ids[] = $id;
            }
            if (!$category_ids) {
                return;
            }
            $cond = "
                WHERE c.id IN ('".implode("','", $this->escape($category_ids))."') AND c.type = ".self::TYPE_STATIC."
                GROUP BY c.id
            ";
        }
        $sql = "
            UPDATE `{$this->table}` c JOIN (
            SELECT c.id, c.count, count(cp.product_id) cnt
            FROM `{$this->table}` c
            LEFT JOIN `shop_category_products` cp ON cp.category_id = c.id
            $cond
            ) r ON c.id = r.id
            SET c.count = r.cnt
        ";
        return $this->exec($sql);
    }

    protected function repairSubtree(&$subtree, $depth = -1, $key = 0, $full_url_prefix = '')
    {
        $subtree[$this->left] = $key;
        $subtree[$this->depth] = $depth;
        $subtree['full_url'] = trim($full_url_prefix.'/'.$subtree['url'], '/');
        if (!empty($subtree['children'])) {
            foreach ($subtree['children'] as & $node) {
                $key = $this->repairSubtree($node, $depth + 1, $key + 1, $subtree['full_url']);
            }
        }
        $subtree[$this->right] = $key + 1;
        return $key + 1;
    }
}
