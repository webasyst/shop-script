<?php
/**
 * Duplicates a product type.
 */
class shopSettingsTypefeatTypeDuplicateController extends waJsonController
{
    public function execute()
    {
        $type_model = new shopTypeModel();

        $id = waRequest::post('id', 0, waRequest::TYPE_INT);
        if ($id) {
            $old_type = $type_model->getById($id);
        }
        if (!$id || empty($old_type)) {
            throw new waException('Not found', 404);
        }

        $type_data = array_intersect_key($old_type, [
            'name' => 1,
            'icon' => 1,
            'sort' => 1,
            'upselling' => 1,
            'cross_selling' => 1,
            'stock_unit_fixed' => 1,
            'stock_unit_id' => 1,
            'base_unit_fixed' => 1,
            'base_unit_id' => 1,
            'stock_base_ratio_fixed' => 1,
            'stock_base_ratio' => 1,
            'count_denominator_fixed' => 1,
            'count_denominator' => 1,
            'order_multiplicity_factor_fixed' => 1,
            'order_multiplicity_factor' => 1,
            'order_count_min_fixed' => 1,
            'order_count_min' => 1,
            'order_count_step_fixed' => 1,
            'order_count_step' => 1,
        ]);

        // Append number to type name
        $old_name = $type_data['name'];
        if (preg_match('/^(.*\D)(\d+)$/', $old_name, $matches)) {
            $old_name = $matches[1];
            $number = $matches[2] + 1;
        } else {
            $old_name .= ' ';
            $number = 1;
        }
        // Make sure new name is unique
        do {
            $type_data['name'] = $old_name.$number;
            $number++;
        } while ($type_model->countByField('name', $type_data['name']) > 0);

        // shop_type
        $type_data['id'] = $type_model->insert($type_data);
        if (!$type_data['id']) {
            throw new waException('Unable to create type');
        }

        $this->addTypeToRoutes($type_data['id'], $old_type['id']);

        $tables = [
            'shop_type_codes',
            'shop_type_features',
            'shop_type_services',
            'shop_type_upselling',
        ];
        foreach($tables as $table) {
            $fields = [];
            foreach($type_model->query("DESCRIBE {$table}")->fetchAll() as $f) {
                if ($f['Field'] != 'type_id') {
                    $fields[] = $f['Field'];
                }
            }
            if (!$fields) {
                continue; // paranoid
            }
            $fields = join(', ', $fields);

            $type_model->exec("INSERT IGNORE INTO {$table} (type_id, {$fields})
                               SELECT ?, {$fields} FROM {$table}
                               WHERE type_id=?", [$type_data['id'], $old_type['id']]);
        }

        $this->response = $type_data;
    }

    /**
     * @param int $new_type_id
     * @param int $parent_type_id
     * @return void
     */
    protected function addTypeToRoutes($new_type_id, $parent_type_id)
    {
        $routing_path = $this->getConfig()->getPath('config', 'routing');
        if (file_exists($routing_path)) {
            $routes = include($routing_path);
            $all_types = array_map('intval', array_column((new shopTypeModel())->getAll(), 'id'));
            $need_update = false;
            foreach ($routes as $site => $site_routes) {
                if (!is_array($site_routes)) {
                    continue;
                }
                foreach ($site_routes as $route_id => $param) {
                    if (ifset($param, 'app', null) !== 'shop') {
                        continue;
                    }
                    $param_type_id = ifset($param, 'type_id', null);
                    if (is_array($param_type_id) && in_array($parent_type_id, $param_type_id)) {
                        try {
                            $routes[$site][$route_id]['type_id'] = shopSettingsTypefeatTypeSaveController::getNewRouteTypeId($param_type_id, intval($new_type_id), true, $all_types);
                            if (!$need_update && $routes[$site][$route_id]['type_id'] != $param_type_id) {
                                $need_update = true;
                            }
                        } catch (waException $e) {
                        }
                    }
                }
            }
            if ($need_update) {
                waUtils::varExportToFile($routes, $routing_path);
            }
        }
    }
}
