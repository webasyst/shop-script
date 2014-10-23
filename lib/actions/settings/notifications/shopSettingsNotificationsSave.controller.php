<?php

class shopSettingsNotificationsSaveController extends waJsonController
{
    public function execute()
    {
        $data = waRequest::post('data', array());
        $data['source'] = $data['source'] ? $data['source'] : null;
        if (!isset($data['status'])) {
            $data['status'] = 0;
        }
        
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

        if (isset($params['from'])) {
            if (!$params['from']) {
                unset($params['from']);
            } else if ($params['from'] == 'other') {
                $params['from'] = waRequest::post('from');    
            }
        }
        
        $params_model = new shopNotificationParamsModel();
        $params_model->save($id, $params);

        $this->response = $model->getById($id);
        $transports = shopSettingsNotificationsAction::getTransports();
        $this->response['icon'] = $transports[$this->response['transport']]['icon'];
    }
}