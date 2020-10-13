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
        
        $notification_sources_model = new shopNotificationSourcesModel();
        $notification_sources_model->updateByField(array('source' => $params['old']), array('source' => $params['new']));
        
        $followup_sources_model = new shopFollowupSourcesModel();
        $followup_sources_model->updateByField(array('source' => $params['old']), array('source' => $params['new']));

        wa('shop')->event(array('shop', 'update.route'), $params);
    }
}