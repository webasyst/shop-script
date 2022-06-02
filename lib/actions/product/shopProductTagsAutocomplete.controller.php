<?php

class shopProductTagsAutocompleteController extends waController
{
    public function execute()
    {
        $limit = 10;
        $term = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);
        $type = waRequest::get('type',null, waRequest::TYPE_STRING);

        $tag_model = new shopTagModel();
        $term = $tag_model->escape($term, 'like');

        if ($type == 'search') {
           $where =  "name LIKE '$term%' AND count > 0";
        } else {
            $where = "name LIKE '$term%'";
        }

        $tags = array();
        try {
            foreach ($tag_model->select('name')->where($where)->limit($limit)->fetchAll() as $tag) {
                $tags[] = array(
                    'value' => $tag['name'],
                    'label' => htmlspecialchars($tag['name'])
                );
            }
        } catch (waDbException $dbe) {
            if ($dbe->getCode() === 1267) {
                $tags = [];
            } else {
                throw $dbe;
            }
        }
        echo json_encode($tags);
    }
}
