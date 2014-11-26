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
        
        $notification_model = new shopNotificationModel();
        $notification_model->updateByField(array('source' => $params['old']), array('source' => $params['new']));
        
        $followup_model = new shopFollowupModel();
        $followup_model->updateByField(array('source' => $params['old']), array('source' => $params['new']));

        wa('shop')->event(array('shop', 'update.route'), $params);
    }
}