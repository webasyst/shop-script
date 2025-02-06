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

        $presentation = new shopPresentation($presentation_id);
        $active_presentation = $presentation->getData();

        $action = new shopProdListAction();
        $columns = $action->mergeColumns($presentation->getColumnsList(), $active_presentation['columns'], false);
        $columns = $action->formatColumns($columns, true);

        $this->response = array_values($columns);
    }
}
