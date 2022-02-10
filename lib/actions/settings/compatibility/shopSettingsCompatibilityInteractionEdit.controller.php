<?php
/**
 * Edit compatibility interaction
 */
class shopSettingsCompatibilityInteractionEditController extends waJsonController
{
    public function execute()
    {
        $data = $this->getData();

        $this->validateData($data);

        if (!$this->errors) {
            $app_settings_model = new waAppSettingsModel();
            $setting_name = sprintf('%s.%s', $data['plugin_type'], $data['plugin_id']);
            foreach ($data['mode'] as $mode => $value) {
                $app_settings_model->set('shop', $setting_name . '.' . $mode, $value);
            }
        }
    }

    /**
     * @param array $data
     * @return void
     */
    private function validateData($data)
    {
        if ($data['plugin_type'] != shopPluginModel::TYPE_PAYMENT && $data['plugin_type'] != shopPluginModel::TYPE_SHIPPING) {
            $this->errors[] = [
                'id'   => 'plugin_type',
                'text' => _w('Invalid plugin type.')
            ];
        }

        foreach ($data['mode'] as $mode) {
            if ($mode != shopFrac::PLUGIN_TRANSFER_CONVERT && $mode != shopFrac::PLUGIN_TRANSFER_DISABLED && $mode !== '') {
                $this->errors[] = [
                    'id'   => 'plugin_mode',
                    'text' => _w('Compatibility mode is unavailable.')
                ];
            }
        }
    }

    /**
     * @return array
     */
    private function getData()
    {
        return [
            'plugin_id' => waRequest::post('plugin_id', '', waRequest::TYPE_STRING_TRIM),
            'plugin_type' => waRequest::post('plugin_type', '', waRequest::TYPE_STRING_TRIM),
            'mode' => [
                'frac_mode' => waRequest::post('frac_mode', '', waRequest::TYPE_STRING_TRIM),
                'units_mode' => waRequest::post('units_mode', '', waRequest::TYPE_STRING_TRIM),
            ],
        ];
    }
}
