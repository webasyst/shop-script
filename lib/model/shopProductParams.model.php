<?php

class shopProductParamsModel extends waModel implements shopProductStorageInterface
{
    protected $table = 'shop_product_params';

    /**
     * Get custom params of product
     * @param int $id product ID
     * @return array params in key=>value format
     */
    public function get($id)
    {
        $params = array();
        foreach ($this->getByField('product_id', $id, true) as $p) {
            $params[$p['name']] = $p['value'];
        }
        return $params;
    }


    /**
     * Set custom params to product
     *
     * @param int $id product ID
     * @param array|null $params key=>value format of array or null (to delete all params assigned to product)
     * @return bool
     */
    public function set($id, $params = array())
    {
        if ($id) {
            // remove if params is null
            if (is_null($params)) {
                return $this->deleteByField(array(
                    'product_id' => $id
                ));
            }

            // candidate to delete
            $delete_params = $this->get($id);

            // accumulate params to add (new params) and update old params
            $add_params = array();
            foreach ($params as $name => $value) {
                if (isset($delete_params[$name])) {
                    // update old param
                    $this->updateByField(array(
                        'product_id' => $id,
                        'name' => $name
                    ), array(
                        'value' => $value
                    )
                    );
                    // remove from candidate to delete
                    unset($delete_params[$name]);
                } else {
                    // param to add
                    $add_params[] = array(
                        'product_id' => $id,
                        'name' => $name,
                        'value' => $value
                    );
                }
            }

            // delete
            foreach ($delete_params as $name => $value) {
                $this->deleteByField(array(
                    'product_id' => $id,
                    'name' => $name
                ));
            }

            // add new params
            if ($add_params) {
                $this->multipleInsert($add_params);
            }

            return true;
        }
        return false;
    }


    /**
     * Clear custom params for this product
     * Shortcut for set($id, null)
     *
     * @param int $id product ID
     * @return bool
     */
    public function clear($id)
    {
        return $this->set($id, null);
    }

    /**
     * Triggered on mass product deleting
     * @param int[] $product_ids
     * @return bool
     */
    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }

    public function getData(shopProduct $product)
    {
        return $this->get($product->id);
    }

    private function toArray($params)
    {
        if (!is_string($params)) {
            return array();
        }
        $ar = array();
        if (!empty($params)) {
            foreach (explode("\n", $params) as $param_str) {
                $param = explode('=', $param_str, 2);
                if (count($param) > 1) {
                    $ar[$param[0]] = trim($param[1]);
                }
            }
        }
        return $ar;
    }

    public function setData(shopProduct $product, $params)
    {
        if (is_string($params)) {
            $params = $this->toArray($params);
        }
        $this->set($product->id, $params);
        return $this->get($product->id);
    }
}