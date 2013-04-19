<?php
/**
 *
 * @author WebAsyst Team
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


    public function getAll($key = null, $normalize = false)
    {
        $this->setCache(new waRuntimeCache('shop/categories/all'));
        return parent::getAll($key, $normalize);
    }

//     /**
//      * @return array
//      */
//     public function getFullTree($static_only = false, $espace = false)
//     {
//         $where = $static_only ? 'WHERE type='.self::TYPE_STATIC : '';
//         $sql = "SELECT * FROM {$this->table} $where ORDER BY {$this->left}";
//         $tree = $this->query($sql)->fetchAll('id');
//         foreach ($tree as &$item) {
//             if (!isset($item['children_count'])) {
//                 $item['children_count'] = 0;
//             }
//             if (isset($tree[$item['parent_id']])) {
//                 $parent = &$tree[$item['parent_id']];
//                 if (!isset($parent['children_count'])) {
//                     $parent['children_count'] = 0;
//                 }
//                 ++$parent['children_count'];
//                 unset($parent);
//             }
//             if ($espace) {
//                 $item['name'] = htmlspecialchars($item['name']);
//             }
//         }
//         return $tree;
//     }

    /**
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
     * get subtree
     *
     * @param $id int
     * @param $depth int related depth default is unlimited
     */
    public function getTree($id, $depth = null, $escape = false, $where = array())
    {
        $data = parent::getTree($id, $depth, $where);
        if ($escape) {
            foreach ($data as &$item) {
                $item['name'] = htmlspecialchars($item['name']);
            }
            unset($item);
        }
        return $data;
    }

    /**
     * Get one level descendants
     * @param  int|array $category
     * @return array|boolean
     */
    public function getSubcategories($category, $public_only = false)
    {
        if (is_numeric($category)) {
            $category = $this->getById($category);
        }
        $sql = "SELECT * FROM `{$this->table}`
                WHERE `{$this->left}` > i:left AND `{$this->right}` < i:right AND `{$this->depth}` = i:depth";
        if ($public_only) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY `{$this->left}`";

        return $this->query($sql, array(
                'left' => $category[$this->left],
                'right' => $category[$this->right],
                'depth'=> $category[$this->depth] + 1
        ))->fetchAll($this->id);
    }

    /**
     * @param int $id
     * @param int|null $before_id If null than inserted back of the level
     * @param int $parent_id If 0 than root level
     * @return boolean
     */
    public function move($id, $before_id = null, $parent_id = 0)
    {
        if (!$this->_move($id, $parent_id)) {
            return false;
        }
        // we have element changed, so get updated element from db
        $element = $this->getById($id);

        $width = $element[$this->right] - $element[$this->left] + 1;

        if ($before_id) {
            $before = $this->getById($before_id);
            if (empty($before)) {
                return false;
            }
            if ($element[$this->parent] != $before[$this->parent]) {
                return true; // move but without sort
            }
            if ($element[$this->right] == $before[$this->left] + 1) {
                return true; // already sorted how we need
            }
            $params = array(
                'left'      => $element[$this->left],
                'right'     => $element[$this->right],
                'width'     => $element[$this->left] > $before[$this->left] ? $width : -$width,
                'step'      => $before[$this->left] - $element[$this->left],
                'from_left' => $before[$this->left]
            );

            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->left}` = `{$this->left}` + IF(`{$this->left}` BETWEEN i:left AND i:right, i:step, i:width)
                WHERE `{$this->left}` >= i:from_left AND `{$this->left}` < i:right
            ", $params);
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->right}` = `{$this->right}` + IF(`{$this->right}` BETWEEN i:left AND i:right, i:step, i:width)
                WHERE `{$this->right}` > i:from_left AND `{$this->right}` <= i:right
            ", $params);
        }
        return true;
    }

    /**
     *
     * @param int $id
     * @param $parent_id
     */
    private function _move($id, $parent_id)
    {
        $element = $this->getById($id);
        $old_parent_id = $element[$this->parent];
        $parent = $this->getById($parent_id);
        $left = $element[$this->left];
        $right = $element[$this->right];

        if ($parent) {
            if ($parent[$this->left] > $left && $parent[$this->right] < $right) {
                return false;
            }
            if ($parent['type'] == self::TYPE_DYNAMIC && $element['type'] == self::TYPE_STATIC) {
                return false;
            }
        }

        // change url taking into account uniqueness of urls in one level
        $element['url'] = $this->suggestUniqueUrl($element['url'], $id, $parent ? $parent[$this->id] : 0);
        $this->updateById($id, array(
            $this->parent  => $parent ? $parent[$this->id] : 0,
            'url' => $element['url']
        ));
        $this->exec("
            UPDATE `{$this->table}`
            SET `{$this->depth}` = `{$this->depth}` + i:parent_depth - i:depth + 1
            WHERE
            `{$this->left}` BETWEEN i:left AND i:right
            ", array('left' => $left, 'right' => $right, 'parent_depth' => $parent ? $parent[$this->depth] : -1, 'depth' => $element[$this->depth]));

        $params = array(
            'left'  => $left,
            'right' => $right,
            'width' => $right - $left + 1
        );

        if (!$parent) { // move element to root level
            $params['step'] = $this->query("SELECT MAX($this->right) max FROM {$this->table}")->fetchField('max') - $right;

            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->left}` = `{$this->left}` + IF(`{$this->left}` BETWEEN i:left AND i:right, i:step, -i:width)
                WHERE `{$this->left}` >= i:left
                ", $params);
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->right}` = `{$this->right}` + IF(`{$this->right}` BETWEEN i:left AND i:right, i:step, -i:width)
                WHERE `{$this->right}` >= i:left
            ", $params);

            // correct full_url of this item and all of new descendants if parent has changed
            if ($old_parent_id != 0) {
                $this->updateById($id, array('full_url' => $element['url']));
                $this->correctFullUrlOfDescendants($id, $element['url']);
            }
            return true;
        }

        $parent_left = $parent[$this->left];
        $parent_right = $parent[$this->right];
        $params['parent_left'] = $parent_left;
        $params['parent_right'] = $parent_right;

        // right
        if ($right > $parent_right) {
            $params['step'] = $parent_right - $left;
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->left}` = `{$this->left}` + IF(`{$this->left}` BETWEEN i:left AND i:right, i:step, i:width)
                WHERE
                `{$this->left}` >= i:parent_right AND `{$this->left}` <= i:right
            ", $params);
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->right}` = `{$this->right}` + IF(`{$this->right}` BETWEEN i:left AND i:right, i:step, i:width)
                WHERE
                `{$this->right}` >= i:parent_right AND `{$this->right}` <= i:right
            ", $params);
        } // left
        else {
            $params['step'] = $parent_right - $right - 1;
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->left}` = `{$this->left}` + IF(`{$this->left}` BETWEEN i:left AND i:right, i:step, -i:width)
                WHERE
                `{$this->left}` >= i:left AND `{$this->left}` < i:parent_right
            ", $params);
            $this->exec("
                UPDATE `{$this->table}`
                SET `{$this->right}` = `{$this->right}` + IF(`{$this->right}` BETWEEN i:left AND i:right, i:step, -i:width)
                WHERE
                `{$this->right}` >= i:left AND `{$this->right}` < i:parent_right
            ", $params);
        }

        if ($old_parent_id != $parent['id']) {
            $full_url = $this->fullUrl($parent['full_url'], $element['url']);
            $this->updateById($id, array('full_url' => $full_url));
            $this->correctFullUrlOfDescendants($id, $full_url);
        }

        return true;
    }

    /**
     * Delete category with taking into account plenty of aspects
     * @param int
     */
    public function delete($id)
    {
        $id = (int)$id;
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }
        $left = (int)$item[$this->left];
        $right = (int)$item[$this->right];
        $parent_id = (int)$item[$this->parent];

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

        $this->deleteById($id);

        // update all descendants (all keys -1, level up)
        $this->exec("UPDATE `{$this->table}`
            SET
              `{$this->right}`  = `{$this->right}` - 1,
              `{$this->left}`   = `{$this->left}`  - 1,
              `{$this->depth}`  = `{$this->depth}` - 1
            WHERE `{$this->left}` > $left AND `{$this->right}` < $right");

        // update childrens (change parent)
        $this->exec("UPDATE `{$this->table}`
            SET
              `{$this->parent}` = {$parent_id}
            WHERE `{$this->parent}` = $id");

        // update left branch (exclude descendants) (all keys -2)
        $this->exec("UPDATE `{$this->table}`
            SET
              `{$this->right}` = `{$this->right}` - 2,
              `{$this->left}`  = `{$this->left}`  - 2
            WHERE `{$this->left}` > $left AND `{$this->right}` > $right");

        // update parent branch (right keys - 2)
        $this->query("UPDATE `{$this->table}`
            SET
              `{$this->right}` = `{$this->right}` - 2
            WHERE `{$this->right}` > $right AND `{$this->left}` < $left");

        // delete related info
        $category_params_model = new shopCategoryParamsModel();
        $category_params_model->clear($id);
        $category_products_model = new shopCategoryProductsModel();
        $category_products_model->deleteByField('category_id', $id);

        $product_model = new shopProductModel();
        $product_model->correctMainCategory(null, $id);

        return true;
    }

    /**
     * Insert new item to on some level (parent)
     * @param array $data
     * @param int $parent_id If 0 than rool level
     */
    public function add($data, $parent_id = 0)
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

        if (!$parent_id) {
            $this->query("UPDATE `{$this->table}` SET
                `{$this->right}` = `{$this->right}` + 2,
                `{$this->left}`  = `{$this->left}`  + 2
            ");
            $data[$this->left] = 1;
            $data[$this->right] = 2;
            $data[$this->parent] = 0;
            $data[$this->depth] = 0;
            if (!empty($data['url'])) {
                $data['full_url'] = $data['url'];
            }
        } else {
            $parent = $this->getById($parent_id);
            if (empty($parent)) {
                return false;
            }
            $this->query("UPDATE `{$this->table}` SET `{$this->left}`  = `{$this->left}`  + 2
                WHERE `{$this->left}` > {$parent[$this->left]}
            ");
            $this->query("UPDATE `{$this->table}` SET `{$this->right}`  = `{$this->right}`  + 2
                WHERE `{$this->right}` > {$parent[$this->left]}
            ");
            $data[$this->left] = $parent[$this->left] + 1;
            $data[$this->right] = $parent[$this->left] + 2;
            $data[$this->parent] = $parent_id;
            $data[$this->depth] = $parent[$this->depth] + 1;
            if (!empty($data['url'])) {
                $data['url'] = $this->suggestUniqueUrl($data['url'], null, $parent_id);
                $data['full_url'] = $this->fullUrl($parent['full_url'], $data['url']);
            }
        }

        $id = $this->insert($data);
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
        if (!$this->updateById($id, $data)) {
            return false;
        }
        if (isset($url)) {
            if ($this->urlExists($url, $id, $item['parent_id'])) {
                return false;
            }
            if (!$this->updateUrl($id, $url)) {
                return false;
            }
        }
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

    private function fullUrl($prefix, $url = '')
    {
        return trim(rtrim($prefix, '/').'/'.ltrim($url, '/'), '/');
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

    /**
     * Repair "broken" nested-set tree. "Broken" is when keys combination is illegal and(or) full_urls is incorrect
     */
    public function repair()
    {
        $tree = array(0 => array('children' => array(), 'url' => ''));
        $parent_ids = array(0);
        $result = true;
        $access_table = array(0 => & $tree[0]);
        while ($parent_ids) {
            $result = $this->query("SELECT * FROM {$this->table} WHERE parent_id IN (".implode(',', $parent_ids).") ORDER BY left_key");
            $parent_ids = array();
            foreach ($result as $item) {
                $parent_id = $item['parent_id'];
                $item['children'] = array();
                $access_table[$parent_id]['children'][$item['id']] = $item;
                $access_table[$item['id']] = &$access_table[$parent_id]['children'][$item['id']];
                $parent_ids[] = $item['id'];
            }
        }
        $this->_repair($access_table[0]);

        foreach ($access_table as $item) {
            if (isset($item['id'])) {
                $this->updateById($item['id'], array(
                    'name'      => $item['name'],
                    'depth'     => $item['depth'],
                    'left_key'  => $item['left_key'],
                    'right_key' => $item['right_key'],
                    'full_url'  => $item['full_url']
                ));
            }
        }
    }

    public function _repair(&$subtree, $depth = -1, $key = 0, $full_url_prefix = '')
    {
        $subtree['left_key'] = $key;
        $subtree['depth'] = $depth;
        $subtree['full_url'] = trim($full_url_prefix.'/'.$subtree['url'], '/');
        if (!empty($subtree['children'])) {
            foreach ($subtree['children'] as & $node) {
                $key = $this->_repair($node, $depth + 1, $key + 1, $subtree['full_url']);
            }
        }
        $subtree['right_key'] = $key + 1;
        return $key + 1;
    }
}
