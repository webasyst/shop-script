<?php
/**
 * 
 */
class shopFrontendApiCategoriesController extends shopFrontApiJsonController
{
    /** @var shopCategoryModel $model */
    private $model;

    public function get($token = null)
    {
        $parent_id = waRequest::get('parent_id', null, 'int');
        $depth = waRequest::get('depth', null, 'int');
        $return_tree = waRequest::get('tree', null, 'int');
        $filters = waRequest::get('filters', 0, waRequest::TYPE_INT);

        if ($depth !== null && $depth <= 0) {
            $depth = null;
        }

        $this->model = new shopCategoryModel();
        $cats = $this->model->getTree($parent_id, $depth);
        if ($filters) {
            $cats = $this->fillFilters($cats);
        }
        $cats = $this->formatCategories($cats);

        if ($return_tree) {
            $cats = $this->buildTree($cats);
        }

        $this->response['categories'] = $cats;
    }

    protected function formatCategories($cats)
    {
        $formatter = new shopFrontApiCategoryFormatter([
            'without_meta' => true,
        ]);
        $result = array();
        foreach ($cats as $c) {
            $result[] = $formatter->format($c);
        }
        return $result;
    }

    protected function buildTree($cats)
    {
        $stack = array();
        $result = array();
        foreach ($cats as $c) {
            $c['categories'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while ($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($result);
                $result[$i] = $c;
                $stack[] = & $result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['categories']);
                if ($stack[$l - 1]['id'] == $c['parent_id']) {
                    $stack[$l - 1]['categories'][$i] = $c;
                    $stack[] = & $stack[$l - 1]['categories'][$i];
                }
            }
        }
        return $result;
    }

    protected function fillFilters($categories)
    {
        $all_filters = [];
        $filter_ids_by_cat = array_filter(array_column($categories, 'filter', 'id'));
        foreach ($filter_ids_by_cat as $key => $_filter_id) {
            $filter_ids_by_cat[$key] = explode(',', $_filter_id);
            $all_filters += array_fill_keys($filter_ids_by_cat[$key], 1);
        }

        $feature_formatter = new shopFrontApiFeatureFormatter();
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getById(array_filter(array_keys($all_filters), 'is_numeric'));
        if ($features) {
            $features = $feature_model->getValues($features);
        }

        $ranges = $this->getPriceRanges();
        $categories_value_ids = $this->getFeatureValueIds();

        foreach ($categories as &$_category) {
            $filters = [];
            $feature_map = [];
            $range = ifset($ranges, $_category['id'], []);
            $category_value_ids = ifset($categories_value_ids, $_category['id'], []);
            $filter_ids = ifset($filter_ids_by_cat, $_category['id'], []);

            foreach ($filter_ids as $fid) {
                if (!isset($filters['price']) && ($fid == 'price' || $fid == 'base_price')) {
                    if ($range['min'] != $range['max']) {
                        if (($range['max'] - $range['min']) <= 1) {
                            $range['max'] +=2;
                        }
                        $filters['price'] = [
                            'name' => 'price',
                            'values' => [
                                ['name' => 'min', 'value' => (float) shop_currency($range['min'], null, null, false)],
                                ['name' => 'max', 'value' => (float) shop_currency($range['max'], null, null, false)],
                            ]
                        ];
                    }
                } elseif (isset($features[$fid]) && isset($category_value_ids[$fid])) {
                    $min = null;
                    $max = null;
                    $unit = null;

                    //set existing feature code with saved filter id
                    $feature_map[$features[$fid]['code']] = $fid;

                    //set feature data
                    $filters[$fid] = $features[$fid];

                    foreach ($filters[$fid]['values'] as $v_id => $v) {
                        //remove unused
                        if (!in_array($v_id, $category_value_ids[$fid])) {
                            unset($filters[$fid]['values'][$v_id]);
                        } else {
                            if ($v instanceof shopRangeValue) {
                                $begin = $this->getFeatureValue($v->begin);
                                if (is_numeric($begin) && ($min === null || (float) $begin < (float) $min)) {
                                    $min = $begin;
                                }
                                $end = $this->getFeatureValue($v->end);
                                if (is_numeric($end) && ($max === null || (float) $end > (float) $max)) {
                                    $max = $end;
                                    if ($v->end instanceof shopDimensionValue) {
                                        $unit = $v->end->unit;
                                    }
                                }
                            } else {
                                $tmp_v = $this->getFeatureValue($v);
                                if ($min === null || $tmp_v < $min) {
                                    $min = $tmp_v;
                                }
                                if ($max === null || $tmp_v > $max) {
                                    $max = $tmp_v;
                                    if ($v instanceof shopDimensionValue) {
                                        $unit = $v->unit;
                                    }
                                }
                            }
                        }
                    }
                    if (
                        !$filters[$fid]['selectable']
                        && ($filters[$fid]['type'] == 'double' || substr($filters[$fid]['type'], 0, 6) == 'range.' || substr($filters[$fid]['type'], 0, 10) == 'dimension.')
                    ) {
                        if ($min == $max) {
                            unset($filters[$fid]);
                        } else {
                            $type = preg_replace('/^[^.]*\./', '', $filters[$fid]['type']);
                            if ($type == 'date') {
                                $min = shopDateValue::timestampToDate($min);
                                $max = shopDateValue::timestampToDate($max);
                            } elseif ($type != 'double') {
                                $filters[$fid]['base_unit'] = shopDimension::getBaseUnit($type);
                                $filters[$fid]['unit'] = shopDimension::getUnit($type, $unit);
                                if ($filters[$fid]['base_unit']['value'] != $filters[$fid]['unit']['value']) {
                                    $dimension = shopDimension::getInstance();
                                    $min = $dimension->convert($min, $type, $filters[$fid]['unit']['value']);
                                    $max = $dimension->convert($max, $type, $filters[$fid]['unit']['value']);
                                }
                            }
                            $filters[$fid]['min'] = $min;
                            $filters[$fid]['max'] = $max;
                        }
                    }
                }
            }

            if ($_category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                $conditions = shopProductsCollection::parseConditions($_category['conditions']);

                foreach ($conditions as $field => $field_conditions) {
                    switch ($field) {
                        case 'price':
                            foreach ($field_conditions as $condition) {
                                $type = reset($condition);
                                switch ($type) {
                                    case '>=':
                                        $min = shop_currency(doubleval(end($condition)), null, null, false);

                                        if (empty($filter_data['price_min'])) {
                                            $filter_data['price_min'] = $min;
                                        } else {
                                            $filter_data['price_min'] = max($min, $filter_data['price_min']);
                                        }

                                        if (isset($filters['price']['min'])) {
                                            $filters['price']['min'] = max($filter_data['price_min'], $filters['price']['min']);
                                        }
                                        break;
                                    case '<=':
                                        $max = shop_currency(doubleval(end($condition)), null, null, false);
                                        if (empty($filter_data['price_max'])) {
                                            $filter_data['price_max'] = $max;
                                        } else {
                                            $filter_data['price_max'] = min($max, $filter_data['price_max']);
                                        }
                                        if (isset($filters['price']['max'])) {
                                            $filters['price']['max'] = min($filter_data['price_max'], $filters['price']['max']);
                                        }
                                        break;
                                }
                            }
                            break;
                        case 'count':
                        case 'rating':
                        case 'compare_price':
                        case 'tag':
                            break;
                        default:
                            if (preg_match('@(\w+)\.(value_id)$@', $field, $matches)) {
                                $feature_code = $matches[1];
                                $first_condition = reset($field_conditions);

                                //If first condition is array that is range. Not need this magic (May be) See below comment)
                                if (!is_array($first_condition)) {
                                    $value_id = array_map('intval', preg_split('@[,\s]+@', end($field_conditions)));

                                    $feature_id = ifset($feature_map, $feature_code, $feature_code);

                                    if (empty($filter_data[$feature_code])) {
                                        $filter_data[$feature_code] = $value_id;
                                    }

                                    //If you understand what this block does write a comment please.
                                    if (!empty($filters[$feature_id]['values'])) {
                                        foreach ($filters[$feature_id]['values'] as $_value_id => $_value) {
                                            if (!in_array($_value_id, $value_id)) {
                                                unset($filters[$feature_id]['values'][$_value_id]);
                                            }
                                        }
                                    }
                                }
                            }
                            break;
                    }
                }
            }

            $_category['filters'] = array_map([$feature_formatter, 'format'], $filters);
        }
        unset($_category);

        return $categories;
    }

