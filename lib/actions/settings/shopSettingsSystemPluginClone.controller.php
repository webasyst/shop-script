<?php

/**
 * Class shopSettingsShippingCloneController
 *
 */
class shopSettingsSystemPluginCloneController extends waJsonController
{
    public function execute()
    {
        $original_id = waRequest::post('original_id', '0', waRequest::TYPE_STRING);
        $type = waRequest::post('type', null, waRequest::TYPE_STRING);

        if (!$type || ($type !== 'shipping' && $type !== 'payment')) {
            $this->errors[] = _w('Invalid plugin type.');
            return null;
        }

        $shop_plugin_model = new shopPluginModel();
        $shop_plugin_settings = new shopPluginSettingsModel();

        $original_plugin = $shop_plugin_model->getByField([
            'id'   => $original_id,
            'type' => $type
        ]);

        if (!$original_plugin) {
            $this->errors[] = _w('Shipping plugin not found.');
            return null;
        }

        //get sort value
        $sort = $shop_plugin_model->select('MAX(`sort`)+1')->where("`type` = '{$type}'")->fetchField();

        $new_plugin = $original_plugin;

        //remove bad id and turn off plugin
        unset($new_plugin['id']);
        $new_plugin['status'] = '0';
        $new_plugin['sort'] = (int)$sort;

        $new_id = $shop_plugin_model->insert($new_plugin);

        if ($new_id) {
            $original_settings = $shop_plugin_settings->getByField('id', $original_id, true);
            if ($original_settings) {
                $new_settings = [];
                foreach ($original_settings as $settings) {
                    $settings['id'] = $new_id;
                    $new_settings[] = $settings;
                }
                $shop_plugin_settings->multipleInsert($new_settings);
            }
        } else {
            $this->errors[] = 'Plugin cloning error';
        }

        $this->response['plugin_id'] = $new_id;
    }
}
