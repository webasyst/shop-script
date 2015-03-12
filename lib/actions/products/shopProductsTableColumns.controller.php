<?php
/** Save selected columns for products list table. */
class shopProductsTableColumnsController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException('Access denied');
        }

        $columns = waRequest::post('columns', array(), 'array');
        $columns = array_keys($columns);

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'list_columns', join(',', $columns));
    }
}
