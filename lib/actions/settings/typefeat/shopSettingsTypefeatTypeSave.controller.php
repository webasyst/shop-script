<?php
/**
 * Accept POST from type editor dialog to save new or existing product type.
 * New type can be created from a template or name + icon form. This also adds features.
 */
class shopSettingsTypefeatTypeSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopTypeModel();

        $data = array(
            'id' => waRequest::post('id', 0, waRequest::TYPE_INT),
            'stock_unit_id' => waRequest::post('stock_unit_id', 0, waRequest::TYPE_INT),
            'stock_unit_fixed' => waRequest::post('stock_unit_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'base_unit_id' => waRequest::post('base_unit_id'),
            'base_unit_fixed' => waRequest::post('base_unit_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'stock_base_ratio' => waRequest::post('stock_base_ratio', 1),
            // not an error, this setting is not displayed in the interface
            'stock_base_ratio_fixed' => waRequest::post('base_unit_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'order_count_min' => waRequest::post('order_count_min'),
            'order_count_min_fixed' => waRequest::post('order_count_min_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'order_count_step' => waRequest::post('order_count_step'),
            'order_count_step_fixed' => waRequest::post('order_count_step_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'count_denominator' => waRequest::post('count_denominator'),
            'count_denominator_fixed' => waRequest::post('count_denominator_fixed', shopTypeModel::PARAM_DISABLED, waRequest::TYPE_INT),
            'order_multiplicity_factor' => waRequest::post('order_multiplicity_factor'),
            'order_multiplicity_factor_fixed' => waRequest::post('order_multiplicity_factor_fixed', null, waRequest::TYPE_INT),
            'migrate_product' => [],
        );
        $migrate_data = waRequest::post('migrate', [], waRequest::TYPE_ARRAY);
        foreach (['count_denominator', 'stock_unit_id', 'base_unit_id', 'order_multiplicity_factor',
                     'stock_base_ratio', 'order_count_min', 'order_count_step'] as $field
        ) {
            if (isset($migrate_data[$field])) {
                $data['migrate_product'][$field] = $migrate_data[$field];
            }
        }
        switch (waRequest::post('source', 'custom')) {
            case 'custom':
                $data['name'] = waRequest::post('name');
                if (trim($data['name']) === '') {
                    $this->errors[] = [
                        'name' => 'name',
                        'value' => _w('This field is required.')
                    ];
                    return;
                }
                $data['icon'] = waRequest::post('icon_url', false, waRequest::TYPE_STRING_TRIM);
                if (empty($data['icon'])) {
                    $data['icon'] = waRequest::post('icon', 'icon.box', waRequest::TYPE_STRING_TRIM);
                }
                $this->formatTypeFractionalData($data);
                $this->validateFractionalData($data);
                if (!$this->errors) {
                    if ($data['id'] > 0) {
                        $update_data = $this->formatProductsFractionalData($data);
                        $disabled_base_unit = shopTypeModel::PARAM_DISABLED;
                        $condition = $data['base_unit_fixed'] == $disabled_base_unit ? '!' : '';
                        $is_changed_base_unit = $model->select("base_unit_fixed $condition= $disabled_base_unit is_changed_base_unit")
                            ->where('id = ?', $data['id'])->fetchField('is_changed_base_unit');
                        $this->updateProducts($data['id'], $update_data, $is_changed_base_unit);
                    }
                    $data = array_intersect_key($data, $model->getEmptyRow());
                    if (!empty($data['id'])) {
                        $model->updateById($data['id'], $data);
                    } else {
                        $data['sort'] = $model->select('MAX(sort)+1 as max_sort')->fetchField('max_sort');
                        $data['id'] = $model->insert($data);
                    }
                    $routing_path = $this->getConfig()->getPath('config', 'routing');
                    if (file_exists($routing_path)) {
                        $routes = include($routing_path);
                        $new_routes = $this->addTypeToRoutes(
                            waRequest::post('storefronts', [], waRequest::TYPE_ARRAY),
                            intval($data['id']),
                            array_map('intval', array_column((new shopTypeModel())->getAll(), 'id')),
                            $routes
                        );
                        // Only ever touch config if something changed
                        if ($routes != $new_routes) {
                            waUtils::varExportToFile($new_routes, $routing_path);
                        }
                    }
                }
                break;
            case 'template':
                $data = $model->insertTemplate(waRequest::post('template'), true);
                break;
        }

        if ($data) {

            /**
             * @event backend_type_save
             * @since 10.1.0
             * @param array $type
             * @return void
             */
            wa('shop')->event('backend_type_save', ref([
                'type' => &$data,
            ]));

            $data['icon_html'] = shopHelper::getIcon($data['icon'], 'icon.box');
            $data['name_html'] = '<span class="js-type-icon">'.$data['icon_html'].'</span>
                    <span class="js-type-name">'.htmlspecialchars($data['name'], ENT_QUOTES, 'utf-8').'</span>';
        }
        $this->response = $data;
    }

    protected function formatTypeFractionalData(&$data)
    {
        if (empty(wa()->getSetting('stock_units_enabled'))) {
            $data['stock_unit_fixed'] = shopTypeModel::PARAM_DISABLED;
        }
        if (empty(wa()->getSetting('base_units_enabled'))) {
            $data['base_unit_fixed'] = shopTypeModel::PARAM_DISABLED;
        }
        if (empty(wa()->getSetting('frac_enabled'))) {
            $data['count_denominator_fixed'] = shopTypeModel::PARAM_DISABLED;
        }

        if ($data['order_multiplicity_factor_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['order_multiplicity_factor'] = 1;
        }
        if (!empty($data['order_multiplicity_factor']) && isset($data['order_multiplicity_factor_fixed'])) {
            $data['count_denominator'] = shopFrac::calculateCountDenominator($data['order_multiplicity_factor']);
            $data['count_denominator_fixed'] = $data['order_multiplicity_factor_fixed'];
        }

        if ($data['stock_unit_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['stock_unit_id'] = 0;
            $data['base_unit_fixed'] = shopTypeModel::PARAM_DISABLED;
        }
        if ($data['base_unit_id'] === '') {
            $data['base_unit_id'] = null;
        } else {
            $data['base_unit_id'] = (int) $data['base_unit_id'];
        }
        if ($data['base_unit_fixed'] == shopTypeModel::PARAM_ONLY_TYPES && !isset($data['base_unit_id'])) {
            $data['base_unit_fixed'] = shopTypeModel::PARAM_ALL_PRODUCTS;
        }
        if ($data['base_unit_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['base_unit_id'] = null;
            $data['stock_base_ratio'] = 1;
            $data['stock_base_ratio_fixed'] = shopTypeModel::PARAM_DISABLED;
        } else {
            $data['stock_base_ratio_fixed'] = shopTypeModel::PARAM_ALL_PRODUCTS;
        }
        if ($data['count_denominator_fixed'] == shopTypeModel::PARAM_ALL_PRODUCTS) {
            if ($data['count_denominator'] === '') {
                $data['count_denominator'] = null;
            } else {
                $data['count_denominator'] = shopFrac::correctCountDenominator($data['count_denominator']);
            }
        } elseif ($data['count_denominator_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['count_denominator'] = 1;
        } else {
            $data['count_denominator'] = shopFrac::correctCountDenominator($data['count_denominator']);
        }

        if ($data['base_unit_fixed'] < 0 || $data['base_unit_fixed'] > 2) {
            $data['base_unit_fixed'] = shopTypeModel::PARAM_DISABLED;
        }
        if (isset($data['migrate_product']['count_denominator'])) {
            $data['migrate_product']['count_denominator'] = shopFrac::correctCountDenominator($data['migrate_product']['count_denominator']);
        }
        if (empty($data['order_multiplicity_factor']) && !isset($data['order_multiplicity_factor_fixed'])) {
            if (!empty($data['count_denominator'])) {
                $data['order_multiplicity_factor'] = 1 / $data['count_denominator'];
            }
            $data['order_multiplicity_factor_fixed'] = $data['count_denominator_fixed'];
        }
        if ($data['order_count_min_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['order_count_min'] = $data['order_multiplicity_factor'];
        }
        if ($data['order_count_step_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $data['order_count_step'] = $data['order_multiplicity_factor'];
        }
    }

    protected function validateFractionalData($data)
    {
        if (is_numeric($data['base_unit_id']) && $data['base_unit_id'] == $data['stock_unit_id']) {
            $this->errors[] = [
                'id' => 'base_unit_error',
                'name' => "type[base_unit_id]",
                'text' => _w('A stock and a base unit must be different.'),
            ];
        }
        if (isset($data['migrate_product']['base_unit_id']) && isset($data['migrate_product']['stock_unit_id'])
            && (is_numeric($data['migrate_product']['base_unit_id'])
                && $data['migrate_product']['base_unit_id'] == $data['migrate_product']['stock_unit_id'])
        ) {
            $this->errors[] = [
                'id' => 'migrate_base_unit_error',
                'name' => "migrate[base_unit_id]",
                'text' => _w('A stock and a base unit must be different.'),
            ];
        }
        if (strlen($data['stock_base_ratio']) > 16) {
            $this->errors[] = [
                'id' => 'stock_base_ratio_error',
                'name' => "type[stock_base_ratio]",
                'text' => _w('Invalid value'),
            ];
        }
        if (isset($data['migrate_product']['stock_base_ratio'])
            && strlen($data['migrate_product']['stock_base_ratio']) > 16
        ) {
            $this->errors[] = [
                'id' => 'migrate_stock_base_ratio_error',
                'name' => "migrate[stock_base_ratio]",
                'text' => _w('Invalid value'),
            ];
        }
        $fields = ['order_count_min', 'order_count_step'];
        foreach ($fields as $field) {
            if (strlen($data[$field]) > 15) {
                $this->errors[] = [
                    'id' => $field . '_error',
                    'name' => "type[$field]",
                    'text' => _w('Invalid value'),
                ];
            }
            if (isset($data['migrate_product'][$field]) && strlen($data['migrate_product'][$field]) > 15) {
                $this->errors[] = [
                    'id' => 'migrate_' . $field . '_error',
                    'name' => "migrate[$field]",
                    'text' => _w('Invalid value'),
                ];
            }
        }
    }

    protected function formatProductsFractionalData($data)
    {
        $update_products = $data['migrate_product'];
        $update_product_skus = [];
        if ($data['stock_unit_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['stock_unit_id'] = $data['stock_unit_id'];
        } elseif ($data['stock_unit_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $update_products['stock_unit_id'] = 0;
        }

        if ($data['base_unit_fixed'] == shopTypeModel::PARAM_ALL_PRODUCTS) {
            if (isset($update_products['base_unit_id']) && $update_products['base_unit_id'] === '') {
                $update_products['base_unit_id'] = 'p.stock_unit_id';
            }
        } elseif ($data['base_unit_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['base_unit_id'] = $data['base_unit_id'];
        }

        if ($data['stock_base_ratio_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['stock_base_ratio'] = $data['stock_base_ratio'];
            $update_product_skus['stock_base_ratio'] = null;
        }

        if ($data['order_multiplicity_factor_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['order_multiplicity_factor'] = $data['order_multiplicity_factor'];
        } elseif ($data['order_multiplicity_factor_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $update_products['order_multiplicity_factor'] = 1;
        }

        if ($data['base_unit_fixed'] == shopTypeModel::PARAM_DISABLED
            || $data['stock_base_ratio_fixed'] == shopTypeModel::PARAM_DISABLED
        ) {
            $update_products['base_unit_id'] = 'p.stock_unit_id';
            $update_products['stock_base_ratio'] = 1;
            $update_product_skus['stock_base_ratio'] = null;
        }

        if ($data['count_denominator_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['count_denominator'] = $data['count_denominator'];
        } elseif ($data['count_denominator_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $update_products['count_denominator'] = 1;
        }

        if (!isset($update_products['order_multiplicity_factor'])) {
            if (!empty($update_products['count_denominator'])) {
                $update_products['order_multiplicity_factor'] = 1 / $data['count_denominator'];
            }
        } else {
            $update_products['count_denominator'] = shopFrac::calculateCountDenominator($update_products['order_multiplicity_factor']);
        }
        $order_multiplicity_factor = isset($update_products['order_multiplicity_factor']) ? $update_products['order_multiplicity_factor'] : $data['order_multiplicity_factor'];
        if ($data['order_count_min_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['order_count_min'] = $data['order_count_min'];
            $update_product_skus['order_count_min'] = null;
        } elseif ($data['order_count_min_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $update_products['order_count_min'] = $order_multiplicity_factor;
            $update_product_skus['order_count_min'] = null;
        }

        if ($data['order_count_step_fixed'] == shopTypeModel::PARAM_ONLY_TYPES) {
            $update_products['order_count_step'] = $data['order_count_step'];
            $update_product_skus['order_count_step'] = null;
        } elseif ($data['order_count_step_fixed'] == shopTypeModel::PARAM_DISABLED) {
            $update_products['order_count_step'] = $order_multiplicity_factor;
            $update_product_skus['order_count_step'] = null;
        }

        return [
            'p' => $update_products,
            'ps' => $update_product_skus
        ];
    }

    protected function updateProducts($type_id, $data, $update_base_prices = false)
    {
        if (!empty($data['p'])) {
            $params = [];
            $product_model = new shopProductModel();
            foreach ($data as $table => $update_data) {
                foreach ($update_data as $field => $value) {
                    if ($value === null) {
                        $value = 'NULL';
                    }
                    if (strlen((string)$value) != 0) {
                        $params[] = $table . '.' . $field . ' = ' . $product_model->escape($value);
                    }
                }
            }

            $query = "UPDATE {$product_model->getTableName()} p
                        JOIN shop_product_skus ps ON p.id = ps.product_id
                      SET " . implode(', ', $params) . " WHERE p.type_id = $type_id";
            $product_model->exec($query);

            if ($update_base_prices) {
                $step = 0;
                $limit = 100;
                do {
                    $product_ids = $product_model->select('id')->where('type_id = ?', $type_id)->limit("$step, $limit")->fetchAll(null, true);
                    shopProdSetTypeController::updateBasePrice($product_ids);
                    $step += $limit;
                } while ($product_ids);
            }
        }
    }

    /**
     * @param array $storefronts
     * @param string $type_id
     * @param array $all_types
     * @param array $routes
     * @return array
     */
    private function addTypeToRoutes($storefronts, $type_id, $all_types, $routes)
    {
        foreach ($routes as $site => $site_routes) {
            if (!is_array($site_routes)) {
                continue;
            }
            foreach ($site_routes as $route_id => $param) {
                if (ifset($param, 'app', null) !== 'shop' || !isset($param['url'])) {
                    continue;
                }
                $param['type_id'] = ifset($param, 'type_id', null);
                $enable = isset($storefronts[$site]) && in_array($param['url'], $storefronts[$site]);
                try {
                    $routes[$site][$route_id]['type_id'] = self::getNewRouteTypeId(ifset($param, 'type_id', null), $type_id, $enable, $all_types);
                } catch (waException $e) {
                    $this->errors[] = [
                        'name'  => 'storefronts['.$site.']['.$param['url'].']',
                        'value' => _w('The current product type is the only one selected for this storefront.')
                    ];
                }
            }
        }
        return $routes;
    }

    /**
     * Given the old 'type_id' route parameter, enable or disable given type_id
     * and return new 'type_id' for the route.
     * Throws waException when trying to disable the last type on this storefront.
     *
     * Example:
     * $this->getNewRouteTypeId([1, 3, 4], 2, true,  [1, 2, 3, 4]) ->> [1, 2, 3, 4]
     * $this->getNewRouteTypeId([1, 3, 4], 3, false, [1, 2, 3, 4]) ->> [1, 4]
     * $this->getNewRouteTypeId([2],       2, false, [1, 2, 3, 4]) ->> waException
     * $this->getNewRouteTypeId('',        2, false, [1, 2, 3, 4]) ->> [1, 3, 4]
     * $this->getNewRouteTypeId('',        2, false, [2])          ->> waException
     *
     * @param mixed $old_type_id
     * @param int $type_id
     * @param bool $enable
     * @param array $all_types
     * @return mixed
     * @throws waException
     */
    public static function getNewRouteTypeId($old_type_ids, $type_id, $enable, $all_types)
    {
        $ALL_INCLUDED = [null, [], false, '', '0', 0];

        // Enable a type on a storefront?
        if ($enable) {
            // Nothing to do if all types are already included
            if (in_array($old_type_ids, $ALL_INCLUDED, true)) {
                return $old_type_ids;
            }

            // Nothing to do if current selection already contains $type_id
            if (!is_array($old_type_ids)) {
                if ($old_type_ids == $type_id) {
                    return $old_type_ids;
                }
            } else {
                if (in_array($type_id, $old_type_ids)) {
                    return $old_type_ids;
                }
            }

            // Not all types are included, and current selection does not contain $type_id.
            // Add $type_id to list of types.
            $new_type_ids = $old_type_ids;
            if (!is_array($new_type_ids)) {
                $new_type_ids = [intval($old_type_ids)];
            }
            $new_type_ids[] = $type_id;
            return $new_type_ids;
        }

        //
        // Otherwise, disable type on a storefront.
        //

        $new_type_ids = $old_type_ids;

        // When all types are enabled, convert them to explicit list of types
        if (in_array($new_type_ids, $ALL_INCLUDED, true)) {
            $new_type_ids = $all_types;
        }

        if (!is_array($new_type_ids) || count($new_type_ids) <= 1) {
            if ($new_type_ids == [$type_id] || $new_type_ids == $type_id) {
                // Can not remove last type_id from a storefront
                throw new waException('Can not remove last type_id from a storefront');
            } else {
                // $type_id not in list, nothing to remove
                return $old_type_ids;
            }
        }

        // Remove a single type from list of types, if it is there
        if (!in_array($type_id, $new_type_ids)) {
            return $old_type_ids;
        }
        $new_type_ids = array_diff($new_type_ids, [$type_id]);
        return array_values($new_type_ids);
    }
}
