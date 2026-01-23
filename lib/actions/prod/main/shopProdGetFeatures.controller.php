<?php
class shopProdGetFeaturesController extends waJsonController
{
    public function execute()
    {
        $last_id = waRequest::get('last_id', 0, waRequest::TYPE_INT);
        $q = waRequest::get('q', '', waRequest::TYPE_STRING_TRIM);
        $limit = 50;

        $items = $this->getFilterFeatures($last_id, $q, $limit);
        $count = count($items);
        $this->response = [
            'items' => $items,
            'params' => [
                'last_id' => $count >= $limit ? (int)$items[$count - 1]['id'] : null
            ]
        ];
        if (strlen($q)) {
            $this->response['params']['q'] = $q;
        }
    }

    private function getFilterFeatures($last_id=null, $search_string='', $limit=null)
    {
        $feature_model = new shopFeatureModel();
        $query = $feature_model
            ->select('*, CONCAT("feature_", id) AS `rule_type`')
            ->where(
                '`type` != "text" AND `type` != "divider" AND `type` NOT LIKE "2d.%" AND `type` NOT LIKE "3d.%" AND `parent_id` IS NULL',
            );

        if ($last_id) {
            $query = $query->where('id > (?)', [$last_id]);
        }
        if (strlen($search_string)) {
            $query = $query->where('name LIKE (?)', ['%'.$search_string.'%']);
        }
        if ($limit > 0) {
            $query = $query->limit($limit);
        }

        $features = array_map(function($f) {
            return [
                'id' => $f['id'],
                'code' => $f['code'],
                'name' => $f['name'],
                'rule_type' => empty($f['multiple']),
                'selectable' => $f['selectable'],
                'type' => $f['type'],
                // 'display_type' => empty($f['multiple']),
                // 'replaces_previous' => empty($f['multiple']),
            ];
        }, $query->fetchAll('rule_type'));

        return array_values($features);
    }
}
