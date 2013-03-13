<?php

class shopCategoryParamsModel extends waModel
{
    protected $table = 'shop_category_params';

    /**
     * Get custom params of category
     * @param int $id category ID
     * @return array params in key=>value format
     */
    public function get($id)
    {
        $params = array();
        foreach ($this->getByField('category_id', $id, true) as $p) {
            $params[$p['name']] = $p['value'];
        }
        return $params;
    }

    /**
     * Set custom params to category
     *
     * @param int $id category ID
     * @param array|null $params key=>value format of array or null (to delete all params assigned to category)
     */
    public function set($id, $params = array())
    {
        if ($id) {
            // remove if params is null
            if (is_null($params)) {
                return $this->deleteByField(array(
                    'category_id' => $id
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
                            'category_id' => $id,
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
                        'category_id' => $id,
                        'name' => $name,
                        'value' => $value
                    );
                }
            }

            // delete
            foreach ($delete_params as $name => $value) {
                $this->deleteByField(array(
                    'category_id' => $id,
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
     * Clear custom params for this category
     * Shortcut for set($id, null)
     *
     * @param int $id category ID
     */
    public function clear($id)
    {
        return $this->set($id, null);
    }
}