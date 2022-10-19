<?php

class shopSettingsUnitChangeBaseUnitsController extends waJsonController
{
    public function execute()
    {
        if (!shopLicensing::isPremium()) {
            $this->response['changed'] = false;
            return;
        }

        $base_units = waRequest::post('base_units', null, waRequest::TYPE_INT) ? 1 : null;
        if (empty($base_units) && wa()->getSetting('base_units_enabled')) {
            $params = [
                'types' => [
                    'base_unit_fixed' => shopTypeModel::PARAM_DISABLED,
                    'base_unit_id' => 'NULL',
                    'stock_base_ratio' => 1,
                    'stock_base_ratio_fixed' => shopTypeModel::PARAM_DISABLED,
                ],
                'products' => [
                    'base_unit_id' => 'p.stock_unit_id',
                    'stock_base_ratio' => shopTypeModel::PARAM_ONLY_TYPES
                ],
                'product_skus' => [
                    'stock_base_ratio' => 'NULL'
                ]
            ];
            $type_model = new shopTypeModel();
            $type_model->updateFractionalParams($params);
        }

        $app_settings = new waAppSettingsModel();
        $app_settings->set('shop', 'base_units_enabled', $base_units);
        $this->response['changed'] = true;
    }
}