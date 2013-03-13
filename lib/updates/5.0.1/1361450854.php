<?php

$category_model = new shopCategoryModel();
foreach ($category_model->select('id, conditions')->where('type = 1')->fetchAll('id') as $item) {
    $conditions = preg_replace('/\brate\b/', 'rating', $item['conditions']);
    if ($conditions != $item['conditions']) {
        $category_model->updateById($item['id'], array('conditions' => $conditions));
    }
}