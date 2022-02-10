<?php

class shopSettingsUnitChangeTypeController extends waJsonController
{
    const TYPE_FRACTIONAL = 1;
    const TYPE_WHOLE = null;

    public function execute()
    {
        if (!shopLicensing::isPremium()) {
            $this->response['changed'] = false;
            return;
        }

        $unit_type = waRequest::post('unit_type', null, waRequest::TYPE_INT) ? self::TYPE_FRACTIONAL : self::TYPE_WHOLE;
        if ($unit_type == self::TYPE_WHOLE) {
            $product_model = new shopProductModel();
            $product_error = $product_model->select('1 error')
                ->where('count_denominator != 1')->limit(1)->fetchField('error');
            $type_model = new shopTypeModel();
            $type_error = $type_model->select('1 error')
                ->where('count_denominator_fixed != 2')->limit(1)->fetchField('error');
            if ($product_error || $type_error) {
                $this->errors[] = [
                    'id' => 'unit_type',
                    'text' => _w('Not all conditions are satisfied.')
                ];
                return;
            }
            if (shopFrac::isEnabled()) {
                $params = [
                    'types' => [
                        'count_denominator_fixed' => shopTypeModel::PARAM_DISABLED,
                        'count_denominator' => shopTypeModel::PARAM_ONLY_TYPES,
                    ],
                    'products' => [
                        'count_denominator' => shopTypeModel::PARAM_ONLY_TYPES
                    ],
                ];
                $type_model = new shopTypeModel();
                $type_model->updateFractionalParams($params);
            }
        }

        $app_settings = new waAppSettingsModel();
        $app_settings->set('shop', 'frac_enabled', $unit_type);
        $this->response['changed'] = true;
    }
}
