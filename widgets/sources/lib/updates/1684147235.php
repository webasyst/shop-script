<?php

$widget_model = new waWidgetModel();
$widgets = $widget_model->getByField([
    'widget' => 'sources',
    'app_id' => 'shop',
], 'id');

if ($widgets) {
    $params_model = new waWidgetSettingsModel();
    $params_model->updateByField([
        'widget_id' => array_keys($widgets),
        'name' => 'metric',
        'value' => 'orders'
    ], ['value' => 'order_count']);
}