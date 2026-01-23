<?php
/**
 * Settings for columns for various parts in products list section.
 */
class shopProdPresentationGetColumnsController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::request('presentation_id', null, 'int');
        if (!$presentation_id) {
            $this->response = [];
            return;
        }

        $q = waRequest::request('q', '', waRequest::TYPE_STRING_TRIM);
        $last_id = waRequest::request('last_id', null, 'int');
        $presentation = new shopPresentation($presentation_id);

        $limit = 50;
        $active_presentation_columns = [];
        $active_features_columns_list = [];
        if (!$last_id && !strlen($q)) {
            $active_presentation_columns = $presentation->getData()['columns'];
            $features_ids = [];
            foreach ($active_presentation_columns as $column) {
                if (substr($column['column_type'], 0, 8) === 'feature_') {
                    $features_ids[] = substr($column['column_type'], 8);
                }
            }
            if ($features_ids) {
                $active_features_columns_list = $presentation->getColumnsList(['features_ids' => $features_ids, 'last_id' => 0]);
            }
        }

        $columns_list = $active_features_columns_list + $presentation->getColumnsList([
            'last_id' => $last_id,
            'search_string' => $q,
            'limit' => $limit
        ]);

        $action = new shopProdListAction();
        $columns = $action->mergeColumns($columns_list, $active_presentation_columns, false);
        $columns = $action->formatColumns($columns, true);

        $last_id = null;
        $count = count($columns_list);
        if ($count >= $limit) {
            if ($last_id) {
                $last_id = $columns_list[$count - 1];
            } else {
                foreach (array_reverse($columns_list) as $column) {
                    if (!empty($column['feature_id'])) {
                        $last_id = $column['feature_id'];
                        break;
                    }
                }
            }
        }
        $this->response = [
            'items' => array_values($columns),
            'params' => ['last_id' => $last_id ? $last_id : null]
        ];
        if (strlen($q)) {
            $this->response['params']['q'] = $q;
        }
    }
}
