<?php

class shopProductPageParamsModel extends waModel
{
    protected $table = 'shop_product_page_params';

    public function getParams($page_id)
    {
        return $this->query("SELECT name, value FROM {$this->table}
            WHERE name IN ('description', 'keywords') AND page_id = ".(int)$page_id)->fetchAll('name', true);
    }

    public function setParams($page_id, $params)
    {
        $update = array();
        $delete = array();

        $old_params = $this->getByField('page_id', $page_id, true);
        foreach ($old_params as $k => $old_param) {
            $name = $old_param['name'];
            if (isset($params[$name])) {
                if ($old_param['value'] != $params[$name]) {
                    $this->updateByField(array('page_id' => $page_id, 'name' => $name), array('value' => $params[$name]));
                } else {
                    unset($params[$name]);
                }
            } else {
                $delete[] = $name;
            }
        }

        $add = array();
        foreach ($params as $name => $value) {
            $add[] = array('page_id' => $page_id, 'name' => $name, 'value' => $value);
        }
        if (!empty($add)) {
            $this->multipleInsert($add);
        }

        if (!empty($delete)) {
            $this->query("DELETE FROM {$this->table} WHERE page_id = ".(int)$page_id." AND name IN ('".implode("','", $delete)."')");
        }
    }
}