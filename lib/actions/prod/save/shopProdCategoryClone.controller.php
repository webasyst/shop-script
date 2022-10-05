<?php

class shopProdCategoryCloneController extends waJsonController
{
    /** @var shopCategoryModel */
    protected $category_model;

    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $copy_products = (bool)waRequest::post('copy_products', null, waRequest::TYPE_INT);
        $copy_categories = waRequest::post('copy_categories', null, waRequest::TYPE_INT) ? null : 0;

        $this->category_model = new shopCategoryModel();

        $tree = $this->category_model->getTree($category_id, $copy_categories);
        $this->validateData($category_id, $tree);

        if (!$this->errors) {
            $this->copy($category_id, $tree, $copy_products);
        }

        if ($this->errors) {
            $this->response['category_copy'] = false;
        } else {
            $this->response['category_copy'] = true;
            if (count($tree) > 1 && $copy_categories) {
                $this->logAction('categories_duplicate', $category_id);
            } else {
                $this->logAction('category_duplicate', $category_id);
            }
        }
    }

    /**
     * @param $category_id
     * @param $tree
     */
    protected function validateData($category_id, $tree)
    {
        if (empty($tree) || !isset($tree[$category_id])) {
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The category to update was not found.')
            ];
        }
    }

    /**
     * @param int $category_id
     * @param array $tree
     * @param bool $copy_products
     * @throws waDbException
     */
    protected function copy($category_id, $tree, $copy_products)
    {
        $new_tree = [];
        foreach ($tree as $id => $category) {
            if ($id != $category_id) {
                $new_parent_id = ifset($new_tree, $category['parent_id'], null);
                $custom_data = [
                    'parent_id' => $new_parent_id
                ];
            } else {
                $saved_names = $this->category_model->select('name')
                    ->where("parent_id = i:parent_id", ['parent_id' => $category['parent_id']])->fetchAll();
                $new_name = $this->suggestUniqueName($saved_names, $category['name']);
                $custom_data = [
                    'name' => $new_name
                ];
            }
            $response = $this->category_model->duplicate($id, $copy_products, $custom_data);
            if (is_numeric($response)) {
                $new_tree[$id] = $response;
            } elseif (is_array($response)) {
                $this->errors = $response;
                break;
            } else {
                $this->errors = [
                    'id' => 'copy_category',
                    'text' => _w('Failed to copy the category.')
                ];
                break;
            }
        }
    }

    /**
     * Suggest unique name by name among one level of categories
     * If not exists yet just return without changes, otherwise fit a number suffix and adding it to name.
     *
     * @param array $saved_names
     * @param string $name
     *
     * @return string
     */
    protected function suggestUniqueName($saved_names, $name)
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
        foreach ($saved_names as $saved_name) {
            if ($saved_name['name'] == $new_name) {
                $new_name = $this->suggestUniqueName($saved_names, $new_name);
                break;
            }
        }

        return $new_name;
    }
}