    protected function getPriceRanges()
    {
        $ranges = [];
        $data = $this->model->query("
            SELECT sc.id cat_id, sc.parent_id cat_parent_id, MIN(p.min_price) min, MAX(p.max_price) max FROM shop_category_products cp1 
            JOIN shop_category sc ON sc.id = cp1.category_id 
            JOIN shop_product p ON p.id = cp1.product_id
            WHERE p.status = 1
            GROUP BY sc.id, sc.parent_id 
            ORDER BY sc.parent_id, sc.id
        ")->fetchAll();

        foreach ($data as $_d) {
            if (!isset($ranges[$_d['cat_id']])) {
                $ranges[$_d['cat_id']] = array_intersect_key($_d, ['max' => 1, 'min' => 1]);
            }

            if (isset($ranges[$_d['cat_parent_id']])) {
                if ($ranges[$_d['cat_parent_id']]['min'] > $_d['min']) {
                    $ranges[$_d['cat_parent_id']]['min'] = $_d['min'];
                }
                if ($ranges[$_d['cat_parent_id']]['max'] < $_d['max']) {
                    $ranges[$_d['cat_parent_id']]['max'] = $_d['max'];
                }
            }
        }

        return $ranges;
    }

    protected function getFeatureValueIds()
    {
        $rows = $this->model->query("
            SELECT DISTINCT sc.id category_id, sc.parent_id, pf1.feature_id, pf1.feature_value_id FROM shop_product p
            JOIN shop_category_products cp1 ON p.id = cp1.product_id
            JOIN shop_product_features pf1 ON p.id = pf1.product_id
            JOIN shop_category sc ON sc.id = cp1.category_id
            WHERE p.status = 1
        ")->fetchAll();

        $category_value_ids = [];
        if ($rows) {
            while ($row = array_shift($rows)) {
                $category_value_ids[$row['category_id']][$row['feature_id']][] = $row['feature_value_id'];
                if (!empty($row['parent_id'])) {
                    $category_value_ids[$row['parent_id']][$row['feature_id']][] = $row['feature_value_id'];
                }
            }
        }

        return $category_value_ids;
    }

    /**
     * @param $v
     * @return int|string
     */
    protected function getFeatureValue($v)
    {
        if ($v instanceof shopDimensionValue) {
            return $v->value_base_unit;
        } elseif ($v instanceof shopDateValue) {
            return $v->timestamp;
        }
        if (is_object($v)) {
            return $v->value;
        }

        return $v;
    }
}
