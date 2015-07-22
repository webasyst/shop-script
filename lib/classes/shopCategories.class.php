<?php

/**
 *
 * Helper for work with categories in light of collapsed/expanded states
 *
 */
class shopCategories
{
    /**
     * @var waContactSettingsModel
     */
    private static $settings_model;

    /**
     * @var shopCategoryModel
     */
    private static $category_model;

    /**
     * Name prefix for category expand/collapse flag
     *
     * @var string
     */
    private static $prefix = 'expanded_category_';

    /**
     * Not natural mode means that expanded just one item - root category (with # = 0)
     * Otherwise all works in natural manner (if item has proper flag - it's expanded, if not - collapsed)
     *
     * @var string
     */
    private static $natural_mode_key = 'expanded_category_natural_mode';

    /**
     * Subtree root (not necessarily the #0)
     * @var int
     */
    private $root_id;


    private $list;
    private $count;
    private $is_expanded;

    public function __construct($root_id = 0)
    {
        $this->root_id = $root_id;
    }

    public function getList()
    {
        if ($this->list === null) {
            $this->list = $this->_getList($this->root_id);
        }
        return $this->list;
    }

    public function count()
    {
        if ($this->count === null) {
            if ($this->root_id) {
                $this->count = self::getCategoryModel()->countByField('parent_id', $this->root_id);
            } else {
                $this->count = self::getCategoryModel()->countAll();
            }
        }
        return $this->count;
    }

    public function isExpanded()
    {
        if ($this->is_expanded === null) {
            $this->is_expanded = $this->_isExpanded();
        }
        return $this->is_expanded;
    }

    private function _isExpanded()
    {
        $contact_id = wa('shop')->getUser()->getId();
        $settings_model = self::getSettingsModel();

        $natural_mode = false;
        if (!$this->count()) {
            self::clear();
        } else {
            $natural_mode = $settings_model->getOne(
                $contact_id,
                'shop',
                self::$natural_mode_key);
        }

        // not natural mode case
        if (!$natural_mode) {
            return $this->root_id == 0;
        }

        // natural mode case
        return (boolean) $settings_model->getOne(
                $contact_id,
                'shop',
                self::$prefix . $this->root_id
            );
    }

    /**
     * Clear information for category
     * @param int|array|null $category_id
     *     IF 0 then it means root, not every items
     *     If then null (default) clear every items
     *     If array then clear every in array
     */
    public static function clear($category_id = null, $recurse=false)
    {
        $contact_id = wa('shop')->getUser()->getId();

        if ($category_id === null) {
            $category_id = array_keys(self::getCategoryExpandedStatesMap());
            self::getSettingsModel()->delete($contact_id, 'shop', self::$natural_mode_key);
        } else {
            $category_id = (array) $category_id;
            if ($recurse) {
                $ids = array_fill_keys($category_id, 1);
                foreach ($category_id as $id) {
                    foreach(self::getCategoryModel()->getTree($id) as $c) {
                        $ids[$c['id']] = 1;
                    }
                }
                $category_id = array_keys($ids);
            }
        }

        foreach ($category_id as $id) {
            self::getSettingsModel()->delete(
                    $contact_id,
                    'shop',
                    self::$prefix . (int) $id
            );
        }
    }

    /**
     * Get categories by parent ID (if 0 that all categories).
     * Take into account collapsed/expanded states
     * @param int $parent_id
     * @return array
     */
    private function _getList($parent_id = 0)
    {
        // map indexed by category ids indicates that this category need or not
        // to unset for output flow
        $expand = self::getCategoryExpandedStatesMap();
        $unset = array();

        $categories = $this->getTree($parent_id);

        $root = null;
        if ($parent_id) {
            $root = $categories[$parent_id];
        }

        foreach ($categories as $category) {
            if (empty($expand[$category['parent_id']])
                    || !empty($unset[$category['parent_id']]))
            {
                $unset[$category['id']] = true;
            }
        }

        foreach ($categories as $category_id => &$category) {
            $category['expanded'] = !empty($expand[$category_id]);
            if (!empty($unset[$category_id])) {
                unset($categories[$category_id]);
            }
        }
        unset($category);

        if ($root) {

            $depth = $root['depth'];

            // root is not needed
            if (isset($categories[$parent_id])) {
                unset($categories[$parent_id]);
            }

            // shift depth for correct rendering
            foreach ($categories as &$category) {
                $category['depth'] -= $depth + 1;
            }
            unset($category);
        }

        return $categories;
    }

