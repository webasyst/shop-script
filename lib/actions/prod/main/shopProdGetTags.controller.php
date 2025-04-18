<?php
class shopProdGetTagsController extends waJsonController
{
    public function execute()
    {
        $limit = 100;
        $tags = [];
        $this->response['params'] = [];
        $db_query = (new shopTagModel())->select('id,name')->limit($limit);

        $last_id = waRequest::get('last_id', 0, waRequest::TYPE_INT);
        if ($last_id) {
            $db_query = $db_query->where('id > :last_id', ['last_id' => $last_id]);
        }
        $q = waRequest::get('q', '', waRequest::TYPE_STRING_TRIM);
        if ($q) {
            $db_query = $db_query->where('name LIKE :q', ['q' => '%'.$q.'%']);
            $this->response['params']['q'] = $q;
        }

        $tags = $db_query->fetchAll();
        $count = count($tags);
        $this->response['tags'] = $tags;
        $this->response['params']['is_last'] = $count < $limit;
        if ($count) {
            $this->response['params']['last_id'] = (int)$tags[$count - 1]['id'];
        }
    }
}
