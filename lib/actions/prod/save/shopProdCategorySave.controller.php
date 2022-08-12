<?php

class shopProdCategorySaveController extends waJsonController
{
    /** @var shopCategoryModel */
    protected $model = null;

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->model = new shopCategoryModel();
        $category = waRequest::post('category', [], waRequest::TYPE_ARRAY);

        $this->validateData($category);
        if (!$this->errors) {
            $id = $this->save($category);
            if ($id) {
                $this->response['data'] = [];
                $saved_category = $this->model->getById($id);
                $this->response['data']['subcategories_updated'] = $category['update_subcategories'];
                $saved_category['name'] = htmlspecialchars($saved_category['name'], ENT_NOQUOTES);

                // when use iframe-transport unescaped content bring errors when parseJSON
                if (!empty($saved_category['description'])) {
                    $saved_category['description'] = htmlspecialchars($saved_category['description'], ENT_NOQUOTES);
                }
                $this->response['data']['category'] = $saved_category;

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
                $this->response['data']['routes'] = $routes;
            }
        }
    }

    protected function validateData(&$category)
    {
        $parent = $this->model->getById($category['parent_id']);
        if ($parent && $parent['type'] == shopCategoryModel::TYPE_DYNAMIC && $category['type'] == shopCategoryModel::TYPE_STATIC) {
            throw new waException('You cannot create a static category in a dynamic category.');
        }

        if (!empty($category['url'])) {
            $categegory_id = isset($category['id']) ? $category['id'] : null;
            if ($this->model->urlExists($category['url'], $categegory_id, $category['parent_id'])) {
                $this->errors['url'] = _w('URL is in use');
                return;
            }
        }

        if (!empty($category['allow_filter']) && !empty($category['filter'])) {
            $category['filter'] = implode(',', array_map('trim', $category['filter']));
        } else {
            $category['filter'] = null;
        }

        if (empty($category['sort_products'])) {
            $category['sort_products'] = null;
        }

        $params = [];
        if (!empty($category['params'])) {
            foreach (explode("\n", $category['params']) as $param_str) {
                $param = explode('=', $param_str, 2);
                if (count($param) > 1) {
                    $params[$param[0]] = trim($param[1]);
                }
            }
        }
        $category['params'] = $params;

//        if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
//            $data['conditions'] = $this->getConditions();
//        }

    }

    private function save(&$data)
    {
        $category_id = null;
        if (isset($data['id'])) {
            $category_id = $data['id'];
            unset($data['id']);
        }

        /**
         * @var shopCategoryModel
         */
        if (!$category_id) {
            if (!mb_strlen($data['url'])) {
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
            if (empty($data['name'])) {
                $data['name'] = $category['name'];
            }
            if (!mb_strlen($data['url'])) {
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
                $this->model->updateByField('parent_id', $category_ids, ['status' => $data['hidden']]);
            }

            if (!empty($data['enable_sorting'])) {
                $data['params']['enable_sorting'] = 1;
            }
            $category_params_model = new shopCategoryParamsModel();
            $category_params_model->set($category_id, !empty($data['params']) ? $data['params'] : null);

            $data['routes'] = ifset($data['routes'], []);
            $data['propagate_visibility'] = waRequest::post('propagate_visibility');

            $category_routes_model = new shopCategoryRoutesModel();
            $category_routes_model->setRoutes($category_id, $data['routes'], $data['propagate_visibility']);

            $category_og_model = new shopCategoryOgModel();
            $category_og_model->set($category_id, ifempty($data['og'], []));

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

    private function getConditions()
    {
        $raw_condition = waRequest::post('condition');

        $conditions = [];
        if (isset($raw_condition['rating'])) {
            $raw_condition['rating'] = waRequest::post('rating');
            $conditions[] = 'rating'.$raw_condition['rating'][0].$raw_condition['rating'][1];
        }
        if (isset($raw_condition['tag'])) {
            $tags = waRequest::post('tag', [], waRequest::TYPE_ARRAY);
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