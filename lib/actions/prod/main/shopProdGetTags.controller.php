<?php
class shopProdGetTagsController extends waJsonController
{
    public function execute()
    {
        $limit = 100;
        $tags = [];
        $db_query = (new shopTagModel())->select('id,name')->limit($limit);

        $last_id = waRequest::get('last_id', null, waRequest::TYPE_INT);
        if ($last_id) {
            $db_query = $db_query->where('id > (?)', [$last_id]);
        }
        $q = waRequest::get('q', '', waRequest::TYPE_STRING_TRIM);
        if (strlen($q)) {
            $db_query = $db_query->where('name LIKE (?)', ['%'.$q.'%']);
        }

        $tags = $db_query->fetchAll();
        $count = count($tags);
        $this->response = [
            'items' => $tags,
            'params' => [
                'last_id' => $count === $limit ? (int)$tags[$count - 1]['id'] : null
            ],
        ];
        if (strlen($q)) {
            $this->response['params']['q'] = $q;
        }
    }
}
