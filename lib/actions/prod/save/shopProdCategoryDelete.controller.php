<?php

class shopProdCategoryDeleteController extends waJsonController
{
    /** @var shopCategoryModel */
    protected $category_model;

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $remove_products = waRequest::post('remove_products', null, waRequest::TYPE_INT);
        $remove_categories = waRequest::post('remove_categories', null, waRequest::TYPE_INT);

        $this->category_model = new shopCategoryModel();
        $this->validateData($category_id);
        if (!$this->errors) {
            $tree = [];
            if ($remove_products || $remove_categories) {
                $tree = $this->category_model->getTree($category_id);
            }
            if ($remove_products) {
                $this->deleteProducts($category_id, $tree, $remove_categories);
            }
            if ($remove_categories) {
                $this->deleteSubcategories($category_id, $tree);
            }
            $this->delete($category_id);
        }
    }

    protected function validateData($category_id)
    {
        $correct_category = false;
        if ($category_id > 0) {
            $category = $this->category_model->select('id')->where('id = ?', $category_id)->fetchField('id');
            if ($category) {
                $correct_category = true;
            }
        }
        if (!$correct_category) {
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The category to update was not found.')
            ];
        }
    }

    protected function delete($category_id)
    {
        $result = $this->category_model->delete($category_id);
        if ($result) {
            $this->logAction('category_delete', $category_id);
            $this->response['category_deleted'] = true;
        } else {
            $this->errors += [
                'id' => 'delete_category',
                'text' => _w('Failed to delete the category.')
            ];
        }
    }

    /**
     * @param int $category_id
     * @param array $tree
     * @return void
     * @throws waDbException
     * @throws waException
     */
    protected function deleteProducts($category_id, $tree, $remove_categories)
    {
        if ($tree) {
            $product_model = new shopProductModel();
            if (isset($tree[$category_id]) && $tree[$category_id]['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                $collection = new shopProductsCollection('category/' . $category_id);
                $processed = 0;
                $count = 100;
                $total_count = $collection->count();
                $product_ids = [];
                while ($processed < $total_count) {
                    $offset = max($total_count - $count - $processed, 0);
                    $product_ids += array_keys($collection->getProducts('*', $offset, $count));
                    $processed = count($product_ids);
                    if (!$product_ids) {
                        break;
                    }
                }
            } else {
                $category_products_model = new shopCategoryProductsModel();
                $category_ids = !empty($tree[$category_id]['include_sub_categories']) || !empty($remove_categories) ? array_keys($tree) : $category_id;
                $product_ids = $category_products_model->select('product_id')->where('category_id IN (?)', [$category_ids])->fetchAll('product_id');
                $product_ids = array_keys($product_ids);
            }
            $delete_ids = $product_model->filterAllowedProductIds($product_ids);
            if ($delete_ids) {
                $result = $product_model->delete($delete_ids);
                if ($result) {
                    $not_allowed_ids = array_diff($product_ids, $delete_ids);
                    $this->response['deleted_products'] = $delete_ids;
                    $this->response['not_allowed_products'] = $not_allowed_ids;
                    $delete_ids_with_name = $product_model->select('id, name')->where('id IN (?)', [$delete_ids])->fetchAll('id');
                    $count_all_products = count($delete_ids_with_name);
                    if ($count_all_products > 1) {
                        for ($offset = 0; $offset < $count_all_products; $offset += 200) {
                            $part_products = array_slice($delete_ids_with_name, $offset, 200, true);
                            $this->logAction('products_delete', $part_products);
                        }
                    } else {
                        $this->logAction('product_delete', $delete_ids_with_name);
                    }
                } else {
                    $this->errors += [
                        'id' => 'delete_products',
                        'text' => _w('Failed to delete products.')
                    ];
                }
            } else {
                $this->response['no_products_found'] = true;
            }
        }
    }

    protected function deleteSubcategories($category_id, $tree)
    {
        $all_subcategories_deleted = true;
        foreach ($tree as $id => $category) {
            if ($id != $category_id) {
                $result = $this->category_model->delete($id);
                if ($result) {
                    $this->logAction('category_delete', $id);
                } else {
                    $all_subcategories_deleted = false;
                    $this->errors += [
                        'id' => 'delete_all_subcategories',
                        'text' => _w('Failed to delete the category.')
                    ];
                }
            }
        }
        if ($all_subcategories_deleted) {
            $this->response['all_subcategories_deleted'] = true;
        }
    }
}
