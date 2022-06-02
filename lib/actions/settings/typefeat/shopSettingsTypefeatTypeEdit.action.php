<?php
/**
 * Type editor dialog HTML.
 */
class shopSettingsTypefeatTypeEditAction extends waViewAction
{
    public function execute()
    {
        $type_id = waRequest::request('type', '', waRequest::TYPE_STRING);

        $type_model = new shopTypeModel();
        $type_templates = [];
        if ($type_id) {
            $type = $type_model->getById($type_id);
            if (!$type) {
                throw new waException('Not found', 404);
            }
        } else {
            $type = $type_model->getEmptyRow();

            // New type can be created from a template
            $type_templates = (array)shopTypeModel::getTemplates();
        }

        $icons = (array)$this->getConfig()->getOption('type_icons');

        $type['icon_url'] = '';
        $type['icon_class'] = $type['icon'];
        if (false !== strpos($type['icon'], '/')) {
            $type['icon_url'] = $type['icon'];
            $type['icon_class'] = '';
        }

        if ($type['icon_class'] && !in_array($type['icon_class'], $icons)) {
            $icons[] = $type['icon_class'];
        } else if (empty($type['id'])) {
            $type['icon_class'] = reset($icons);
        }

        $storefronts = [];
        $count_storefronts = 0;
        $shop_routes = wa()->getRouting()->getByApp('shop');
        $count_all_storefronts = count(shopStorefrontList::getAllStorefronts());

        foreach ($shop_routes as $domain => $stores) {
            foreach ($stores as $store_id => $param) {
                if (is_array($param['type_id']) && count($param['type_id']) > 0) {
                    $is_checked = in_array($type_id, $param['type_id']);
                } else {
                    $is_checked = in_array($param['type_id'], [null, [], false, '', '0', 0], true);
                }
                $storefronts[] = [
                    'url'        => $param['url'],
                    'domain'     => $domain,
                    'is_checked' => $is_checked
                ];
                $count_storefronts = ($is_checked ? ++$count_storefronts : $count_storefronts);
            }
        }

        $fractional = $this->getTypeFractional($type);
        // $fractional["stock_unit"]["status"] = false;

        $this->view->assign([
            'all_storefronts_is_checked' => ( $count_storefronts === $count_all_storefronts ),
            'type_templates'             => $type_templates,
            'storefronts'                => $storefronts,
            'icons'                      => $icons,
            'type'                       => $type,
            "is_premium"                 => shopLicensing::isPremium(),
            "fractional"                 => $fractional,
        ]);
    }

    public static function getTypeFractional($type) {
        // дробные юниты
        $unit_model = new shopUnitModel();
        $_units = $unit_model->getAll('id');
        $fractional_units = [];
        foreach ($_units as $_unit) {
            if ($_unit['status'] !== '0') {
                $short_name = (!empty($_unit["storefront_name"]) ? $_unit["storefront_name"] : $_unit["short_name"]);
                $fractional_units[] = [
                    "value" => (string)$_unit["id"],
                    "name" => $_unit["name"],
                    "name_short" => $short_name,
                ];
            }
        }

        $denominators = [
            [
                "name" => "1",
                "value" => "1"
            ],
            [
                "name" => "0.1",
                "value" => "10"
            ],
            [
                "name" => "0.01",
                "value" => "100"
            ],
            [
                "name" => "0.001",
                "value" => "1000"
            ]
        ];

        if (!$type) {
            // Paranoid mode: make sure code does not throw notices
            $type_model = new shopTypeModel();
            $type = $type_model->getEmptyRow();
        }

        // значение поля "default" = (string) - Значение поля при отключении параметров (поумолчанию пустота)
        // значение поля "value" = (string) - Значение поля или селекта: значение по умолчанию для товаров этого типа
        // значение поля "status" = (boolean) true|false — Активно на уровне магазина или задизейблено
        // значение поля "enabled" = (boolean) true|false — Значение переключателя: включено ли на уровне типа
        // значение поля "editable" = (boolean) true|false - Значение чекбокса: разрешено ли менять на уровне товара

        return [
            "units"             => $fractional_units,
            "denominators"      => $denominators,
            "stock_unit"        => [
                "default"  => reset($fractional_units)["value"],
                "value"    => (string) $type['stock_unit_id'],
                "status"   => (bool) wa()->getSetting('stock_units_enabled', '', 'shop'),
                "enabled"  => $type['stock_unit_fixed'] < 2,
                "editable" => $type['stock_unit_fixed'] < 1,
            ],

            "base_unit"         => [
                "default"   => "",
                "value"    => (string)$type['base_unit_id'],
                "status"   => (bool) wa()->getSetting('base_units_enabled', '', 'shop'),
                "enabled"  => $type['base_unit_fixed'] < 2,
                "editable" => $type['base_unit_fixed'] < 1,
            ],

            "stock_base_ratio"  => [
                "default"   => "1",
                "value"    => floatval($type['stock_base_ratio']),
                "status"   => (bool) wa()->getSetting('base_units_enabled', '', 'shop'),
                "enabled"  => $type['stock_base_ratio_fixed'] < 2, // 2 бывает только вместе с base_unit_fixed == 2.
                "editable" => true //$type['stock_base_ratio_fixed'] < 1,  // в интерфейсе editable галочкой поменять нельзя, но на уровне Бд предусмотрено
            ],

            "count_denominator" => [
                "default"   => "1",
                "value"    => (string)$type['count_denominator'],
                "status"   => (bool) shopFrac::isEnabled(),
                "enabled"  => $type['count_denominator_fixed'] < 2,
                "editable" => $type['count_denominator_fixed'] < 1,
            ],

            "order_multiplicity_factor" => [
                "default"   => "1",
                "value"    => (string)$type['order_multiplicity_factor'],
                "status"   => (bool) shopFrac::isEnabled(),
                "enabled"  => $type['order_multiplicity_factor_fixed'] < 2,
                "editable" => $type['order_multiplicity_factor_fixed'] < 1,
            ],

            "order_count_min"   => [
                "default"   => "1",
                "value"    => floatval($type['order_count_min']),
                "status"   => (bool) shopFrac::isEnabled(),
                "enabled"  => $type['order_count_min_fixed'] < 2,
                "editable" => $type['order_count_min_fixed'] < 1,  // в интерфейсе editable галочкой поменять нельзя, но на уровне Бд предусмотрено
            ],

            "order_count_step"  => [
                "default"   => "1",
                "value"     => floatval($type['order_count_step']),
                "status"   => (bool) shopFrac::isEnabled(),
                "enabled"  => $type['order_count_step_fixed'] < 2,
                "editable" => $type['order_count_step_fixed'] < 1, // в интерфейсе editable галочкой поменять нельзя, но на уровне Бд предусмотрено
            ],
        ];
    }
}
