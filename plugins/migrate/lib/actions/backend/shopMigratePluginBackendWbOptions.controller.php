<?php

class shopMigratePluginBackendWbOptionsController extends waJsonController
{
    public function execute()
    {
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $settings = new shopMigratePluginWbSettings();
            $options = array();
            $fields = array(
                'mode'               => waRequest::TYPE_STRING_TRIM,
                'log_mode'           => waRequest::TYPE_STRING_TRIM,
                'feature_mode'       => waRequest::TYPE_STRING_TRIM,
                'feature_force_text' => waRequest::TYPE_INT,
                'image_mode'         => waRequest::TYPE_STRING_TRIM,
            );
            foreach ($fields as $field => $type) {
                if (waRequest::issetPost($field)) {
                    $options[$field] = waRequest::post($field, null, $type);
                }
            }
            if ($options) {
                $settings->saveImportOptions($options);
            }
            $this->response = $settings->getImportOptions();
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }
}
