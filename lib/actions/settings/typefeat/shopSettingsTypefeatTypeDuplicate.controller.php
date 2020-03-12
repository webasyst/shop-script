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
}
