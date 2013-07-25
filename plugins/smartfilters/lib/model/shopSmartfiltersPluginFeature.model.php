<?php

class shopSmartfiltersPluginFeatureModel extends shopFeatureModel
{
    private $category_id;
    private $product_ids;
    private $features;

    public function getByCategoryId($category_id)
    {
        $this->category_id = (int)$category_id;
        $feature_value_ids = $this->getPossibleFilterValues();
        if (!$feature_value_ids)
            return array();

        $sql = 'SELECT f.code, f.id fid, f.name fname, fv.id fvid, fv.value fvval FROM shop_feature_values_varchar fv
            LEFT JOIN shop_feature f ON f.id = fv.feature_id
            WHERE fv.id IN(:ids)
	        ORDER BY fv.feature_id, fv.sort';

        $res = $this->query($sql, array('ids' => $this->escape($feature_value_ids, 'int')))->fetchAll(null, false);
        $filter = array();

        $codes = array();

        foreach ($res as $row) {
            /**
             * @TODO: Skip some params. Like:
             * if(in_array($row['code'], array('brand')))
             *    continue;
             */
            $filter[$row['code']]['name'] = $row['fname'];
            $filter[$row['code']]['code'] = $row['code'];
            $filter[$row['code']]['values'][$row['fvid']] = $row['fvval'];
            $filter[$row['code']]['disabled'][$row['fvid']] = false;
            $codes[$row['code']] = 1;
        }
        $data = waRequest::get();

        if ($data) {
            $codes = array_keys($codes);
            $feature_model = new shopFeatureModel();
            $this->features = $feature_model->getByField('code', $codes, 'code');

            foreach ($codes as $code) {
                $enabledFilters = $this->getEnabledFilters($code, $data);

                if ($enabledFilters === false) {
                    foreach ($filter[$code]['disabled'] as $fvid => $val) {
                        $filter[$code]['disabled'][$fvid] = true;
                    }
                } elseif (is_array($enabledFilters)) {
                    foreach ($filter[$code]['disabled'] as $fvid => $val) {
                        $filter[$code]['disabled'][$fvid] = in_array($fvid, $enabledFilters) ? false : true;
                    }
                }
            }
        }

        return $filter;
    }

    private function getPossibleFilterValues()
    {
        $category_model = new shopCategoryModel();
        $category = $category_model->getById($this->category_id);

        if ($category['include_sub_categories']) {
            $subcategories = $category_model->descendants($this->info, true)->where('type = ' . shopCategoryModel::TYPE_STATIC)->fetchAll('id');
            $ids = array_keys($subcategories);
        } elseif ($category['id']) {
            $ids = array($category['id']);
        }
        if (!$ids) return array();

        $sql = 'SELECT p.id FROM shop_product p JOIN shop_category_products cp ON p.id = cp.product_id WHERE p.status > 0 AND cp.category_id IN (:category_ids)';
        $this->product_ids = $this->query($sql, array('category_ids' => $ids))->fetchAll(null, true);
        if (!$this->product_ids) return array();

        $sql = 'SELECT DISTINCT feature_value_id FROM shop_product_features WHERE product_id IN(:product_ids)';
        return $this->query($sql, array('product_ids' => $this->product_ids))->fetchAll(null, true);
    }


    public function getEnabledFilters($key, $data)
    {
        $delete = array('page', 'sort', 'order', $key);
        foreach ($delete as $k) {
            if (isset($data[$k])) {
                unset($data[$k]);
            }
        }

        if (!count($data))
            return true;

        $where = array();
        $joins = array();

        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $where[] = 'p.price >= ' . (int)$data['price_min'];
            unset($data['price_min']);
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $where[] = 'p.price <= ' . (int)$data['price_max'];
            unset($data['price_max']);
        }
        $feature_join_index = 0;

        foreach ($data as $feature_id => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            if (isset($this->features[$feature_id])) {
                $feature_join_index++;
                $joins[] = sprintf(
                    " LEFT JOIN %s %s ON %s",
                    'shop_product_features',
                    'filter' . $feature_join_index,
                    'p.id = filter' . $feature_join_index . '.product_id AND filter' . $feature_join_index . '.feature_id = ' . (int)$this->features[$feature_id]['id']
                );
                foreach ($values as & $v) {
                    $v = (int)$v;
                }
                $where[] = 'filter' . $feature_join_index . ".feature_value_id IN (" . implode(',', $values) . ")";
            }
        }

        if (!$feature_join_index) {
            return true;
        }

        $where[] = 'p.id IN (:product_ids)';
        $sql = "SELECT p.id FROM shop_product p " . implode('', $joins) . " WHERE " . implode(' AND ', $where) . " GROUP BY p.id";

        $product_ids = $this->query($sql, array('product_ids' => $this->product_ids))->fetchAll(null, true);

        if (!$product_ids) return false;
        $sql = "SELECT DISTINCT feature_value_id FROM shop_product_features WHERE product_id IN(:product_ids) AND feature_id = :feature_id";
        $res = $this->query($sql, array('product_ids' => $product_ids, 'feature_id' => (int)$this->features[$key]['id']))->fetchAll(null, true);
        $res = array_map('intval', $res);
        return $res;
    }
}