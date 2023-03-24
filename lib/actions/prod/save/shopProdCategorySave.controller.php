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
        if (!empty($category['id'])) {
            $category_model = new shopCategoryModel();
            $saved_category = $category_model->getById($category['id']);
            $category_type = $saved_category['type'];
        } else {
            $category_type = $category['type'];
        }
        $category['name'] = trim(preg_replace('#\s+#', ' ', $category['name']));
        $parent = $this->model->getById($category['parent_id']);
        if ($parent && $parent['type'] == shopCategoryModel::TYPE_DYNAMIC && $category_type == shopCategoryModel::TYPE_STATIC) {
            throw new waException('You cannot create a static category in a dynamic category.');
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

        if ($category_type == shopCategoryModel::TYPE_DYNAMIC) {
            $category['conditions'] = $this->getConditions();
        }
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
                $data['name'] = _w('(no name)');
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

            $category_routes_model = new shopCategoryRoutesModel();
            $category_routes_model->setRoutes($category_id, $data['routes'], !empty($data['propagate_visibility']));

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

    /**
     * @return string
     */
    protected function getConditions()
    {
        $raw_condition = waRequest::post('condition', [], waRequest::TYPE_ARRAY);
        $conditions = [];

        $range_fields = ['create_datetime', 'edit_datetime', 'rating', 'price', 'compare_price', 'purchase_price', 'count'];
        $select_fields = ['type_id', 'tag', 'badge'];
        foreach ($raw_condition as $key => $item) {
            if (in_array($key, $range_fields)) {
                $sign = null;
                if (!mb_strlen($item['begin'])) {
                    unset($item['begin']);
                    $sign = '<=';
                } elseif (!mb_strlen($item['end'])) {
                    unset($item['end']);
                    $sign = '>=';
                }
                $raw_data = array_values($item);
                $validated_params = shopFilter::validateValue($raw_data, $key);
                if (empty($validated_params)) {
                    $this->errors = [
                        'id' => 'incorrect_params',
                        'text' => _w('Incorrect parameters.'),
                    ];
                    return '';
                }
                if ($sign == null) {
                    $conditions[] = "$key>=" . $validated_params[0];
                    $conditions[] = "$key<=" . $validated_params[1];
                } else {
                    $conditions[] = $key . $sign . $validated_params[0];
                }
            } elseif (in_array($key, $select_fields) && !empty($item) && is_array($item)) {
                if ($key == 'tag') {
                    $tag_model = new shopTagModel();
                    $names = $tag_model->select('name')->where('id IN (?)', [$item])->fetchAll();
                    $item = array_column($names, 'name');
                }
                $select_data = implode('||', $item);
                $select_data = str_replace('&', '\&', $select_data);
                $conditions[] = $key.'='.$select_data;
            }
        }

        if (!empty($raw_condition['features'])) {
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getByCode(array_keys($raw_condition['features']));
            foreach ($raw_condition['features'] as $code => $feature_data) {
                if (isset($features[$code])) {
                    $is_range = false;
                    $sign = $unit = null;
                    if (isset($feature_data['begin'])) {
                        $is_range = true;
                        if (isset($feature_data['unit']) && mb_strlen($feature_data['unit'])) {
                            $unit = $feature_data['unit'];
                            unset($feature_data['unit']);
                        }
                        if (!mb_strlen($feature_data['begin'])) {
                            unset($feature_data['begin']);
                            $sign = '<=';
                        } elseif (!mb_strlen($feature_data['end'])) {
                            unset($feature_data['end']);
                            $sign = '>=';
                        }
                    }

                    if (is_array($feature_data)) {
                        $feature_data = array_values($feature_data);
                    } else {
                        // boolean
                        $feature_data = [$feature_data];
                    }
                    $type = $features[$code] + ['display_type' => 'feature'];
                    $validated_params = shopFilter::validateValue($feature_data, '', $type);
                    if (empty($validated_params)) {
                        $this->errors = [
                            'id' => 'incorrect_feature_params',
                            'text' => _w('Incorrect feature parameters.'),
                        ];
                        return '';
                    }
                    if ($is_range) {
                        if ($sign == null) {
                            $conditions[] = "$code.value>=" . $validated_params[0];
                            $conditions[] = "$code.value<=" . $validated_params[1];
                        } else {
                            $conditions[] = "$code.value$sign" . $validated_params[0];
                        }
                        if ($unit) {
                            $conditions[] = $code . '.unit=' . $unit;
                        }
                    } else {
                        $conditions[] = $code.'.value_id='.implode(',', $validated_params);
                    }
                }
            }
        }

        return implode('&', $conditions);
    }
}
