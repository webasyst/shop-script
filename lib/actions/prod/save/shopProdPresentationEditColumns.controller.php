<?php
/**
 * Change presentation columns
 */
class shopProdPresentationEditColumnsController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $view_id = waRequest::post('view_id', null, waRequest::TYPE_STRING_TRIM);
        $columns = waRequest::post('columns', [], waRequest::TYPE_ARRAY);

        $presentation_model = new shopPresentationModel();
        $presentation = $presentation_model->getById($presentation_id);
        $this->validateData($presentation, $view_id);
        if (!$this->errors) {
            $presentation_columns_model = new shopPresentationColumnsModel();
            $new_presentation_id = self::duplicatePresentation($presentation, false);
            if ($new_presentation_id) {
                $this->response['new_presentation_id'] = $new_presentation_id;
                $presentation_id = $new_presentation_id;
            }
            $sort_column_id = (int)$presentation['sort_column_id'];
            $this->formatColumns($columns, $presentation_id, $view_id, $sort_column_id);
            if (!$new_presentation_id) {
                $presentation_columns_model->deleteByField('presentation_id', $presentation_id);
            }
            $presentation_columns_model->multipleInsert($columns);
            $new_sort_column_id = null;
            if (is_string($sort_column_id)) {
                $new_column = $presentation_columns_model->getByField([
                    'presentation_id' => $presentation_id,
                    'column_type' => $sort_column_id,
                ]);
                if ($new_column) {
                    $new_sort_column_id = $new_column['id'];
                }
            }
            $presentation_model->updateById($presentation_id, ['sort_column_id' => $new_sort_column_id]);
        }
    }

    /**
     * @param array|int $presentation
     * @return int
     */
    public static function duplicatePresentation($presentation, $copy_columns = true)
    {
        if (is_numeric($presentation)) {
            $presentation_model = new shopPresentationModel();
            $presentation = $presentation_model->getById($presentation);
        }
        $open_presentations = waRequest::post('open_presentations', [], waRequest::TYPE_ARRAY);
        $browser_name = shopPresentation::getBrowserName();
        if (is_array($presentation) && (in_array($presentation['id'], $open_presentations) || $presentation['browser'] != $browser_name)) {
            $datetime = new DateTime('now');
            $duplicate_options = [
                'use_datetime' => $datetime->format('Y-m-d H:i:s'),
                'browser' => $browser_name,
            ];
            $presentation_model = new shopPresentationModel();
            return $presentation_model->duplicate($presentation['id'], shopPresentationModel::DUPLICATE_MODE_PRESENTATION, $duplicate_options, $copy_columns);
        }
        return null;
    }

    /**
     * @param array $presentation
     * @param string $view_id
     * @return void
     */
    protected function validateData($presentation, $view_id)
    {
        if (!$presentation) {
            $this->errors = [
                'id' => 'presentation_id',
                'text' => _w('Saved view not found.'),
            ];
            return;
        }
        if (empty($presentation['parent_id'])) {
            $this->errors = [
                'id' => 'presentation_id',
                'text' => _w('A saved view cannot be a template.'),
            ];
            return;
        }

        if (!in_array($view_id, [shopPresentation::VIEW_THUMBS, shopPresentation::VIEW_TABLE, shopPresentation::VIEW_TABLE_EXTENDED])) {
            $this->errors = [
                'id' => 'view_id',
                'text' => _w('Incorrect view type. type.'),
            ];
        }
    }

    /**
     * @param array $columns
     * @param int $presentation_id
     * @param string $view_id
     * @return void
     */
    protected function formatColumns(&$columns, $presentation_id, $view_id, &$sort_column_id)
    {
        $required_columns = shopPresentation::getRequiredColumns($view_id);
        $column_names = array_column($columns, 'column_type');
        $missed_columns = array_diff($required_columns, $column_names);
        $presentation_columns_model = new shopPresentationColumnsModel();
        $enabled_columns = $presentation_columns_model->getByField([
            'presentation_id' => $presentation_id,
            'column_type' => $column_names,
        ], 'column_type');
        foreach ($missed_columns as $column_name) {
            $columns[] = [
                'column_type' => $column_name
            ];
        }
        $sort = 0;
        foreach ($columns as &$column) {
            $column['presentation_id'] = $presentation_id;
            $column['sort'] = $sort++;
            $column['width'] = null;
            if (isset($enabled_columns[$column['column_type']])) {
                $column['width'] = (int)$enabled_columns[$column['column_type']]['width'];
                if ($enabled_columns[$column['column_type']]['id'] == $sort_column_id) {
                    $sort_column_id = $column['column_type'];
                }
            }
            $column_data = null;
            if (isset($enabled_columns[$column['column_type']]['data'])) {
                $column_data = json_decode($enabled_columns[$column['column_type']]['data'], true);
            }
            if (!empty($column['settings'])) {
                if (is_array($column_data)) {
                    $column['data'] = array_merge($column_data, [
                        'settings' => $column['settings']
                    ]);
                } else {
                    $column['data'] = [
                        'settings' => $column['settings']
                    ];
                }
            } elseif (empty($column_names['data'])) {
                $column['data'] = $column_data;
            }
            unset($column['settings']);
        }
    }
}
