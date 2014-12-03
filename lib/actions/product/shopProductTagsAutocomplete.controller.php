<?php

class shopProductTagsAutocompleteController extends waController
{
    public function execute()
    {
        $limit = 10;
        $term = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);

        $tag_model = new shopTagModel();
        $term = $tag_model->escape($term, 'like');

        $tags = array();
        foreach ($tag_model->select('name')->where("name LIKE '$term%'")->limit($limit)->fetchAll() as $tag) {
            $tags[] = array(
                'value' => $tag['name'],
                'label' => htmlspecialchars($tag['name'])
            );
        }
        echo json_encode($tags);
    }
}
