<?php

$rights_model = new waContactRightsModel();
$groups = $rights_model->getByField([
    'app_id' => 'shop',
    'value' => 1
], true);

$new_rights = [];
$has_rights = [];
foreach ($groups as $group) {
    if ($group['name'] == 'products') {
        $has_rights[$group['group_id']] = $group['group_id'];
        if (isset($new_rights[$group['group_id']])) {
            unset($new_rights[$group['group_id']]);
        }
        continue;
    }

    if ($group['name'] == 'backend' && !isset($new_rights[$group['group_id']]) && !isset($has_rights[$group['group_id']])) {
        $new_rights[$group['group_id']] = [
            'group_id' => $group['group_id'],
            'app_id' => 'shop',
            'name' => 'products',
            'value' => '1',
        ];
    }
}

if ($new_rights) {
    $rights_model->multipleInsert(array_values($new_rights));
}
