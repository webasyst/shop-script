<?php

class shopSettingsNotificationsSaveController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post('data', array());
        $id = waRequest::get('id');
        $model = new shopNotificationModel();
        if (!$id) {
            $id = $model->insert($data);
        } else {
            $model->updateById($id, $data);
        }

        $params = waRequest::post('params');
        if (isset($params['to']) && !$params['to']) {
            $params['to'] = waRequest::post('to');
        }
        $params_model = new shopNotificationParamsModel();
        $params_model->save($id, $params);

        $this->response = $model->getById($id);
        $transports = shopSettingsNotificationsAction::getTransports();
        $this->response['icon'] = $transports[$this->response['transport']]['icon'];
    }
}