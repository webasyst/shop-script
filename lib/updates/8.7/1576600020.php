<?php

#grant rights to everybody, who has access to orders

$rights_model = new waContactRightsModel();

$sql = <<<SQL
SELECT
       `group_id` 
FROM `wa_contact_rights` 
WHERE 
      `app_id`='shop'
      AND 
      `name`='orders' 
      AND `value`
SQL;
$ids = $rights_model->query($sql)->fetchAll('group_id');
$data = array(
    'group_id' => array_keys($ids),
    'app_id'   => 'shop',
    'name'     => 'workflow_actions.all',
    'value'    => 1,
);
$rights_model->multipleInsert($data);
