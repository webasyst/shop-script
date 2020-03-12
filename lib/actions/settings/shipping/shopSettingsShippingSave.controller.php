<?php

class shopSettingsShippingSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin = waRequest::post('shipping')) {
            try {
                if (!isset($plugin['settings'])) {
                    $plugin['settings'] = array();
                }
                shopShipping::savePlugin($plugin);

                $is_edit = $plugin['id'] > 0;

                $log_params = array(
                    'id' => $plugin['id'],
                    'status' => !empty($plugin['status']),
                    'plugin' => $plugin['plugin']
                );

                if ($is_edit) {
                    $this->logAction('shipping_plugin_edit', $log_params);
                } else {
                    $this->logAction('shipping_plugin_add', $log_params);
                }

                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        } elseif ($params = waRequest::post('params')) {

            $errors = array();
            $this->response['message'] = _w('Saved');
            if (!empty($params['dimensions'])) {
                $shipping_dimensions = $params['dimensions'];
            } else {
                $dimensions = array(
                    'height' => _w('Product height'),
                    'width'  => _w('Product width'),
                    'length' => _w('Product length'),
                );
                $shipping_dimensions = array();

                foreach ($dimensions as $dimension => $name) {
                    if (!empty($params[$dimension]) && !in_array($params[$dimension], $shipping_dimensions)) {
                        $shipping_dimensions[] = $params[$dimension];
                    } else {
                        $errors[$dimension] = $name;
                    }
                }
                if ($errors) {
                    $shipping_dimensions = false;
                } else {
                    $shipping_dimensions = implode('.', $shipping_dimensions);
                }
            }

            $app_settings = new waAppSettingsModel();
            if ($shipping_dimensions !== false) {

                $app_settings->set('shop', 'shipping_dimensions', $shipping_dimensions);
            } else {
                $shipping_dimensions = $app_settings->get('shop', 'shipping_dimensions');
            }

            if ($shipping_dimensions) {
                $shipping_dimensions = preg_split('@\D+@', $shipping_dimensions);
            } else {
                $shipping_dimensions = array();
            }

            $status = array();

            if ((count($shipping_dimensions) == 1) || (count($shipping_dimensions) == 3)) {
                $status['dimensions'] = 'valid';
            } else {
                $status['dimensions'] = 'invalid';
            }
            $id = ifset($params, 'shipping_package_provider', false);
            $app_settings->set('shop', 'shipping_package_provider', $id);
            $status['shipping_package_provider'] = $id ? 'valid' : 'invalid';

            if ($errors) {
                $this->setError(sprintf(
                    _w('Parameters are not specified: %s'),
                    implode(', ', $errors)
                ));
            } else {
                $this->response['params'] = $status;
            }
            shopShipping::flushCache();
        }
    }
}
