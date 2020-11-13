<?php

class shopSettingsSaveStockController extends waJsonController
{
    public function execute()
    {
        // Read data from POST
        $stocks_add = $this->getAddData();
        $stocks_edit = $this->getEditData();
        $virtualstocks_add = $this->getAddData('vadd');
        $virtualstocks_edit = $this->getEditData('vedit');

        // Insert 'sort' field into items of four arrays above
        $stocks_order = waRequest::post('stocks_order');
        if ($stocks_order) {
            $sort = $iadd = $ivadd = 0;
            foreach (explode(',', $stocks_order) as $id) {
                if ($id && $id[0] == 'v') {
                    $id = substr($id, 1);
                    if ($id) {
                        if (!isset($virtualstocks_edit[$id])) {
                            $virtualstocks_edit[$id] = array();
                        }
                        $virtualstocks_edit[$id]['sort'] = $sort;
                    } elseif (isset($virtualstocks_add[$ivadd])) {
                        $virtualstocks_add[$ivadd]['sort'] = $sort;
                        $ivadd++;
                    }
                } else {
                    if ($id) {
                        if (!isset($stocks_edit[$id])) {
                            $stocks_edit[$id] = array();
                        }
                        $stocks_edit[$id]['sort'] = $sort;
                    } elseif (isset($stocks_add[$iadd])) {
                        $stocks_add[$iadd]['sort'] = $sort;
                        $iadd++;
                    }
                }
                $sort++;
            }
        }

        // Update existing stocks
        $stock_model = new shopStockModel();
        foreach ($stocks_edit as $id => $item) {
            $stock_model->updateById($id, $item);
        }

        // Create new stocks
        $inventory_stock_id = null;
        foreach ($stocks_add as $item) {
            $id = $stock_model->add($item);
            if (!empty($item['inventory'])) {
                $inventory_stock_id = $id;
            }
        }

        // Update existing virtual stocks
        $virtualstock_model = new shopVirtualstockModel();
        $virtualstock_stocks_model = new shopVirtualstockStocksModel();
        foreach ($virtualstocks_edit as $id => $item) {
            $virtualstock_model->updateById($id, $item);
            if (!empty($item['substocks'])) {
                $virtualstock_stocks_model->set($id, $item['substocks']);
            }
        }

        // Create new virtual stocks
        foreach ($virtualstocks_add as $item) {
            if (!empty($item['substocks'])) {
                $id = $virtualstock_model->add($item);
                $virtualstock_stocks_model->set($id, $item['substocks']);
            }
        }

        // Save settings
        $app_id = $this->getAppId();
        $app_settings_model = new waAppSettingsModel();
        if (waRequest::post('ignore_stock_count')) {
            $app_settings_model->set($app_id, 'ignore_stock_count', 1);
        } else {
            $app_settings_model->set($app_id, 'ignore_stock_count', 0);
            if (waRequest::post('limit_main_stock')) {
                $app_settings_model->set($app_id, 'limit_main_stock', 1);
            } else {
                $app_settings_model->del($app_id, 'limit_main_stock');
            }
        }

        $stock_counting = waRequest::post('stock_counting_action');
        switch ($stock_counting) {
            case 'create':
                $app_settings_model->set($app_id, 'update_stock_count_on_create_order', 1);
                $app_settings_model->set($app_id, 'disable_stock_count', 0);
                break;
            case 'processing':
                $app_settings_model->set($app_id, 'update_stock_count_on_create_order', 0);
                $app_settings_model->set($app_id, 'disable_stock_count', 0);
                break;
            case 'none':
                $app_settings_model->set($app_id, 'disable_stock_count', 1);
                break;
        }

        // Assign all inventory to new stock, if specified
        if ($inventory_stock_id) {
            $product_stocks_model = new shopProductStocksModel();
            $product_stocks_model->insertFromSkus($inventory_stock_id);
        }
    }

    public function getEditData($field = 'edit')
    {
        $data = array();
        $ids = array();
        $fields = waRequest::post($field, array());

        foreach ($fields as $name => $items) {
            foreach ($items as $k => $value) {
                if ($name == 'id') {
                    $ids[$k] = (int)$value;
                } elseif ($name == 'public') {
                    continue;
                } else {
                    $data[$k][$name] = $value;
                }
            }
        }
        if (!empty($data)) {
            $data = array_combine($ids, $data);
        }

        //Set stocks visible
        if (isset($fields['public'])) {
            foreach ($fields['public'] as $id) {
                if (isset($data[$id])){
                    $data[$id]['public'] = 1;
                }
            }
        }

        $this->correct($data);
        return $data;
    }

    public function getAddData($field = 'add')
    {
        $add = array();
        foreach (waRequest::post($field, array()) as $name => $items) {
            foreach ($items as $k => $value) {
                if ($name != 'inventory') {
                    $add[$k][$name] = $value;
                }
            }
        }
        $post_field = waRequest::post($field);
        if (isset($post_field['inventory'])) {
            end($add);
            $add[key($add)]['inventory'] = true;
        }
        $this->correct($add);
        return $add;
    }

    public function correct(&$data)
    {
        foreach ($data as &$item) {
            if (!is_numeric($item['low_count'])) {
                $low_count = (int)$item['low_count'];
                if (!$low_count) {
                    $low_count = shopStockModel::LOW_DEFAULT;
                }
            } else {
                $low_count = $item['low_count'];
                if ($low_count < 0) {
                    $low_count = shopStockModel::LOW_DEFAULT;
                }
            }
            if (!is_numeric($item['critical_count'])) {
                $critical_count = (int)$item['critical_count'];
                if (!$critical_count) {
                    $critical_count = shopStockModel::CRITICAL_DEFAULT;
                }
            } else {
                $critical_count = $item['critical_count'];
                if ($critical_count < 0) {
                    $critical_count = shopStockModel::CRITICAL_DEFAULT;
                }
            }
            if ($low_count < $critical_count) {
                list ($low_count, $critical_count) = array($critical_count, $low_count);
            }
            $item['low_count'] = $low_count;
            $item['critical_count'] = $critical_count;
            if (empty($item['public'])) {
                $item['public'] = 0;
            } else {
                $item['public'] = 1;
            }
            if (!empty($item['substocks'])) {
                $item['substocks'] = array_filter(explode(',', $item['substocks']), 'wa_is_int');
            }
            if (empty($item['substocks'])) {
                unset($item['substocks']);
            }
        }
        unset($item);
    }
}
