<?php
/** @since 12.8.0 */
class shopUserGetListMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = false;

    public function execute()
    {
        $users = shopRights::getUsers('orders', shopRightConfig::RIGHT_ORDERS_COURIER);

        $role_by_value = [
            shopRightConfig::RIGHT_ORDERS_COURIER => 'courier',
            shopRightConfig::RIGHT_ORDERS_FULFILLMENT => 'fulfillment',
            shopRightConfig::RIGHT_ORDERS_CASHIER => 'cashier',
            shopRightConfig::RIGHT_ORDERS_MANAGER => 'manager',
            shopRightConfig::RIGHT_ORDERS_FULL => 'manager',
        ];

        foreach ($users as &$_user) {
            if ($_user['right']['name'] == 'orders') {
                $_user['role'] = ifset($role_by_value, $_user['right']['value'], 'admin');
            } else {
                $_user['role'] = 'admin';
            }
            unset($_user['right'], $_user['is_user']);
        }
        unset($_user);

        $this->response = array_values($users);
    }
}
