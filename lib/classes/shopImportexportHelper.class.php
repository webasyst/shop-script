<?php

class shopImportexportHelper
{

    /**
     * @var shopImportexportModel
     */
    private $model;
    /**
     * @var string
     */
    private $plugin;

    /**
     * @var array
     */
    private $collection;

    private $name;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->model = new shopImportexportModel();
    }

    /**
     * @param $id
     * @return array
     */
    public function getConfig($id = null)
    {
        $id = $this->getId($id);
        $config = $this->model->getByField(array(
            'id'     => $id,
            'plugin' => $this->plugin,
        ));

        $config['config'] = json_decode($config['config'], true);
        if (!is_array($config['config'])) {
            $config['config'] = array();
        }
        return $config;
    }

    private function getId($id = null)
    {
        if (!$id) {
            if ($raw = waRequest::request('profile')) {
                if (is_array($raw)) {
                    $id = intval(ifset($raw['id']));
                    if (!empty($raw['name'])) {
                        $this->name = trim($raw['name']);
                    }
                } else {
                    $id = intval($raw);
                }
            } elseif (!empty($this->collection)) {
                reset($this->collection);
                $id = key($this->collection);
            }
        }
        return $id;
    }

    public function setConfig($config = array(), $id = null)
    {
        $id = $this->getId($id);
        if ($id <= 0) {
            $name = wa('shop')->getConfig()->getGeneralSettings('name');
            if (!$name) {
                $name = date('c');
            }
            $description = '';
            if (($raw = waRequest::request('profile')) && (is_array($raw))) {
                if (!empty($raw['name'])) {
                    $name = $raw['name'];
                }
                if (!empty($raw['description'])) {
                    $description = $raw['description'];
                }

            }
            $id = $this->addConfig($name, $description);
        }
        $fields = array(
            'id'     => $id,
            'plugin' => $this->plugin,
        );
        $data = array(
            'config' => json_encode($config),
        );
        if (!empty($this->name)) {
            $data['name'] = $this->name;
        }
        $this->model->updateByField($fields, $data);
        return $id;
    }

    public function addConfig($name = '', $description = '', $config = array())
    {
        if (empty($name)) {
            $name = wa('shop')->getConfig()->getGeneralSettings('name');
            if (!$name) {
                $name = date('c');
            }
        }
        $data = array(
            'plugin'      => $this->plugin,
            'name'        => $name,
            'description' => $description,
            'config'      => json_encode($config),
        );

        return $this->model->insert($data);
    }

    public function deleteConfig($id = null)
    {
        $fields = array(
            'plugin' => $this->plugin,
        );
        if ($id !== null) {
            $fields['id'] = $id;
        }
        return $this->model->deleteByField($fields);
    }

    public function getList()
    {
        if (!isset($this->collection)) {
            $profiles = $this->model->getProfiles($this->plugin);
            $this->collection = $profiles[$this->plugin];
        }
        return $this->collection;
    }

    /**
     * @param string $hash
     * @return string[]
     */
    public static function getCollectionHash($hash = null)
    {
        if ($hash === null) {
            $hash = waRequest::post('hash', waRequest::TYPE_STRING_TRIM, '');
        }
        $raw = explode('/', $hash);

        $info = array(
            'type' => array_shift($raw),
            'name' => wa('shop')->getConfig()->getGeneralSettings('name'),

        );
        if (!$info['name']) {
            $info['name'] = date('c');
        }
        $tail = implode('/', $raw);
        switch ($info['type']) {
            case 'id':
                $hash = 'id/'.waRequest::post('product_ids', $tail, waRequest::TYPE_STRING_TRIM);
                break;
            case 'set':
                $set_id = waRequest::post('set_id', $tail, waRequest::TYPE_STRING_TRIM);
                $hash = 'set/'.$set_id;
                $model = new shopSetModel();
                if ($set = $model->getById($set_id)) {
                    $info['name'] .= ' - '.$set['name'];
                }
                break;
            case 'type':
                $type_id = waRequest::post('type_id', $tail, waRequest::TYPE_INT);
                $hash = 'type/'.$type_id;
                $model = new shopTypeModel();
                if ($type = $model->getById($type_id)) {
                    $info['name'] .= ' - '.$type['name'];
                }
                break;
            case 'category':
                $category_id = waRequest::post('category_ids', $tail, waRequest::TYPE_INT);
                $model = new shopCategoryModel();
                if ($category = $model->getById($category_id)) {
                    $info['name'] .= ' - '.$category['name'];
                }
                $hash = 'category/'.$category_id;
                break;
            case 'tag':
            case 'search':
                $collection = new shopProductsCollection($info['type'].'/'.urldecode($tail));
                $products = $collection->getProducts('id,name,url');
                $info['type'] = 'id';
                $hash = 'id/'.implode(',', array_keys($products));
                break;
            default:
                $hash = '*';
                break;
        }
        $info['hash'] = $hash;
        return $info;
    }

    public static function parseHash($hash, $params = array())
    {
        $info = array(
            'type'    => '',
            'set_id'  => '',
            'type_id' => '',
            'data'    => array(),
        );
        if (strpos($hash, '/')) {
            list($info['type'], $hash) = explode('/', $hash, 2);
        }
        switch ($info['type']) {
            case 'id':
                $ids = array_unique(array_map('intval', explode(',', $hash)));
                sort($ids);
                $info['count'] = count($ids);
                $info['product_ids'] = implode(',', $ids);
                break;
            case 'set':
                $info['set_id'] = trim($hash);
                break;
            case 'type':
                $info['type_id'] = intval($hash);
                break;
            case 'category':
                $ids = array_unique(array_map('intval', explode(',', $hash)));
                sort($ids);
                $info['count'] = count($ids);
                $info['category_ids'] = implode(',', $ids);
                break;
            default:
                $info['hash'] = '';
                break;
        }
        if (!empty($params['categories']) || true) {
            $model = new shopCategoryModel();
            $categories = $model->getTree(null, 0, true);
            foreach ($categories as $id => $category) {
                if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                    unset($categories[$id]);
                }
            }

            $map = array();
            foreach ($categories as &$category) {
                $map[$category['id']] = &$category;
            }
            unset($category);

            $category_id = explode(',', ifset($info['category_ids'], ''));
            foreach ($category_id as $id) {
                if (isset($map[$id])) {
                    $map[$id]['selected'] = 'selected';
                }
            }
            $category_id = array_diff($category_id, array_keys($categories));
            foreach ($model->getById($category_id) as $category) {
                $category['name'] = htmlspecialchars($category['name'], ENT_NOQUOTES, 'utf-8');
                $map[$category['id']] = &$category;
                if (isset($map[$category['parent_id']])) {
                    if (!isset($map['childs'])) {
                        $map['childs'] = array();
                    }

                    $map[$category['parent_id']]['childs'][$category['id']] = &$category;
                } else {
                    $path = $model->getPath($category['id']);
                    while ($path_category = array_shift($path)) {
                        $path_category['childs'][$category['id']] = &$category;
                        unset($category);
                        $category = $path_category;
                        $map[$category['id']] = &$category;

                        if (isset($map[$category['parent_id']])) {
                            $map[$category['parent_id']]['childs'][$category['id']] = &$category;
                            unset($category);
                            break;
                        }

                    }
                }
            }
            foreach ($category_id as $id) {
                if (isset($map[$id])) {
                    $map[$id]['selected'] = 'selected';
                }
            }

            $info['data']['categories'] = $categories;
        }
        return $info;
    }

    public function save()
    {
        $raw_profile = waRequest::post('profile');
        if (is_array($raw_profile)) {
            $profile_id = isset($raw_profile['id']) ? intval(intval($raw_profile['id'])) : 0;
        } else {
            $profile_id = intval($raw_profile);
            $raw_profile = array();
        }
        if ($profile_id) {
            $profiles = new shopImportexportHelper($this->plugin);
            if ($profile_id < 0) {
                $profile_id = $profiles->addConfig(ifset($raw_profile['name'], date('c')), ifset($raw_profile['description'], ''));
            }
            $profiles->setConfig($raw_profile, $profile_id);
        }
    }
}
