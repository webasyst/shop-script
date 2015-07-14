<?php

class shopProductsSaveListSettingsController extends waJsonController
{
    private $model;

    public function execute()
    {
        $hash = $this->getHash();        // hash that identify 'destination' list (settings of which is changing)
        if (!$hash) {
            throw new waException("Unknown type of list");
        }

        if (waRequest::get('edit', null, waRequest::TYPE_STRING_TRIM) === 'name') {
            $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
            $this->getModel($hash[0])->updateById($hash[1], array(
                'name' => $name
            ));
            $this->response = array(
                'id' => $hash[1], 'name' => htmlspecialchars($name)
            );
            return;
        }

        $data = $this->getData($hash[0]);
        $id = $this->saveSettings($hash, $data);

        if ($id) {

            $this->response = $this->getModel($hash[0])->getById($id);
            $this->response['name'] = htmlspecialchars($this->response['name'], ENT_NOQUOTES);

            // when use iframe-transport unescaped content bring errors when parseJSON
            if (!empty($this->response['description'])) {
                $this->response['description'] = htmlspecialchars($this->response['description'], ENT_NOQUOTES);
            }

            if ($hash[0] == 'category') {
                // bind storefronts (routes)
                $category_routes_model = new shopCategoryRoutesModel();
                $routes = $category_routes_model->getRoutes($id);
                foreach ($routes as &$r) {
                    if (substr($r, -1) === '*') {
                        $r = substr($r, 0, -1);
                    }
                    if (substr($r, -1) === '/') {
                        $r = substr($r, 0, -1);
                    }
                }
                unset($r);
                $this->response['routes'] = $routes;
            }
        }
    }

    private function saveSettings($hash, &$data)
    {
        if ($hash[0] == 'category') {
            if (isset($data['id'])) {
                unset($data['id']);
            }
            return $this->saveCategorySettings((int)$hash[1], $data);
        }
        if ($hash[0] == 'set') {
            return $this->saveSetSettings($hash[1], $data);
        }
    }

