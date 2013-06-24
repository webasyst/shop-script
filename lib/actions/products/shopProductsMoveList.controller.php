<?php

class shopProductsMoveListController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null);
        if (!$id) {
            throw new waException("Unknown list id");
        }

        $before_id = waRequest::post('before_id', null);
        $parent_id = waRequest::post('parent_id', 0);

        if ($id == $before_id) {
            throw new waException("List couldn't be inserted before itself");
        }

        if ($id == $parent_id) {
            throw new waException("List couldn't be parent of itself");
        }

        if (($before_id || $parent_id) && $before_id == $parent_id) {
            throw new waException("Before item couldn't be parent item");
        }

        $type = waRequest::post('type', '', waRequest::TYPE_STRING_TRIM);
        if (!$type) {
            throw new waException('Unknown list type: ' . $type);
        }

        if ($type == 'set' && $parent_id) {
            throw new waException("Sets don't support hierarchy");
        }

        $this->move($type, $id, $before_id, $parent_id);
    }

    public function move($type, $id, $before_id, $parent_id)
    {
        if ($type == 'category') {
            $category_model = new shopCategoryModel();


            if (!$category_model->move($id, $before_id, $parent_id)) {
                $this->errors = array('Error when move');
            } else {
                if ($parent_id) {
                    $parent = $category_model->getById($parent_id);
                    $this->response['count'] = array(
                            'count' => $parent['count'],
                            'subtree' => $category_model->getTotalProductsCount($parent_id)
                    );
                }
            }
        } else if ($type == 'set') {
            $set_model = new shopSetModel();
            if (!$set_model->move($id, $before_id)) {
                $this->errors = array('Error when move');
            }
        } else {
            throw new waException('Unknown list type: ' . $type);
        }
    }
}
