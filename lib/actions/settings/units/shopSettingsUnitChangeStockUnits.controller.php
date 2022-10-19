<?php

class shopSettingsUnitChangeStockUnitsController extends waJsonController
{
    public function execute()
    {
        if (!shopLicensing::isPremium()) {
            $this->response['changed'] = false;
            return;
        }

        $stock_units = waRequest::post('stock_units', null, waRequest::TYPE_INT) ? 1 : null;
        $app_settings = new waAppSettingsModel();
        if (empty($stock_units) && wa()->getSetting('stock_units_enabled')) {
            $params = [
                'types' => [
                    'stock_unit_fixed' => shopTypeModel::PARAM_DISABLED,
                    'stock_unit_id' => shopTypeModel::PARAM_ALL_PRODUCTS,
                    'base_unit_fixed' => shopTypeModel::PARAM_DISABLED,
                    'base_unit_id' => 'NULL',
                    'stock_base_ratio' => 1,
                    'stock_base_ratio_fixed' => shopTypeModel::PARAM_DISABLED,
                ],
                'products' => [
                    'stock_unit_id' => shopTypeModel::PARAM_ALL_PRODUCTS,
                    'base_unit_id' => 'p.stock_unit_id',
                    'stock_base_ratio' => shopTypeModel::PARAM_ONLY_TYPES
                ],
                'product_skus' => [
                    'stock_base_ratio' => 'NULL'
                ]
            ];
            $type_model = new shopTypeModel();
            $type_model->updateFractionalParams($params);

            $app_settings->set('shop', 'base_units_enabled', null);
        }

        $app_settings->set('shop', 'stock_units_enabled', $stock_units);
        $this->response['changed'] = true;
    }
}