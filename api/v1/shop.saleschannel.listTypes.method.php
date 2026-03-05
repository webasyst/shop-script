<?php

class shopSaleschannelListTypesMethod extends shopApiMethod
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', _w('Access denied.'), 403);
        }

        $types = shopSalesChannelType::getEnabledTypes();
        foreach ($types as &$type) {
            $type = self::formatSalesChannelType($type);
        }
        unset($type);

        $this->response = [
            'types' => $types,
        ];
    }

    public static function formatSalesChannelType($type)
    {
        $schema = [
            'id' => 'string',
            'name' => 'string',
            'menu_icon' => 'string',
            'available' => 'boolean',
            //'class' => 'string',
        ];
        $type = shopFrontApiFormatter::formatFieldsToType($type, $schema);
        return array_intersect_key($type, $schema);
    }
}
