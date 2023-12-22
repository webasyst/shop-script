<?php
/**
 * List of measurement units on the settings page
 */
class shopSettingsUnitAction extends waViewAction
{
    public function execute()
    {
        $units = $this->getUnits();

        /* выпилить "штуки" */
        unset($units[0]);

        $type_model = new shopTypeModel();
        $types = $type_model->select('*')
            ->where('count_denominator_fixed != 2 OR stock_unit_fixed != 2 OR base_unit_fixed != 2')->fetchAll('id');

        $types_with_fractional = [];
        $types_with_stock_units = [];
        $types_with_base_units = [];
        $types_with_order_count_min = [];
        $types_with_order_count_step = [];
        foreach ($types as $key => $type) {
            if ($type['count_denominator_fixed'] != 2) {
                $types_with_fractional[$key] = $type;
            }
            if ($type['order_count_min_fixed'] != 2) {
                $types_with_order_count_min[$key] = $type;
            }
            if ($type['order_count_step_fixed'] != 2) {
                $types_with_order_count_step[$key] = $type;
            }
            if ($type['stock_unit_fixed'] != 2) {
                $types_with_stock_units[$key] = $type;
            }
            if ($type['base_unit_fixed'] != 2) {
                $types_with_base_units[$key] = $type;
            }
        }

        $this->view->assign([
            'frac_enabled' => shopFrac::isEnabled(),
            'stock_units_enabled' => wa()->getSetting('stock_units_enabled'),
            'base_units_enabled' => wa()->getSetting('base_units_enabled'),
            'types_with_fractional' => $types_with_fractional,
            'types_with_order_count_min' => $types_with_order_count_min,
            'types_with_order_count_step' => $types_with_order_count_step,
            'types_with_stock_units' => $types_with_stock_units,
            'types_with_base_units' => $types_with_base_units,
            'units' => $units,
            'is_teaser' => shopLicensing::isStandard(),
        ]);
    }

    protected function getUnits()
    {
        $units_model = new shopUnitModel();
        $units = $units_model->getAll();
        $units_used = $units_model->getUsedUnit();

        foreach ($units as &$unit) {
            $unit['locked'] = false;
            if ($unit['status'] === '1' && in_array($unit['id'], $units_used)) {
                $unit['locked'] = true;
            }
        }

        return $units;
    }
}
