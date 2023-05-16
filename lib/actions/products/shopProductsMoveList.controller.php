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
            throw new waException(_w('A set cannot be inserted before itself.'));
        }

        if ($id == $parent_id) {
            throw new waException(_w('A set cannot be the parent of itself.'));
        }

        if (($before_id || $parent_id) && $before_id == $parent_id) {
            throw new waException(_w('Before an item cannot be placed its parent item.'));
        }

        $type = waRequest::post('type', '', waRequest::TYPE_STRING_TRIM);
        if (!$type) {
            throw new waException(sprintf_wp('Unknown list type: %s.', $type));
        }

        if ($type == 'set' && $parent_id) {
            throw new waException(_w('Sets don’t support hierarchy.'));
        }

        $this->move($type, $id, $before_id, $parent_id);
    }

    public function move($type, $id, $before_id, $parent_id)
    {
        if ($type == 'category') {
            $category_model = new shopCategoryModel();

            $response = $category_model->move($id, $before_id, $parent_id);
            if ($response === true) {
                if ($parent_id) {
                    $parent = $category_model->getById($parent_id);
                    $this->response['count'] = array(
                        'count' => $parent['count'],
                        'subtree' => $category_model->getTotalProductsCount($parent_id)
                    );
                }
            } else {
                $this->errors = array(
                    array(
                        "id" => "move_error",
                        "text" => $response
                    )
                );
            }
        } else if ($type == 'set') {
            $set_model = new shopSetModel();
            if (!$set_model->move($id, $before_id)) {
                $this->errors = array('Error when move');
            }
        } else {
            throw new waException(sprintf_wp('Unknown list type: %s.', $type));
        }
    }
}
