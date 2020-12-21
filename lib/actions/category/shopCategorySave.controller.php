<?php

class shopCategorySaveController extends waJsonController
{
    /** @var shopCategoryModel */
    protected $model = null;

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->model = new shopCategoryModel();

        //Only update name
        if (waRequest::get('edit', null, waRequest::TYPE_STRING_TRIM) === 'name') {
            $this->saveCategoryName();
            return null;
        }

        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        $data = $this->getData();

        $parent = $this->model->getById($data['parent_id']);
        if ($parent && $parent['type'] == shopCategoryModel::TYPE_DYNAMIC && $data['type'] == shopCategoryModel::TYPE_STATIC) {
            throw new waException('You cannot create a static category in a dynamic category.');
        }

        $id = $this->saveSettings($category_id, $data);

        if ($id) {
            $this->response = $this->model->getById($id);
            $this->response['subcategories_updated'] = $data['update_subcategories'];
            $this->response['name'] = htmlspecialchars($this->response['name'], ENT_NOQUOTES);

            // when use iframe-transport unescaped content bring errors when parseJSON
            if (!empty($this->response['description'])) {
                $this->response['description'] = htmlspecialchars($this->response['description'], ENT_NOQUOTES);
            }

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

    private function saveSettings($category_id, &$data)
    {
        if (isset($data['id'])) {
            unset($data['id']);
        }
        return $this->saveCategorySettings($category_id, $data);
    }

    private function saveCategoryName()
    {
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);

        $this->model->updateById($category_id, array(
            'name' => $name
        ));
        $this->response = array(
            'id'   => $category_id,
            'name' => htmlspecialchars($name)
        );
    }

    private function saveCategorySettings($category_id, &$data)
    {
        /**
         * @var shopCategoryModel
         */
        if (!$category_id) {
            if (empty($data['url'])) {
                $url = shopHelper::transliterate($data['name'], false);
                if ($url) {
                    $data['url'] = $this->model->suggestUniqueUrl($url);
                }
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $response = $this->model->add($data, $data['parent_id']);
            if (is_array($response)) {
                $this->errors += $response;
            } else {
                $category_id = $response;
            }
            $this->logAction('category_add', $category_id);
        } else {
            $category = $this->model->getById($category_id);
            if (!$this->categorySettingsValidate($category, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = $category['name'];
            }
            if (empty($data['url'])) {
                $data['url'] = $this->model->suggestUniqueUrl(shopHelper::transliterate($data['name']), $category_id, $category['parent_id']);
            }
            unset($data['parent_id']);
            $data['edit_datetime'] = date('Y-m-d H:i:s');
            $this->model->update($category_id, $data);
            $this->logAction('category_edit', $category_id);
        }
        if ($category_id) {
            if ($data['update_subcategories']) {
                $tree = $this->model->getTree($category_id);
                $category_ids = array_keys($tree);
                $this->model->updateByField('parent_id', $category_ids, array('status' => $data['status']));
            }

            if (waRequest::post('enable_sorting')) {
                $data['params']['enable_sorting'] = 1;
            }
            $category_params_model = new shopCategoryParamsModel();
            $category_params_model->set($category_id, !empty($data['params']) ? $data['params'] : null);

            $data['routes'] = ifset($data['routes'], array());
            $data['propagate_visibility'] = waRequest::post('propagate_visibility');

            $category_routes_model = new shopCategoryRoutesModel();
            $category_routes_model->setRoutes($category_id, $data['routes'], $data['propagate_visibility']);

            $category_og_model = new shopCategoryOgModel();
            $category_og_model->set($category_id, ifempty($data['og'], array()));

            $data['id'] = $category_id;
            /**
             * @event category_save
             * @param array $category
             * @return void
             */
            wa()->event('category_save', $data);
        }
        return $category_id;

    }

    private function categorySettingsValidate($category, $data)
    {
        if (!empty($data['url'])) {
            if ($this->model->urlExists($data['url'], $category['id'], $category['parent_id'])) {
                $this->errors['url'] = _w('URL is in use');
            }
        }
        return empty($this->errors);
    }

    private function getData()
    {
        $type = waRequest::post('type', 0, waRequest::TYPE_INT);
        $data = array(
            'name'             => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'url'              => waRequest::post('url', '', waRequest::TYPE_STRING_TRIM),
            'description'      => waRequest::post('description', '', waRequest::TYPE_STRING_TRIM),
            'meta_title'       => waRequest::post('meta_title', '', waRequest::TYPE_STRING_TRIM),
            'meta_keywords'    => waRequest::post('meta_keywords', '', waRequest::TYPE_STRING_TRIM),
            'meta_description' => waRequest::post('meta_description', '', waRequest::TYPE_STRING_TRIM),
            'params'           => waRequest::post('params', '', waRequest::TYPE_STRING_TRIM),
            'parent_id'        => waRequest::get('parent_id', 0, waRequest::TYPE_INT),
            'type'             => $type,
            'status'           => waRequest::post('hidden', 0) ? 0 : 1,
            'routes'           => waRequest::post('routes', array())
        );
        $params = array();
        if (!empty($data['params'])) {
            foreach (explode("\n", $data['params']) as $param_str) {
                $param = explode('=', $param_str, 2);
                if (count($param) > 1) {
                    $params[$param[0]] = trim($param[1]);
                }
            }
        }
        $data['params'] = $params;
        $data['filter'] = $this->getFilter();

        if ($type == shopCategoryModel::TYPE_DYNAMIC) {
            $data['conditions'] = $this->getConditions();
        }
        $data['sort_products'] = waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM);
        $data['sort_products'] = !empty($data['sort_products']) ? $data['sort_products'] : null;

        $data['include_sub_categories'] = waRequest::post('include_sub_categories', 0, waRequest::TYPE_INT);
        $data['og'] = waRequest::post('og', array(), waRequest::TYPE_ARRAY);

        $data['update_subcategories'] = waRequest::post('update_subcategories', 0);

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
            $conditions[] = 'rating'.$raw_condition['rating'][0].$raw_condition['rating'][1];
        }
        if (isset($raw_condition['tag'])) {
            $tags = (array)waRequest::post('tag', array());
            if (!empty($tags)) {
                $tags_query = implode('||', $tags);
                $tags_query = str_replace('&', '\&', $tags_query);
                $conditions[] = 'tag='.$tags_query;
            }
        }
        if (isset($raw_condition['price'])) {
            $raw_condition['price'] = waRequest::post('price');
            if (!empty($raw_condition['price'][0])) {
                $conditions[] = 'price>='.$raw_condition['price'][0];
            }
            if (!empty($raw_condition['price'][1])) {
                $conditions[] = 'price<='.$raw_condition['price'][1];
            }
        }

        $features = ifset($raw_condition, 'features', []);

        if ($features) {
            foreach ($features as $f_code => $f_data) {
                $f_type = ifset($f_data, 'type', null);
                $f_values = ifset($f_data, 'values', null);
                if (substr($f_type, 0, 5) === 'range') {
                    $begin = ifset($f_values, 'begin', null);
                    if (is_numeric($begin)) {
                        $conditions[] = $f_code.'.value>='.$begin;
                    }
                    $end = ifset($f_values, 'end', null);
                    if (is_numeric($end)) {
                        $conditions[] = $f_code.'.value<='.$end;
                    }
                } elseif ($f_values) {
                    $conditions[] = $f_code.'.value_id='.implode(',', $f_values);
                }
            }
        }

        if (isset($raw_condition['count'])) {
            $raw_condition['count'] = waRequest::post('count');
            $conditions[] = 'count'.$raw_condition['count'][0].$raw_condition['count'][1];
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
