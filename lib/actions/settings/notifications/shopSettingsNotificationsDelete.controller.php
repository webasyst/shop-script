<?php

class shopSettingsNotificationsDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');
        $model = new shopNotificationModel();
        if (!$model->delete($id)) {
            $this->errors = 'Error, try again';
        }
    }
}
