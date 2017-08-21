<?php

class shopSettingsNotificationsSaveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::get('id', null, 'int');
        $data = waRequest::post('data', array(), 'array');
        $params = waRequest::post('params', array(), 'array');
        $data['source'] = $data['source'] ? $data['source'] : null;
        if (!isset($data['status'])) {
            $data['status'] = 0;
        }

        $model = new shopNotificationModel();
        $params_model = new shopNotificationParamsModel();

        // In restricted mail mode it's only allowed to use notifications
        // with default text. This is useful for demo and trial accounts.
        if(wa('shop')->getConfig()->getOption('restricted_mail')) {
            if ($id) {
                $n = $model->getById($id);
                $event = ifset($n['event']);
            } else {
                $event = ifset($data['event']);
            }
            $action = new shopSettingsNotificationsAddAction();
            $templates = $action->getTemplates();
            if (empty($templates[$event])) {
                throw new waRightsException();
            }

            $params['subject'] = $templates[$event]['subject'];
            $params['body'] = $templates[$event]['body'];
        }

        if (!$id) {
            $id = $model->insert($data);
        } else {
            $model->updateById($id, $data);
        }

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

        $params_model->save($id, $params);

        $notification = $model->getById($id);
        if ($notification) {
            $event_params = array(
                'notification' => $notification,
                'params' => $params_model->getParams($id),
            );
            wa()->event('backend_notification_save', $event_params);
        }

        $this->response = $model->getById($id);
        $transports = shopSettingsNotificationsAction::getTransports();
        $this->response['icon'] = $transports[$this->response['transport']]['icon'];
    }
}
