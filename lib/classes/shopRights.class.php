<?php

class shopRights
{
    /**
     * @see waContactRightsModel::getUsers()
     * @param $name
     * @param $value
     * @param $fields
     * @return array
     * @throws waDbException
     */
    public static function getUsers($name = 'backend', $value = 1, $fields = ['id', 'name'])
    {
        $fields = array_unique(array_merge($fields, ['firstname', 'middlename', 'lastname', 'is_user']));
        $conditions = [
            "(r.app_id = s:app_id AND r.name = s:name AND r.value >= i:value)",
            "(r.app_id = 'webasyst' AND r.name = 'backend' AND r.value > 0)",
        ];
        if ($name != 'backend') {
            $conditions[] = "(r.app_id = s:app_id AND r.name = 'backend' AND r.value > 1)";
        }
        $contact_model = new waContactModel();
        $users_right = $contact_model->query("
            SELECT DISTINCT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid, r.name, r.value 
            FROM wa_contact_rights r
            LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
            WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL)
            AND (".join(' OR ', $conditions).")
        ", [
            'app_id' => 'shop',
            'name'   => $name,
            'value'  => $value,
        ])->fetchAll('cid');

        if (!$users_right) {
            return [];
        }

        $fields = implode(',', $fields);
        $users = $contact_model->select($contact_model->escape($fields))->where('id IN (?) AND is_user >= 0', [array_keys($users_right)])->fetchAll('id');
        foreach ($users as &$_user) {
            $_user['name'] = waContactNameField::formatName($_user);
            $_user['right'] = ifset($users_right, $_user['id'], []);
        }
        unset($_user);

        return $users;
    }

    public static function getContactIds($app_id, $name = 'backend', $values = [])
    {
        if (empty($values)) {
            $values = shopRightConfig::getOrdersAccessOptions();
            $values = array_keys($values);
        }
        $right_model = new waContactRightsModel();

        return $right_model->query("
            SELECT DISTINCT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid, r.name, r.value 
            FROM wa_contact_rights r
            LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
            WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL)
            AND (r.app_id = s:app_id AND r.name = s:name AND r.value IN (i:values))
        ", [
            'app_id' => $app_id,
            'name'   => $name,
            'values' => $values,
        ])->fetchAll('cid');
    }

    public static function getAssistants()
    {
        $result = [];
        $users = shopRights::getUsers('orders', shopRightConfig::RIGHT_ORDERS_COURIER, ['id', 'name', 'photo']);
        if ($users) {
            foreach ($users as $_user) {
                if ($_user['right']['name'] == 'orders') {
                    $result[$_user['right']['value']][$_user['id']] = $_user;
                } else {
                    $result[shopRightConfig::RIGHT_ORDERS_FULL][$_user['id']] = $_user;
                }
            }
        }

        return [
            'couriers'     => ifempty($result, shopRightConfig::RIGHT_ORDERS_COURIER, []),
            'fulfillments' => ifempty($result, shopRightConfig::RIGHT_ORDERS_FULFILLMENT, []),
            'cashiers'     => ifempty($result, shopRightConfig::RIGHT_ORDERS_CASHIER, []),
            'managers'     => ifempty($result, shopRightConfig::RIGHT_ORDERS_MANAGER, []),
            'admins'       => ifempty($result, shopRightConfig::RIGHT_ORDERS_FULL, [])
        ];
    }

    public static function getUserRole($user_id)
    {
        $user = new waUser($user_id);
        if ($user->exists()) {
            $role_field = 'assigned_contact_id';
            $roles = shopRightConfig::getOrdersAccessOptions();
            $right = (int) $user->getRights('shop', 'orders');
            switch ($right) {
                case shopRightConfig::RIGHT_ORDERS_COURIER:
                    $role_field = 'courier_contact_id';
                    break;
                case shopRightConfig::RIGHT_ORDERS_FULFILLMENT:
                    $role_field = 'fulfillment_contact_id';
                    break;
                case shopRightConfig::RIGHT_ORDERS_CASHIER:
                    $role_field = 'cashier_contact_id';
                    break;
                case shopRightConfig::RIGHT_ORDERS_MANAGER:
                    $role_field = 'manager_contact_id';
                    break;
            }
            return [
                'right'      => $right,
                'role_field' => $role_field,
                'role'       => ifset($roles, $right, null)
            ];
        }

        return null;
    }

    public static function isAssistant()
    {
        $assistant_right = [
            shopRightConfig::RIGHT_ORDERS_COURIER,
            shopRightConfig::RIGHT_ORDERS_FULFILLMENT,
            shopRightConfig::RIGHT_ORDERS_CASHIER,
            shopRightConfig::RIGHT_ORDERS_MANAGER
        ];

        return in_array(wa()->getUser()->getRights('shop', 'orders'), $assistant_right);
    }
}
