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
            $name = wa()->getConfig()->getGeneralSettings('name');
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

    public function addConfig($name = '', $description = '')
    {
        if (empty($name)) {
            $name = wa()->getConfig()->getGeneralSettings('name');
            if (!$name) {
                $name = date('c');
            }
        }
        $data = array(
            'plugin'      => $this->plugin,
            'name'        => $name,
            'description' => $description,
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
     * @return array
     */
    public static function getCollectionHash()
    {
        $hash = waRequest::post('hash');
        $info = array(
            'type' => $hash,
        );
        switch ($hash) {
            case 'id':
                $hash = 'id/'.waRequest::post('product_ids');
                break;
            case 'set':
                $hash = 'set/'.waRequest::post('set_id', waRequest::TYPE_STRING_TRIM);
                break;
            case 'type':
                $hash = 'type/'.waRequest::post('type_id', waRequest::TYPE_INT);
                break;
            case 'category':
                $hash = 'category/'.waRequest::post('category_ids', waRequest::TYPE_INT);
                break;
            default:
                $hash = '*';
                break;
        }
        $info['hash'] = $hash;
        return $info;
    }

    public static function parseHash($hash)
    {
        $info = array(
            'type'    => '',
            'set_id'  => '',
            'type_id' => ''
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