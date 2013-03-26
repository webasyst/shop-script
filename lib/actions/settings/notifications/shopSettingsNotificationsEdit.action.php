<?php
class shopSettingsNotificationsEditAction extends shopSettingsNotificationsAction
{
    public function execute()
    {
        $id = waRequest::get('id');
        $model = new shopNotificationModel();
        $n = $model->getById($id);

        $params_model = new shopNotificationParamsModel();
        $params = $params_model->getParams($id);

        $this->view->assign('n', $n);
        $this->view->assign('params', $params);
        $this->view->assign('transports', self::getTransports());
        $this->view->assign('events', $this->getEvents());

        $this->view->assign('sms_from', $this->getSmsFrom());
    }
}
