<?php

class shopProductPagesModel extends waModel
{
    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;

    protected $table = 'shop_product_pages';

    public function get($id)
    {
        $page = $this->getById($id);
        if ($page) {
            $params_model = new shopProductPageParamsModel();
            $page['params'] = $params_model->getParams($id);
        }
        return $page;
    }

    public function getPages($product_id = null, $only_public = false)
    {
        return $this->query("SELECT * FROM {$this->table}".
            ($product_id ? " WHERE product_id = ".(int)$product_id.($only_public ? " AND status = 1" : '') : '').
            " ORDER BY ".(!$product_id ? "product_id, " : '')."sort")->
        fetchAll('id');
    }


    public function count($product_id = null)
    {
        return $this->query("SELECT COUNT(id) cnt FROM {$this->table}".
            ($product_id ? " WHERE product_id = ".(int)$product_id : ''))->
        fetchField('cnt');
    }

    public function update($id, $data)
    {
        $data['update_datetime'] = date('Y-m-d H:i:s');
        $result = $this->updateById($id, $data);
        if ($result && !empty($data['params'])) {
            $params_model = new shopProductPageParamsModel();
            $params_model->setParams($id, $data['params']);
        }
        return $result;
    }

    public function add($data)
    {
        if (empty($data['product_id'])) {
            return false;
        }
        $product_id = (int)$data['product_id'];
        $data['create_datetime'] = date('Y-m-d H:i:s');
        $sort = (int)$this->query("SELECT MAX(sort) sort FROM `{$this->table}` WHERE product_id = $product_id ")->fetchField('sort');
        $data['sort'] = $sort;
        $id = $this->insert($data);
        if ($id && !empty($data['params'])) {
            $params_model = new shopProductPageParamsModel();
            $params_model->setParams($id, $data['params']);
        }
        return $id;
    }

    public function delete($id)
    {
        if ($this->deleteById($id)) {
            $params_model = new shopProductPageParamsModel();
            $params_model->deleteByField('page_id', $id);
            return true;
        }
        return false;
    }

    public function move($id, $before_id = null)
    {
        if (!$before_id) {
            $item = $this->getById($id);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT MAX(sort) sort FROM {$this->table}")->fetchField('sort') + 1;
            $this->updateById($id, array('sort' => $sort));
        } else {
            $before_id = (int)$before_id;
            $items = $this->query("SELECT * FROM {$this->table} WHERE id IN ($id, $before_id)")->fetchAll('id');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_id]['sort'];
            $this->query("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= $sort");
            $this->updateById($id, array('sort' => $sort));
        }
        return true;
    }

    public function getPreviewHash()
    {
        $app_settings_model = new waAppSettingsModel();
        $hash = $app_settings_model->get('shop', 'preview_hash');
        if ($hash) {
            $hash_parts = explode('.', $hash);
            if (time() - $hash_parts[1] > 14400) {
                $hash = '';
            }
        }
        if (!$hash) {
            $hash = uniqid().'.'.time();
            $app_settings_model->set('shop', 'preview_hash', $hash);
        }
        return md5($hash);
    }
}