    private function getTree($parent_id = 0)
    {
        $category_model = self::getCategoryModel();
        if ($parent_id) {
            $categories = $category_model->getTree($parent_id);
        } else {
            $categories = $category_model->getFullTree('id, left_key, right_key, parent_id, depth, name, count, type, status, include_sub_categories');
        }

        // children_count is number of children of category
        foreach ($categories as &$item) {
            if (!isset($item['children_count'])) {
                $item['children_count'] = 0;
            }
            if (isset($categories[$item['parent_id']])) {
                $parent = &$categories[$item['parent_id']];
                if (!isset($parent['children_count'])) {
                    $parent['children_count'] = 0;
                }
                ++$parent['children_count'];
                unset($parent);
            }
        }
        unset($item);

        // bind storefronts (routes)
        $category_routes_model = new shopCategoryRoutesModel();
        foreach ($category_routes_model->getRoutes(array_keys($categories), false) as $category_id => $routes) {
            foreach ($routes as &$r) {
                $r = rtrim($r, '/*');
            }
            unset($r);
            $categories[$category_id]['routes'] = $routes;
        }

        // form intermediate utility data structure
        $stack = array();
        $hierarchy = array();
        foreach ($categories as $item) {
            $c = array(
                'id' => $item['id'],
                'total_count' => 0,
                'parent_id' => $item['parent_id'],
                'count' => $item['count'],
                'depth' => $item['depth'],
                'children' => array()
            );

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while($l > 0 && $stack[$l - 1]['depth'] >= $item['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($hierarchy);
                $hierarchy[$i] = $c;
                $stack[] = & $hierarchy[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['children']);
                $stack[$l - 1]['children'][$i] = $c;
                $stack[] = & $stack[$l - 1]['children'][$i];
            }
        }

        $hierarchy = array(
            'id' => 0,
            'count' => 0,
            'total_count' => 0,
            'children' => $hierarchy
        );
        $this->totalCount($hierarchy, $categories);

        return $categories;
    }

    private function totalCount(&$tree, &$plain_list)
    {
        $total = $tree['count'];
        foreach ($tree['children'] as &$node) {
            $total += $this->totalCount($node, $plain_list);
        }
        if (isset($plain_list[$tree['id']])) {
            $plain_list[$tree['id']]['total_count'] = $total;
        }
        return $total ;
    }

    public static function setCollapsed($category_id, $recurse=false)
    {
        self::switchToNaturalMode();
        self::clear($category_id, $recurse);
    }

    public static function setExpanded($category_id, $recurse=false)
    {
        $map = self::getCategoryExpandedStatesMap();
        if (isset($map[0]) && count($map) == 1) {
            self::switchToNaturalMode();
            self::getSettingsModel()->set(
                wa('shop')->getUser()->getId(),
                'shop',
                self::$prefix . '0',
                1
            );
        }

        $category_id = (array) $category_id;
        if ($recurse) {
            $ids = array_fill_keys($category_id, 1);
            foreach ($category_id as $id) {
                foreach(self::getCategoryModel()->getTree($id) as $c) {
                    $ids[$c['id']] = 1;
                }
            }
            $category_id = array_keys($ids);
        }

        foreach($category_id as $c_id) {
            self::getSettingsModel()->set(
                    wa('shop')->getUser()->getId(),
                    'shop',
                    self::$prefix . (int)$c_id,
                    1
            );
        }
    }

    private static function switchToNaturalMode()
    {
        $contact_id = wa('shop')->getUser()->getId();

        // not natural mode case
        if (!self::getSettingsModel()->getOne(
                $contact_id,
                'shop',
                self::$natural_mode_key))
        {
            self::getSettingsModel()->set(
                    $contact_id,
                    'shop',
                    self::$natural_mode_key,
                    1
            );
        }
    }


    /**
     * @return waContactSettingsModel
     */
    private static function getSettingsModel()
    {
        if (!self::$settings_model) {
            self::$settings_model = new waContactSettingsModel();
        }
        return self::$settings_model;
    }

    /**
     * @return shopCategoryModel
     */
    private static function getCategoryModel()
    {
        if (!self::$category_model) {
            self::$category_model = new shopCategoryModel();
        }
        return self::$category_model;
    }

    public static function getCategoryExpandedStatesMap()
    {
        $settings_model = self::getSettingsModel();
        $contact_id = wa('shop')->getUser()->getId();

        $natural_mode = false;
        $settings = array();
        foreach ($settings_model->get($contact_id, 'shop') as $name => $item) {
            $k = strpos($name, self::$prefix);
            if ($k === false) {
                continue;
            }
            if ($name == self::$natural_mode_key) {
                $natural_mode = true;
                continue;
            }
            $category_id = str_replace(self::$prefix, '', $name);
            $settings[$category_id] = true;
        }

        if (!$natural_mode) {
            $settings = array(0 => true);
        }

        return $settings;
    }
}
