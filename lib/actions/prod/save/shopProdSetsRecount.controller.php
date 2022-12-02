<?php

class shopProdSetsRecountController extends waJsonController
{
    public function execute()
    {
        $static_ids = waRequest::post('static_ids', [], waRequest::TYPE_ARRAY_TRIM);
        $dynamic_ids = waRequest::post('dynamic_ids', [], waRequest::TYPE_ARRAY_TRIM);
        $set_ids = array_merge($static_ids, $dynamic_ids);
        $sets = $this->count($set_ids);
        if (!$this->errors) {
            $this->response['sets'] = $sets;
        }
    }

    protected function count($set_ids)
    {
        $set_model = new shopSetModel();

        $sets = [];
        if ($set_ids) {
            $old_count = $set_model->select('`id`, `count`')->where('`type` = ? AND `id` IN (?)', [$set_model::TYPE_STATIC, $set_ids])->fetchAll('id');
            foreach ($set_ids as $set_id) {
                try {
                    $product_collection = new shopProductsCollection("set/$set_id");
                    $set_right_count = $product_collection->count();
                    $sets[] = [
                        'id' => $set_id,
                        'count' => $set_right_count,
                    ];
                    if (isset($old_count[$set_id]) && $old_count[$set_id]['count'] != $set_right_count) {
                        $set_model->update($set_id, ['count' => $set_right_count]);
                    }
                } catch (Exception $e) {
                    $this->errors = $e->getMessage();
                }
            }
        }

        return $sets;
    }
}