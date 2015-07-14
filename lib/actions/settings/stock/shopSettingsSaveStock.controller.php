<?php

class shopSettingsSaveStockController extends waJsonController
{
    public function execute()
    {
        $model = new shopStockModel();
        foreach ($this->getEditData() as $id => $item) {
            $model->updateById($id, $item);
        }

        $inventory_stock_id = null;

        foreach ($this->getAddData() as $before_id => $data) {
            foreach ($data as $item) {
                $id = $model->add($item, $before_id);
                if (!empty($item['inventory'])) {
                    $inventory_stock_id = $id;
                }
            }
        }

        if ($inventory_stock_id) {
            // Assign all inventory to this stock
            $product_stocks_model = new shopProductStocksModel();
            $product_stocks_model->insertFromSkus($inventory_stock_id);
        }

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
        if (waRequest::post('update_stock_count_on_create_order')) {
            $app_settings_model->set($app_id, 'update_stock_count_on_create_order', 1);
        } else {
            $app_settings_model->set($app_id, 'update_stock_count_on_create_order', 0);
        }
    }

    public function getEditData()
    {
        $data = array();
        $ids = array();
        foreach (waRequest::post('edit', array()) as $name => $items) {
            foreach ($items as $k => $value) {
                if ($name == 'id') {
                    $ids[$k] = (int)$value;
                } else {
                    $data[$k][$name] = $value;
                }
            }
        }
        if (!empty($data)) {
            $data = array_combine($ids, $data);
        }
        $this->correct($data);
        return $data;
    }

    public function getAddData()
    {
        $add = array();
        foreach (waRequest::post('add', array()) as $name => $items) {
            foreach ($items as $k => $value) {
                $add[$k][$name] = $value;
            }
        }
        $this->correct($add);


        // group by before_id values
        $data = array();
        foreach ($add as $item) {
            $before_id = (int)$item['before_id'];
            if (!isset($data[$before_id])) {
                $data[$before_id] = array();
            }
            unset($item['before_id']);
            $data[$before_id][] = $item;
        }
        return $data;
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
        }
        unset($item);
    }
}