    private function saveCategorySettings($id, &$data)
    {
        /**
         * @var shopCategoryModel
         */
        $model = $this->getModel('category');
        if (!$id) {
            if (empty($data['url'])) {
                $url = shopHelper::transliterate($data['name'], false);
                if ($url) {
                    $data['url'] = $model->suggestUniqueUrl($url);
                }
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $id = $model->add($data, $data['parent_id']);
            $this->logAction('category_add', $id);
        } else {
            $category = $model->getById($id);
            if (!$this->categorySettingsValidate($category, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = $category['name'];
            }
            if (empty($data['url'])) {
                $data['url'] = $model->suggestUniqueUrl(shopHelper::transliterate($data['name']), $id, $category['parent_id']);
            }
            unset($data['parent_id']);
            $data['edit_datetime'] = date('Y-m-d H:i:s');
            $model->update($id, $data);
            $this->logAction('category_edit', $id);
        }
        if ($id) {
            if (waRequest::post('enable_sorting')) {
                $data['params']['enable_sorting'] = 1;
            }
            $category_params_model = new shopCategoryParamsModel();
            $category_params_model->set($id, !empty($data['params']) ? $data['params'] : null);

            $category_routes_model = new shopCategoryRoutesModel();
            $category_routes_model->setRoutes($id, isset($data['routes']) ? $data['routes'] : array());

            $category_og_model = new shopCategoryOgModel();
            $category_og_model->set($id, ifempty($data['og'], array()));

            $data['id'] = $id;
            /**
             * @event category_save
             * @param array $category
             * @return void
             */
            wa()->event('category_save', $data);
        }
        return $id;

    }

    private function categorySettingsValidate($category, $data)
    {
        if (!empty($data['url'])) {
            if ($this->getModel('category')->urlExists($data['url'], $category['id'], $category['parent_id'])) {
                $this->errors['url'] = _w('Url is in use');
            }
        }
        return empty($this->errors);
    }

    private function saveSetSettings($id, &$data)
    {
        if (empty($data['count']) || $data['count'] < 0) {
            $data['count'] = 0;
        }

        /**
         * @var shopSetModel $model
         */
        $model = $this->getModel('set');
        if (!$id) {
            if (empty($data['id'])) {
                $id = shopHelper::transliterate($data['name']);
                $data['id'] = $model->suggestUniqueId($id);
            } else {
                $data['id'] = $model->suggestUniqueId($data['id']);
            }
            if (!$this->setSettingsValidate(null, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $id = $model->add($data);
        } else {
            $set = $model->getById($id);
            if (!$this->setSettingsValidate($set, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = $set['name'];
            }
            if (!empty($data['id'])) {
                $id = $data['id'];
            } else {
                $id = shopHelper::transliterate($data['name']);
                if ($id != $set['id']) {
                    $id = $model->suggestUniqueId($id);
                }
                $data['id'] = $id;
            }
            $data['edit_datetime'] = date('Y-m-d H:i:s');
            $model->update($set['id'], $data);
        }
        if ($id) {
            $data['id'] = $data;
            /**
             * @event set_save
             * @param array $set
             * @return void
             */
            wa()->event('set_save', $data);
        }
        return $id;
    }

    private function setSettingsValidate($set = null, $data)
    {
        if (!preg_match("/^[a-z0-9\._-]+$/i", $data['id'])) {
            $this->errors['id'] = _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed');
        }
        if ($set) {
            if (!empty($data['id']) && $set['id'] != $data['id']) {
                if ($this->getModel('set')->idExists($data['id'])) {
                    $this->errors['id'] = _w('ID is in use');
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * @param $type
     * @return waModel
     */
    private function getModel($type)
    {
        static $model = array();
        if (!isset($model[$type])) {
            if ($type == 'category') {
                $model[$type] = new shopCategoryModel();
            } elseif ($type == 'set') {
                $model[$type] = new shopSetModel();
            } else {
                $model[$type] = null;
            }
        }
        return $model[$type];
    }

    private function getProductsModel($type)
    {
        static $model = array();
        if (!isset($model[$type])) {
            if ($type == 'category') {
                $model[$type] = new shopCategoryProductsModel();
            } elseif ($type == 'set') {
                $model[$type] = new shopSetProductsModel();
            } else {
                $model[$type] = null;
            }
        }
        return $model[$type];
    }

    /*
    private function addProducts($id, $type)
    {
        $model = $this->getProductsModel($type);
        $hash = waRequest::post('hash');    // hash of source list (which provides products)
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            $model->add($product_ids, $id);
        } else {
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                $model->add($product_ids, $id);
                $offset += count($product_ids);
            }
        }
    }
    */

    private function getHash()
    {
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        if ($category_id !== null) {
            return array('category', $category_id);
        }
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);
        if ($set_id !== null) {
            return array('set', $set_id);
        }
        return null;
    }

    private function getData($list_type)
    {
        $type = waRequest::post('type', 0, waRequest::TYPE_INT);
        $data = array(
            'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'url' =>  waRequest::post('url', '', waRequest::TYPE_STRING_TRIM),
            'description' => waRequest::post('description', '', waRequest::TYPE_STRING_TRIM),
            'meta_title' => waRequest::post('meta_title', '', waRequest::TYPE_STRING_TRIM),
            'meta_keywords' => waRequest::post('meta_keywords', '', waRequest::TYPE_STRING_TRIM),
            'meta_description' => waRequest::post('meta_description', '', waRequest::TYPE_STRING_TRIM),
            'params' => waRequest::post('params', '', waRequest::TYPE_STRING_TRIM),
            'parent_id' => waRequest::get('parent_id', 0, waRequest::TYPE_INT),
            'type' => $type,
            'status' => waRequest::post('hidden', 0) ? 0 : 1,
            'routes' => waRequest::post('routes', array())
        );
        $params = array();
        if (!empty($data['params'])) {
            foreach (explode("\n", $data['params']) as $param_str) {
                $param = explode('=', $param_str);
                if (count($param) > 1) {
                    $params[$param[0]] = trim($param[1]);
                }
            }
        }
        $data['params'] = $params;

        if ($list_type == 'category') {
            $data['filter'] = $this->getFilter();
            if ($type == shopCategoryModel::TYPE_DYNAMIC) {
               $data['conditions'] = $this->getConditions();
            }
            $data['sort_products'] = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);
            $data['sort_products'] = !empty($data['sort_products']) ? $data['sort_products'] : null;

            $data['include_sub_categories'] = waRequest::post('include_sub_categories', 0, waRequest::TYPE_INT);
            $data['og'] = waRequest::post('og', array(), waRequest::TYPE_ARRAY);
        }

        if ($list_type == 'set') {
            $data['id'] = waRequest::post('id', null, waRequest::TYPE_STRING_TRIM);
            if ($type == shopSetModel::TYPE_DYNAMIC) {
                $data['rule']  = waRequest::post('rule', null, waRequest::TYPE_STRING);
                $data['rule'] = !empty($data['rule']) ? $data['rule'] : null;
                $data['count'] = waRequest::post('count', 100, waRequest::TYPE_INT);
            }
        }
        return $data;
    }

    private function getFilter()
    {
        $filter = array();
        if (waRequest::post('allow_filter')) {
            foreach (waRequest::post('filter', array()) as $value) {
                $filter[] = trim($value);
            }
        }
        return !empty($filter) ? implode(',', $filter) : null;
    }

    private function getConditions()
    {
        $raw_condition = waRequest::post('condition');

        $conditions = array();
        if (isset($raw_condition['rating'])) {
            $raw_condition['rating'] = waRequest::post('rating');
            $conditions[] = 'rating' . $raw_condition['rating'][0] . $raw_condition['rating'][1];
        }
        if (isset($raw_condition['tag'])) {
            $tags = (array)waRequest::post('tag', array());
            if (!empty($tags)) {
                $conditions[] = 'tag=' . implode('||', $tags);
            }
        }
        if (isset($raw_condition['price'])) {
            $raw_condition['price'] = waRequest::post('price');
            if (!empty($raw_condition['price'][0])) {
                $conditions[] = 'price>=' . $raw_condition['price'][0];
            }
            if (!empty($raw_condition['price'][1])) {
                $conditions[] = 'price<=' . $raw_condition['price'][1];
            }
        }

        if (isset($raw_condition['feature'])) {
            $feature_values = waRequest::post('feature_values');
            foreach ($raw_condition['feature'] as $f_code) {
                $conditions[] = $f_code.'.value_id='.$feature_values[$f_code];
            }
        }

        if (isset($raw_condition['count'])) {
            $raw_condition['count'] = waRequest::post('count');
            $conditions[] = 'count' . $raw_condition['count'][0] . $raw_condition['count'][1];
        }

        if (isset($raw_condition['compare_price'])) {
            $conditions[] = 'compare_price>0';
        }

        if ($custom_conditions = waRequest::post('custom_conditions')) {
            $conditions[] = $custom_conditions;
        }

        $conditions = implode('&', $conditions);
        return $conditions;
    }
}
