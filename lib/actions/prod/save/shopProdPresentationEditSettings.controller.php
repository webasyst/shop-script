<?php
/**
 * Change settings in the presentation
 */
class shopProdPresentationEditSettingsController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $view = waRequest::post('view', null, waRequest::TYPE_STRING_TRIM);
        $rows_on_page = waRequest::post('rows_on_page', null, waRequest::TYPE_INT);
        $sort_order = waRequest::post('sort_order', null, waRequest::TYPE_STRING_TRIM);
        $column_id = waRequest::post('column_id', null, waRequest::TYPE_INT);
        $width = waRequest::post('width', null, waRequest::TYPE_INT);

        $presentation_model = new shopPresentationModel();
        $presentation = $presentation_model->getById($presentation_id);
        $column_type = $this->validateData($presentation_id, $view, $rows_on_page, $sort_order, $column_id, $width);
        if (!$this->errors) {
            $new_presentation_id = shopProdPresentationEditColumnsController::duplicatePresentation($presentation);
            if ($new_presentation_id) {
                $this->response['new_presentation_id'] = $new_presentation_id;
                $presentation_id = $new_presentation_id;
            }
            $new_data = [];
            if ($view) {
                $new_data['view'] = $view;
            }
            if ($rows_on_page) {
                $new_data['rows_on_page'] = $rows_on_page;
            }
            if ($sort_order) {
                $new_data['sort_order'] = $sort_order;
            }
            if ($column_id) {
                $presentation_columns_model = new shopPresentationColumnsModel();
                if ($new_presentation_id) {
                    $column_data = $presentation_columns_model->getByField([
                        'presentation_id' => $new_presentation_id,
                        'column_type' => $column_type,
                    ]);
                    $column_id = $column_data['id'];
                }
                if ($width) {
                    $presentation_columns_model = new shopPresentationColumnsModel();
                    $presentation_columns_model->updateById($column_id, [
                        'width' => $width,
                    ]);
                } else {
                    $new_data['sort_column_id'] = $column_id;
                }
            }
            if ($new_data) {
                $presentation_model->updateById($presentation_id, $new_data);
            }
        }
    }

    /**
     * @param array $presentation
     * @param string $view
     * @param int $rows_on_page
     * @param string $sort_order
     * @param int $column_id
     * @param int $width
     * @return string|null
     */
    protected function validateData($presentation, $view, $rows_on_page, $sort_order, $column_id, $width)
    {
        if (!$presentation) {
            $this->errors = [
                'id' => 'presentation_id',
                'text' => _w('Saved view not found.'),
            ];
            return null;
        }
        if ($view && !in_array($view, [shopPresentation::VIEW_THUMBS, shopPresentation::VIEW_TABLE, shopPresentation::VIEW_TABLE_EXTENDED])) {
            $this->errors = [
                'id' => 'view',
                'text' => _w('Incorrect view type.'),
            ];
        }
        if ($rows_on_page && $rows_on_page <= 0) {
            $this->errors = [
                'id' => 'rows_on_page',
                'text' => _w('Negative number of products per page.'),
            ];
        }
        if ($sort_order && $sort_order != 'ASC' && $sort_order != 'DESC') {
            $this->errors = [
                'id' => 'sort_order',
                'text' => _w('Incorrect column sorting.'),
            ];
        }

        if ($column_id) {
            $presentation_columns_model = new shopPresentationColumnsModel();
            $column_data = $presentation_columns_model->getById($column_id);
            if (!$column_data) {
                $this->errors = [
                    'id' => 'column_type',
                    'text' => _w('Column not enabled.'),
                ];
                return null;
            }
            // not more than 4K wide
            if ($width && ($width <= 0 || $width > 3840)) {
                $this->errors = [
                    'id' => 'width',
                    'text' => _w('Width too small or too large.'),
                ];
            }

            return $column_data['column_type'];
        }
        return null;
    }
}
