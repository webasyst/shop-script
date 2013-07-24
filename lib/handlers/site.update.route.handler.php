<?php

class shopSiteUpdateRouteHandler extends waEventHandler
{
    /**
     * @param array $params array('old' => string, 'new' => string)
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $model = new shopCategoryRoutesModel();
        $model->updateByField(array('route' => $params['old']), array('route' => $params['new']));
    }
}