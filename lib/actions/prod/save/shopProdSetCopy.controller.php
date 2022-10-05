<?php

class shopProdSetCopyController extends waJsonController
{
    /** @var shopSetModel */
    protected $set_model;

    public function execute()
    {
        $set_id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);

        $this->set_model = new shopSetModel();
        $set = $this->set_model->getById($set_id);
        if ($set) {
            $copy_products = false;
            if ($set['type'] == shopSetModel::TYPE_STATIC) {
                $copy_products = (bool)waRequest::post('copy_products', 0, waRequest::TYPE_INT);
            }
            $new_set_id = $this->copy($set, $copy_products);

            if ($new_set_id !== false) {
                $new_set = $this->set_model->getById($new_set_id);
                $new_set['set_id'] = $new_set['id'];
                $this->response = $new_set;
            }
        } else {
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The set to update was not found.')
            ];
        }
    }

    /**
     * @param array $set
     * @param bool $copy_products
     * @throws waDbException
     */
    protected function copy($set, $copy_products)
    {
        $all_sets = $this->set_model->select('id, name')->fetchAll();
        $parent_id = $set['id'];
        $set['id'] = $this->suggestUniqueId($all_sets, $set['id']);
        $set['name'] = $this->suggestUniqueName($all_sets, $set['name']);
        $set['create_datetime'] = date('Y-m-d H:i:s');
        unset($set['edit_datetime']);

        $next_set_position = $set['sort'];
        $this->set_model->query("UPDATE {$this->set_model->getTableName()} SET sort = sort + 1 WHERE sort > $next_set_position");

        $response = $this->set_model->insert($set);
        if (!$response) {
            $this->errors = [
                'id' => 'copy_set',
                'text' => _w('Failed to copy the set.')
            ];
        } elseif ($copy_products) {
            $new_set_id = preg_replace('/[^a-z0-9\._-]+/im', '', $set["id"]);
            if (mb_strlen($new_set_id)) {
                $set_products_model = new shopSetProductsModel();
                $set_products_model->query("INSERT INTO {$set_products_model->getTableName()} (`set_id`, `product_id`, `sort`)
                    SELECT '{$new_set_id}' AS `set_id`, `product_id`, `sort`
                    FROM {$set_products_model->getTableName()} WHERE `set_id` = '{$parent_id}'");
                return $new_set_id;
            } else {
                $this->errors = [
                    'id' => 'copy_set_products',
                    'text' => _w('Failed to copy the set’s products.')
                ];
            }
        }

        return false;
    }

    /**
     * Suggest unique id by id among of sets
     * If not exists yet just return without changes, otherwise fit a number suffix and adding it to name.
     *
     * @param array $all_sets
     * @param string $id
     *
     * @return string
     */
    protected function suggestUniqueId($all_sets, $id)
    {
        preg_match("/_(\d+)$/s", $id, $matches);
        if (isset($matches[1]) && is_numeric($matches[1]) && $matches[1] < PHP_INT_MAX) {
            $new_id = preg_replace("/\d+$/s", $matches[1] + 1, $id);
        } else {
            $new_id = mb_substr($id, 0, 62) . '_2';
        }
        foreach ($all_sets as $set) {
            if ($set['id'] == $new_id) {
                $new_id = $this->suggestUniqueId($all_sets, $new_id);
                break;
            }
        }

        return $new_id;
    }

    /**
     * Suggest unique name by name among of sets
     * If not exists yet just return without changes, otherwise fit a number suffix and adding it to name.
     *
     * @param array $all_sets
     * @param string $name
     *
     * @return string
     */
    protected function suggestUniqueName($all_sets, $name)
    {
        $copy_text = _w('copy');
        $copy_text_formatted = preg_quote($copy_text);
        preg_match("/\({$copy_text_formatted}\)(?:(?:\s(\d+))*$)/s", $name, $matches);
        $new_name = '';
        if (isset($matches[0])) {
            if (isset($matches[1]) && is_numeric($matches[1]) && $matches[1] < PHP_INT_MAX) {
                $new_name = preg_replace("/\d+$/s", $matches[1] + 1, $name);
            } elseif ($matches[0] == ('(' . $copy_text . ')') && !isset($matches[1])) {
                $new_name = mb_substr($name, 0, 253) . ' 2';
            }
        }
        if (!$new_name) {
            $copy_text_length = mb_strlen($copy_text);
            $new_name = sprintf('%s (%s)', mb_substr($name, 0, 252 - $copy_text_length), $copy_text);
        }
        foreach ($all_sets as $set) {
            if ($set['name'] == $new_name) {
                $new_name = $this->suggestUniqueName($all_sets, $new_name);
                break;
            }
        }

        return $new_name;
    }
}
